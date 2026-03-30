<?php
require 'db.php';

// Ensure the terminal shows updates immediately
ob_implicit_flush(true);
while (ob_get_level()) ob_end_clean();

echo "[*] Launching Final Production Worker (SATA/i3 Optimized)\n";

if ($conn->connect_error) die("[-] Database connection failed.\n");

// --- PHASE 1: DISK & DATABASE SYNC ---
echo "[*] Scanning /uploads/ for physical files...\n";
$upload_dir = "/var/www/html/uploads/";
$physical_files = scandir($upload_dir);
$existing_files = array_flip($physical_files);
echo "[+] Found " . count($physical_files) . " items on disk.\n";

echo "[*] Syncing queue with physical disk...\n";
// Reset any tasks that were stuck in 'processing' from a previous crash
$conn->query("UPDATE images SET status = 'pending', progress = 0, worker_id = NULL WHERE status = 'processing' AND file_type = 'video'");

// Identify and mark "Ghost Files" (In DB but missing from Disk)
$res = $conn->query("SELECT id, file_name FROM images WHERE status = 'pending' AND file_type = 'video'");
$ghost_ids = [];
while ($row = $res->fetch_assoc()) {
    if (!isset($existing_files[$row['file_name']])) {
        $ghost_ids[] = $row['id'];
    }
}

if (!empty($ghost_ids)) {
    $id_list = implode(',', $ghost_ids);
    $conn->query("UPDATE images SET status = 'ready', progress = -1 WHERE id IN ($id_list)");
    echo "[!] Marked " . count($ghost_ids) . " missing files as 'Not Found' (-1).\n";
} else {
    echo "[+] All pending database entries match physical files.\n";
}

$running_tasks = [];

// --- PHASE 2: MAIN PROCESSING LOOP ---
while (true) {
    $has_active_task = count($running_tasks) > 0;

    // A: Manage Active FFmpeg Task
    foreach ($running_tasks as $key => $task) {
        $status = proc_get_status($task['process']);

        if (!$status['running']) {
            $error_log = stream_get_contents($task['pipes'][2]);
            fclose($task['pipes'][1]);
            fclose($task['pipes'][2]);
            // --- REPLACE THE SUCCESS CHECK IN PART A ---

            $exitCode = proc_close($task['process']);

            // Check if it actually worked despite the -1 code
            $isActuallyDone = ($exitCode === 0 || (file_exists($task['output']) && filesize($task['output']) > 0));

            if ($isActuallyDone) {
                if (file_exists($task['input'])) unlink($task['input']);
                $stmt = $conn->prepare("UPDATE images SET status = 'ready', file_name = ?, progress = 100, worker_id = NULL WHERE id = ?");
                $stmt->bind_param("si", $task['outputName'], $task['id']);
                $stmt->execute();
                echo "[+] SUCCESS: ID {$task['id']} (Code: $exitCode)\n";
            } else {
                // If it truly failed (no file created)
                if (file_exists($task['output'])) unlink($task['output']);
                echo "[-] REAL ERROR ID {$task['id']} (Code $exitCode): " . trim($error_log) . "\n";
                $failStmt = $conn->prepare("UPDATE images SET status = 'ready', progress = -2, worker_id = NULL WHERE id = ?");
                $failStmt->bind_param("i", $task['id']);
                $failStmt->execute();
            }
            unset($running_tasks[$key]);
            $has_active_task = false;
        } else {
            // Update Progress in DB (Silent in Logs)
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

    // B: Claim Next Task
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

                // Final file sanity check
                if (!file_exists($input) || filesize($input) < 1024) {
                    $err_code = file_exists($input) ? -3 : -1;
                    $conn->query("UPDATE images SET status = 'ready', progress = $err_code WHERE id = $id");
                    continue;
                }

                $outputName = "web_" . time() . "_" . $video['file_name'];
                $output = $upload_dir . $outputName;

                $dur_cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($input);
                $duration = (float)shell_exec($dur_cmd);

                $descriptorspec = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];

                // Web-Optimized Command: No scaling (fixed odd-width error), CRF 28 for size, Faststart for web
                $cmd = "ffmpeg -y -i " . escapeshellarg($input) . " -c:v libx264 -crf 28 -preset faster -c:a aac -b:a 128k -movflags +faststart -progress pipe:1 " . escapeshellarg($output);

                $process = proc_open($cmd, $descriptorspec, $pipes);

                if (is_resource($process)) {
                    stream_set_blocking($pipes[1], false);
                    stream_set_blocking($pipes[2], false);
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
                    echo "[*] STARTING: ID $id (" . round(filesize($input) / 1048576, 2) . " MB)\n";
                }
            }
        } else {
            // Queue empty - wait longer
            sleep(10);
        }
    }

    // Protection for i3 CPU/SATA Disk
    usleep($has_active_task ? 1000000 : 500000);
}
