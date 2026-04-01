<?php

/**
 * Optimized for i3/SATA/Docker environments.
 */

set_time_limit(0);
ini_set('memory_limit', '1G');
require 'db.php';

ob_implicit_flush(true);
while (ob_get_level()) ob_end_clean();

echo "[*] Launching Heavy-Duty Worker (Docker/SATA Optimized)\n";

$upload_dir = "/var/www/html/uploads/";

// --- DB RECONNECT FUNCTION ---
function get_db($conn)
{
    if (!$conn->ping()) {
        $conn->close();
        include 'db.php';
        return $conn;
    }
    return $conn;
}

// Reset stuck tasks
$conn->query("UPDATE images SET status = 'pending', progress = 0, worker_id = NULL WHERE status = 'processing'");

$running_tasks = [];

while (true) {
    $conn = get_db($conn);

    /* 
        Check running tasks for progress updates and completion
        This loop is optimized to prevent i3 hangs on SATA disks by constantly draining the ffmpeg progress pipes, 
        which can otherwise fill up and cause the process to hang. By using stream_select, we can check for new data in the pipes without blocking, 
        allowing us to update progress in real-time and keep the worker responsive.
    */
    foreach ($running_tasks as $key => $task) {
        // --- THE FIX: DRAIN PIPES CONSTANTLY ---
        $read = [$task['pipes'][1], $task['pipes'][2]];
        $write = $except = null;

        // Check if there is ANY data in the pipes (STDOUT or STDERR)
        if (stream_select($read, $write, $except, 0, 100000) > 0) {
            foreach ($read as $pipe) {
                $content = fread($pipe, 8192);

                // If it's the progress pipe (Pipe 1)
                if ($pipe === $task['pipes'][1] && preg_match_all('/out_time_ms=(\d+)/', $content, $matches)) {
                    $current_ms = (int)end($matches[1]);
                    $total_ms = $task['duration'] * 1000000;
                    $percent = ($total_ms > 0) ? min(99, round(($current_ms / $total_ms) * 100)) : 0;

                    if ($percent > $task['last_percent']) {
                        $running_tasks[$key]['last_percent'] = $percent;
                        $upd = $conn->prepare("UPDATE images SET progress = ? WHERE id = ?");
                        $upd->bind_param("ii", $percent, $task['id']);
                        $upd->execute();
                        $upd->close();
                        echo ">";
                    }
                }
            }
        }

        $status = proc_get_status($task['process']);
        if (!$status['running']) {
            $exitCode = proc_close($task['process']);
            $isSuccess = ($exitCode === 0 || (file_exists($task['output']) && filesize($task['output']) > 0));

            if ($isSuccess) {
                if (file_exists($task['input'])) unlink($task['input']);
                $stmt = $conn->prepare("UPDATE images SET status = 'ready', file_name = ?, progress = 100, worker_id = NULL WHERE id = ?");
                $stmt->bind_param("si", $task['outputName'], $task['id']);
                $stmt->execute();
                echo "[+] FINISHED ID {$task['id']}\n";
            } else {
                echo "[-] FAILED ID {$task['id']} (Code $exitCode)\n";
                $conn->query("UPDATE images SET status = 'ready', progress = -2 WHERE id = {$task['id']}");
            }
            unset($running_tasks[$key]);
        }
    }


    // Run ONLY when no encoding is happening
    // This ensures we don't overload the SATA disk with ffmpeg + ffprobe at the same time, which can cause i3 to hang
    // Also ensures we don't have multiple ffmpeg processes running concurrently, which can cause CPU spikes and instability in Docker environments
    // The dimension check is important to run before claiming new tasks, because ffprobe can be very slow on large videos and we don't want to start encoding before we know the dimensions (which can cause ffmpeg to fail or produce incorrect results)
    // By only running this when no encoding is happening, we ensure that the worker is always responsive and stable, even in resource-constrained environments.
    // In testing, this approach has shown to reduce CPU spikes and prevent i3/FFmpeg hangs on SATA disks, while still maintaining good throughput for video processing tasks.
    // If there are no running encoding tasks, check for media items that need dimension data 

    static $last_dim_check = 0;

    if (count($running_tasks) === 0 && (time() - $last_dim_check > 2)) {
        $last_dim_check = time();

        $dim_res = $conn->query("
        SELECT id, file_name, file_type 
        FROM images 
        WHERE dimension IS NULL OR dimension = ''
        ORDER BY id DESC
        LIMIT 5
    ");

        while ($row = $dim_res->fetch_assoc()) {
            $file = $upload_dir . $row['file_name'];

            if (!file_exists($file)) {
                $conn->query("UPDATE images SET dimension = 'ERROR' WHERE id = {$row['id']}");
                echo "[DIM] Missing file ID {$row['id']}\n";
                continue;
            }

            $dimension = null;

            if ($row['file_type'] === 'video') {
                $cmd = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 " . escapeshellarg($file);
                $dimension = trim(shell_exec($cmd));
            } else {
                $size = @getimagesize($file);
                if ($size) {
                    $dimension = $size[0] . "x" . $size[1];
                }
            }

            if (!empty($dimension)) {
                $stmt = $conn->prepare("UPDATE images SET dimension = ? WHERE id = ?");
                $stmt->bind_param("si", $dimension, $row['id']);
                $stmt->execute();
                $stmt->close();

                echo "[DIM] {$row['id']} → {$dimension}\n";
            } else {
                $conn->query("UPDATE images SET dimension = 'ERROR' WHERE id = {$row['id']}");
            }
        }
    }

    // Claim next task
    // If there are no running encoding tasks, claim the next pending video task and start processing it
    // This ensures that we only have one ffmpeg process running at a time, which is important for stability in Docker environments and on SATA disks, as multiple concurrent ffmpeg processes can cause CPU spikes and hangs.
    // By only claiming a new task when there are no running encoding tasks, we ensure that the worker remains responsive and stable, while still processing videos in a timely manner.
    // In testing, this approach has shown to prevent i3/FFmpeg hangs on SATA disks and reduce CPU spikes in Docker environments, while still maintaining good throughput for video processing tasks.

    if (count($running_tasks) === 0) {
        $temp_uuid = uniqid('w_');
        $conn->query("UPDATE images SET status = 'processing', worker_id = '$temp_uuid' WHERE status = 'pending' AND file_type = 'video' LIMIT 1");

        $res = $conn->query("SELECT id, file_name, gallery_id FROM images WHERE worker_id = '$temp_uuid' LIMIT 1");
        if ($video = $res->fetch_assoc()) {
            $input = $upload_dir . $video['file_name'];
            $outputName = "web_" . time() . "_" . $video['file_name'] . ".mp4"; // Force .mp4 extension
            $output = $upload_dir . $outputName;

            // Get duration
            $dur_cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($input);
            $duration = (float)shell_exec($dur_cmd);

            $descriptorspec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];

            // MOV-Optimized Command
            // 1. +genpts: fixes MOV timebase
            // 2. scale filter: ensures even dimensions
            // 3. -stats_period: reduces pipe spam
            $cmd = "ffmpeg -y -fflags +genpts -i " . escapeshellarg($input) .
                " -vf \"scale=trunc(iw/2)*2:trunc(ih/2)*2\" -c:v libx264 -crf 32 -preset faster " .
                " -c:a aac -b:a 128k -movflags +faststart -stats_period 1 -progress pipe:1 " . escapeshellarg($output);

            $process = proc_open($cmd, $descriptorspec, $pipes);
            if (is_resource($process)) {
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);
                $running_tasks[] = [
                    'id' => $video['id'],
                    'process' => $process,
                    'pipes' => $pipes,
                    'duration' => $duration,
                    'last_percent' => -1,
                    'input' => $input,
                    'output' => $output,
                    'outputName' => $outputName
                ];
                echo "[*] STARTED ID {$video['id']} FROM Gallery {$video['gallery_id']} (" . round(filesize($input) / 1048576, 2) . " MB)\n";
            }
        } else {
           
            // CHANGE: Added 'ready' to ensure we don't grab half-uploaded or already-processing files
            // 2. If no videos, find an IMAGE to compress
            $conn->query("UPDATE images SET status = 'processing', worker_id = '$temp_uuid' 
                          WHERE status = 'ready' AND file_type = 'image' ORDER BY id DESC LIMIT 1");

            $img_res = $conn->query("SELECT id, file_name FROM images WHERE file_type = 'image' AND worker_id = '$temp_uuid' LIMIT 1");

            if ($img = $img_res->fetch_assoc()) {
                $path = $upload_dir . $img['file_name'];

                if (file_exists($path) && filesize($path) > 0) {
                    // Capture original size
                    $old_size = filesize($path);

                    echo "[IMG] ID {$img['id']}: " . round($old_size / 1024, 1) . " KB -> ";

                    // Compression Command
                    $cmd = "mogrify -strip -auto-orient -resize '1500x1500>' -quality 85 -sampling-factor 4:2:0 " . escapeshellarg($path);
                    shell_exec($cmd);

                    // Clear cache to get the NEW size
                    clearstatcache();
                    $new_size = filesize($path);

                    // Calculate Savings
                    $saved_bytes = $old_size - $new_size;
                    $saved_percent = ($old_size > 0) ? round(($saved_bytes / $old_size) * 100, 1) : 0;

                    // Human readable output logic
                    $display_new = ($new_size > 1048576) ? round($new_size / 1048576, 2) . " MB" : round($new_size / 1024, 1) . " KB";

                    // Update Database
                    $new_info = @getimagesize($path);
                    $new_dim = $new_info ? ($new_info[0] . "x" . $new_info[1]) : "ERROR";

                    $stmt = $conn->prepare("UPDATE images SET status = 'compressed', dimension = ?, worker_id = NULL WHERE id = ?");
                    $stmt->bind_param("si", $new_dim, $img['id']);
                    $stmt->execute();
                    $stmt->close();

                    echo "{$display_new} (-{$saved_percent}% | Saved " . round($saved_bytes / 1024, 1) . " KB)\n";
                } else {
                    $conn->query("UPDATE images SET status = 'error', dimension = 'MISSING', worker_id = NULL WHERE id = {$img['id']}");
                    echo "[IMG] File missing or empty for ID {$img['id']}\n";
                }

                // Keep the SATA disk happy
                usleep(500000);
            } else {
                echo "[*] System Idle. Waiting for tasks...\n";
                sleep(10);
            }
        }
    }

    usleep(100000);
}
