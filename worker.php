<?php
require 'db.php';

// Force output for Docker logs
ob_implicit_flush(true);
while (ob_get_level()) ob_end_clean();

echo "[*] Video Worker Booting Up...\n";

// 1. Wait for Database to be ready
$max_retries = 5;
while ($conn->connect_error && $max_retries > 0) {
    echo "[!] Waiting for DB connection... ($max_retries attempts left)\n";
    sleep(2);
    $max_retries--;
}

if ($conn->connect_error) {
    die("[-] CRITICAL: Could not connect to DB. Recovery failed.\n");
}

// 2. Recovery: Reset any tasks that were interrupted (Power cut / Docker restart)
echo "[*] Checking for crashed/stuck tasks...\n";
$reset_query = "UPDATE images SET status = 'pending', progress = 0, worker_id = NULL WHERE status = 'processing' AND file_type = 'video'";
if ($conn->query($reset_query)) {
    echo "[+] Recovery complete. Reset " . $conn->affected_rows . " tasks.\n";
}

$running_tasks = []; // Max 2 slots

while (true) {

    $q_check = $conn->query("SELECT COUNT(*) as total FROM images WHERE status = 'pending' AND file_type = 'video'");
    $row = $q_check->fetch_assoc();
    if ($row['total'] > 0) {
        echo "[i] Current Backlog: " . $row['total'] . " videos waiting.\n";
    }
    
    // --- PART A: MANAGE RUNNING TASKS ---
    foreach ($running_tasks as $key => $task) {
        $status = proc_get_status($task['process']);

        if (!$status['running']) {
            // Close all pipes and the process
            fclose($task['pipes'][1]);
            fclose($task['pipes'][2]);
            $exitCode = proc_close($task['process']);

            if ($exitCode === 0) {
                if (file_exists($task['input'])) unlink($task['input']);
                $stmt = $conn->prepare("UPDATE images SET status = 'ready', file_name = ?, progress = 100, worker_id = NULL WHERE id = ?");
                $stmt->bind_param("si", $task['outputName'], $task['id']);
                $stmt->execute();
                echo "[+] FINISHED ID {$task['id']}: 100% -> {$task['outputName']}\n";
            } else {
                if (file_exists($task['output'])) unlink($task['output']);
                $conn->query("UPDATE images SET status = 'failed', progress = 0, worker_id = NULL WHERE id = " . $task['id']);
                echo "[-] FAILED ID {$task['id']}: FFmpeg Error (Code $exitCode)\n";
            }
            unset($running_tasks[$key]);
        } else {
            // Read progress from stdout (pipe 1)
            $out = fgets($task['pipes'][1]);
            if ($out && preg_match('/out_time_ms=(\d+)/', $out, $match)) {
                $current_ms = (int)$match[1];
                $total_ms = $task['duration'] * 1000000;
                $percent = ($total_ms > 0) ? min(99, round(($current_ms / $total_ms) * 100)) : 0;

                // Only update DB/Logs if percentage changed to save CPU/IO
                if ($percent > $task['last_percent']) {
                    $running_tasks[$key]['last_percent'] = $percent;
                    $conn->query("UPDATE images SET progress = $percent WHERE id = " . $task['id']);
                    echo "[i] ID {$task['id']} Progress: $percent%\n";
                }
            }
        }
    }

    // --- PART B: CLAIM NEW TASKS (ATOMIC TAGGING) ---
    if (count($running_tasks) < 2) {
        $temp_uuid = uniqid('w_'); // Unique tag for this claim attempt

        // 1. Tag exactly one pending row with our unique ID
        $claim = $conn->prepare("UPDATE images SET status = 'processing', worker_id = ? WHERE status = 'pending' AND file_type = 'video' ORDER BY id ASC LIMIT 1");
        $claim->bind_param("s", $temp_uuid);
        $claim->execute();

        if ($claim->affected_rows > 0) {
            // 2. Fetch the specific row we just tagged
            $stmt = $conn->prepare("SELECT id, file_name FROM images WHERE worker_id = ? LIMIT 1");
            $stmt->bind_param("s", $temp_uuid);
            $stmt->execute();
            $video = $stmt->get_result()->fetch_assoc();

            if ($video) {
                $id = $video['id'];
                $input = "/var/www/html/uploads/" . $video['file_name'];
                $outputName = "compressed_" . time() . "_" . $video['file_name'];
                $output = "/var/www/html/uploads/" . $outputName;

                if (file_exists($input)) {
                    // Get duration using ffprobe
                    $dur_cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($input);
                    $duration = (float)shell_exec($dur_cmd);

                    $descriptorspec = [
                        0 => ["pipe", "r"],
                        1 => ["pipe", "w"], // stdout for -progress
                        2 => ["pipe", "w"]  // stderr
                    ];

                    $cmd = "ffmpeg -y -i " . escapeshellarg($input) . " -progress pipe:1 -c:v libx264 -crf 24 -preset medium -c:a aac -b:a 128k " . escapeshellarg($output);
                    $process = proc_open($cmd, $descriptorspec, $pipes);

                    if (is_resource($process)) {
                        stream_set_blocking($pipes[1], false);
                        $running_tasks[] = [
                            'id' => $id,
                            'process' => $process,
                            'pipes' => $pipes,
                            'duration' => $duration,
                            'last_percent' => -1,
                            'input' => $input,
                            'output' => $output,
                            'outputName' => $outputName
                        ];
                        echo "[!] STARTED ID $id. (Active: " . count($running_tasks) . ")\n";
                    }
                } else {
                    echo "[-] File not found for ID $id. Marking failed.\n";
                    $conn->query("UPDATE images SET status = 'failed', worker_id = NULL WHERE id = $id");
                }
            }
        }
    }

    // --- PART C: HEARTBEAT ---
    if (!$conn->ping()) {
        echo "[!] DB Connection lost. Reconnecting...\n";
        require 'db.php';
    }

    usleep(300000); // 0.3s wait to keep i3 CPU usage for the script itself very low
}
