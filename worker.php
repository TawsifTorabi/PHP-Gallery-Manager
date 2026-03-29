<?php
require 'db.php';

// Force output to show up in Docker logs immediately
ob_implicit_flush(true);
while (ob_get_level()) ob_end_clean();

echo "[*] Video Worker Started. Checking for crashed tasks...\n";

// RECOVERY: Reset crashed tasks
$conn->query("UPDATE images SET status = 'pending' WHERE status = 'processing' AND file_type = 'video'");

while (true) {
    // 1. Atomic Lock: Claim the oldest pending video
    $conn->query("UPDATE images SET status = 'processing' WHERE status = 'pending' AND file_type = 'video' ORDER BY id ASC LIMIT 1");

    if ($conn->affected_rows > 0) {
        // 2. Fetch the claimed video
        $stmt = $conn->prepare("SELECT id, file_name FROM images WHERE status = 'processing' LIMIT 1");
        $stmt->execute();
        $video = $stmt->get_result()->fetch_assoc();

        if ($video) {
            $id = $video['id'];
            $input = "/var/www/html/uploads/" . $video['file_name'];
            $outputName = "compressed_" . time() . "_" . $video['file_name'];
            $output = "/var/www/html/uploads/" . $outputName;

            // 3. Final check: Does the file actually exist on the HDD?
            if (!file_exists($input)) {
                echo "[-] File not found on disk for ID $id. Marking as failed.\n";
                $conn->query("UPDATE images SET status = 'failed' WHERE id = $id");
                continue; 
            }

            echo "[!] Compressing ID $id: {$video['file_name']}...\n";

            // 4. RUN THE COMMAND (Crucial: This must happen before checking $result)
            $cmd = "ffmpeg -y -i " . escapeshellarg($input) . " -c:v libx264 -crf 24 -preset medium -c:a aac -b:a 128k " . escapeshellarg($output) . " 2>&1";
            exec($cmd, $log, $result);

            // 5. Cleanup and Success/Fail Logic
            if ($result === 0) {
                // Check if user deleted the DB row while FFmpeg was running
                $check = $conn->query("SELECT id FROM images WHERE id = $id");
                if ($check->num_rows === 0) {
                    echo "[-] Record deleted during processing. Cleaning up output.\n";
                    if (file_exists($output)) unlink($output);
                } else {
                    unlink($input); // Delete original
                    $update = $conn->prepare("UPDATE images SET status = 'ready', file_name = ? WHERE id = ?");
                    $update->bind_param("si", $outputName, $id);
                    $update->execute();
                    echo "[+] Success: $outputName\n";
                }
            } else {
                // If FFmpeg failed, delete the partial "junk" file
                if (file_exists($output)) unlink($output);
                $conn->query("UPDATE images SET status = 'failed' WHERE id = $id");
                echo "[-] FFmpeg Error on ID $id. Log: " . end($log) . "\n";
            }
        }
    }
    
    // Check if DB connection is still alive (long-running scripts can time out)
    if (!$conn->ping()) {
        echo "[!] DB Connection lost. Reconnecting...\n";
        require 'db.php'; 
    }

    sleep(5); 
}