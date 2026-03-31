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
function get_db($conn) {
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

    // Claim next task
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
                    'id' => $video['id'], 'process' => $process, 'pipes' => $pipes,
                    'duration' => $duration, 'last_percent' => -1, 'input' => $input,
                    'output' => $output, 'outputName' => $outputName
                ];
                echo "[*] STARTED ID {$video['id']} FROM Gallery {$video['gallery_id']} (" . round(filesize($input)/1048576, 2) . " MB)\n";
            }
        } else {
            sleep(5);
        }
    }
    usleep(100000); 
}