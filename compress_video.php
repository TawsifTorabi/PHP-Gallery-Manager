<?php
// compress_video.php
require 'db.php';

$image_id = $argv[1];
$upload_dir = 'uploads/';

// Update status to processing
$conn->query("UPDATE images SET status = 'processing' WHERE id = $image_id");

$res = $conn->query("SELECT file_name FROM images WHERE id = $image_id");
$video = $res->fetch_assoc();
$input_file = $upload_dir . $video['file_name'];
$output_file = $upload_dir . 'compressed_' . bin2hex(random_bytes(5)) . '.mp4';

// FFmpeg Command: Libx264 is web-friendly, crf 28 is a good balance of size/quality
$cmd = "ffmpeg -i " . escapeshellarg($input_file) . " -vcodec libx264 -crf 28 -preset faster -acodec aac " . escapeshellarg($output_file) . " 2>&1";

exec($cmd, $output, $return_var);

if ($return_var === 0) {
    // Replace old file with new compressed one
    unlink($input_file);
    $final_name = basename($output_file);
    $conn->query("UPDATE images SET file_name = '$final_name', status = 'ready' WHERE id = $image_id");
} else {
    // If it fails, at least make it 'ready' so it shows up, or log error
    $conn->query("UPDATE images SET status = 'ready' WHERE id = $image_id");
}