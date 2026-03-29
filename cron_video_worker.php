<?php
require 'db.php';

// 1. Check if a compression process is already running to avoid overloading CPU
// This works on Linux/Ubuntu servers
$running_processes = shell_exec("pgrep ffmpeg");
if (!empty($running_processes)) {
    die("FFmpeg is already working on a file. Skipping this cycle.");
}

// 2. Find the oldest 'pending' video
$stmt = $conn->prepare("SELECT id, file_name FROM images WHERE file_type = 'video' AND status = 'pending' ORDER BY id ASC LIMIT 1");
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();

if ($video) {
    $video_id = $video['id'];
    $file_path = __DIR__ . "/uploads/" . $video['file_name'];

    // 3. Start compression
    // We use a separate script for compression to keep this watchdog clean
    echo "Recovering Video ID: $video_id\n";
    shell_exec("php " . __DIR__ . "/compress_video.php $video_id > /dev/null 2>&1 &");
}