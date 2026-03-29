<?php
require 'db.php';

echo "[*] Video Worker Started. Checking for crashed tasks...\n";

// RECOVERY: On startup, reset 'processing' back to 'pending' 
// This handles the case where the power cut mid-compression.
$conn->query("UPDATE images SET status = 'pending' WHERE status = 'processing' AND file_type = 'video'");

while (true) {
    // Atomic lock: grab one pending video
    $conn->query("UPDATE images SET status = 'processing' WHERE status = 'pending' AND file_type = 'video' LIMIT 1");
    
    if ($conn->affected_rows > 0) {
        $stmt = $conn->prepare("SELECT id, file_name FROM images WHERE status = 'processing' LIMIT 1");
        $stmt->execute();
        $video = $stmt->get_result()->fetch_assoc();

        if ($video) {
            $id = $video['id'];
            $input = "/var/www/html/uploads/" . $video['file_name'];
            $outputName = "compressed_" . time() . "_" . $video['file_name'];
            $output = "/var/www/html/uploads/" . $outputName;

            echo "[!] Compressing ID $id: {$video['file_name']}...\n";

            // FFmpeg command optimized for compatibility
            // libx264 ensures it plays on Chrome/Safari/Phones
            $cmd = "ffmpeg -y -i '$input' -c:v libx264 -crf 24 -preset medium -c:a aac -b:a 128k '$output' 2>&1";
            
            exec($cmd, $log, $result);

            if ($result === 0) {
                // Success: Delete original to save space on satahdd1, update DB
                unlink($input); 
                $stmt = $conn->prepare("UPDATE images SET status = 'ready', file_name = ? WHERE id = ?");
                $stmt->bind_param("si", $outputName, $id);
                $stmt->execute();
                echo "[+] Done: $outputName\n";
            } else {
                $conn->query("UPDATE images SET status = 'failed' WHERE id = $id");
                echo "[-] FFmpeg Error on ID $id\n";
            }
        }
    }
    sleep(10); // Check every 10 seconds
}