<?php
require 'db.php';

ob_implicit_flush(true);
while (ob_get_level()) ob_end_clean();

echo "[*] Booting Disk-Scan Optimized Worker...\n";

// 1. Connection Check
if ($conn->connect_error) die("[-] DB Fail.\n");

/**
 * STEP 1: MASS DISK SCAN (The "Deep Breath" phase)
 * We scan the directory once to see what actually exists on the disk.
 */
echo "[*] Scanning /uploads/ directory... (Please wait)\n";
$upload_dir = "/var/www/html/uploads/";
$physical_files = scandir($upload_dir);
// Flip for O(1) lookup speed instead of O(n)
$existing_files = array_flip($physical_files); 
echo "[+] Scan complete. Found " . count($physical_files) . " physical items.\n";

/**
 * STEP 2: BULK GHOST-FILE CLEANUP
 * We find all 'pending' videos and check them against our memory array.
 */
echo "[*] Syncing Database with Disk... \n";
$result = $conn->query("SELECT id, file_name FROM images WHERE status = 'pending' AND file_type = 'video'");
$ghost_ids = [];

while ($row = $result->fetch_assoc()) {
    if (!isset($existing_files[$row['file_name']])) {
        $ghost_ids[] = $row['id'];
    }
}

if (!empty($ghost_ids)) {
    $id_list = implode(',', $ghost_ids);
    $conn->query("UPDATE images SET status = 'ready', progress = -1 WHERE id IN ($id_list)");
    echo "[!] Marked " . count($ghost_ids) . " missing files as 'File Not Found' (-1).\n";
} else {
    echo "[+] No missing files detected in the current queue.\n";
}

// 2. Normal Recovery: Reset stuck tasks
$conn->query("UPDATE images SET status = 'pending', progress = 0, worker_id = NULL WHERE status = 'processing' AND file_type = 'video'");

$running_tasks = []; 

/**
 * STEP 3: THE MAIN PROCESSING LOOP
 */
while (true) {
    $has_active_task = count($running_tasks) > 0;

    foreach ($running_tasks as $key => $task) {
        $status = proc_get_status($task['process']);

        if (!$status['running']) {
            fclose($task['pipes'][1]);
            fclose($task['pipes'][2]);
            $exitCode = proc_close($task['process']);

            if ($exitCode === 0) {
                if (file_exists($task['input'])) unlink($task['input']);
                $stmt = $conn->prepare("UPDATE images SET status = 'ready', file_name = ?, progress = 100, worker_id = NULL WHERE id = ?");
                $stmt->bind_param("si", $task['outputName'], $task['id']);
                $stmt->execute();
                echo "[+] SUCCESS: ID {$task['id']} finished.\n";
            } else {
                if (file_exists($task['output'])) unlink($task['output']);
                $failStmt = $conn->prepare("UPDATE images SET status = 'ready', progress = -2, worker_id = NULL WHERE id = ?");
                $failStmt->bind_param("i", $task['id']);
                $failStmt->execute();
                echo "[-] ERROR: FFmpeg failed on ID {$task['id']}.\n";
            }
            unset($running_tasks[$key]);
            $has_active_task = false;
        } else {
            $out = fgets($task['pipes'][1]);
            if ($out && preg_match('/out_time_ms=(\d+)/', $out, $match)) {
                $current_ms = (int)$match[1];
                $total_ms = $task['duration'] * 1000000;
                $percent = ($total_ms > 0) ? min(99, round(($current_ms / $total_ms) * 100)) : 0;

                if ($percent > $task['last_percent']) {
                    $running_tasks[$key]['last_percent'] = $percent;
                    $upd = $conn->prepare("UPDATE images SET progress = ? WHERE id = ?");
                    $upd->bind_param("ii", $percent, $task['id']);
                    $upd->execute();
                    $upd->close();
                }
            }
        }
    }

    if (!$has_active_task) {
        $temp_uuid = uniqid('w_');
        $claim = $conn->prepare("UPDATE images SET status = 'processing', worker_id = ? WHERE status = 'pending' AND file_type = 'video' ORDER BY id ASC LIMIT 1");
        $claim->bind_param("s", $temp_uuid);
        $claim->execute();

        if ($claim->affected_rows > 0) {
            $stmt = $conn->prepare("SELECT id, file_name FROM images WHERE worker_id = ? LIMIT 1");
            $stmt->bind_param("s", $temp_uuid);
            $stmt->execute();
            $video = $stmt->get_result()->fetch_assoc();

            if ($video) {
                $id = $video['id'];
                $input = $upload_dir . $video['file_name'];
                
                // Double check before starting FFmpeg
                if (file_exists($input)) {
                    $outputName = "720p_" . time() . "_" . $video['file_name'];
                    $output = $upload_dir . $outputName;

                    $dur_cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($input);
                    $duration = (float)shell_exec($dur_cmd);

                    $descriptorspec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
                    $cmd = "ffmpeg -y -i " . escapeshellarg($input) . " -vf \"scale=-1:720\" -c:v libx264 -crf 26 -preset veryfast -movflags +faststart -progress pipe:1 " . escapeshellarg($output);
                    
                    $process = proc_open($cmd, $descriptorspec, $pipes);

                    if (is_resource($process)) {
                        stream_set_blocking($pipes[1], false);
                        $running_tasks[] = [
                            'id' => $id, 'process' => $process, 'pipes' => $pipes,
                            'duration' => $duration, 'last_percent' => -1,
                            'input' => $input, 'output' => $output, 'outputName' => $outputName
                        ];
                        echo "[!] STARTED ID $id.\n";
                    }
                } else {
                    // This handles files deleted while the worker was already running
                    $conn->query("UPDATE images SET status = 'ready', progress = -1 WHERE id = $id");
                    echo "[!] ID $id deleted mid-run. Skipping.\n";
                }
            }
        } else {
            sleep(10); 
        }
    }

    if ($has_active_task) {
        usleep(1000000); 
    } else {
        if (!$conn->ping()) { require 'db.php'; }
        sleep(2);
    }
}