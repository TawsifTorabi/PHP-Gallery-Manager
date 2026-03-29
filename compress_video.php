<?php
require 'db.php';
$image_id = $argv[1];

// 1. Get file info
$res = $conn->query("SELECT file_name FROM images WHERE id = $image_id");
$video = $res->fetch_assoc();
$input_file = 'uploads/' . $video['file_name'];

// 2. Get total duration of video using ffprobe (needed for percentage math)
$duration_cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($input_file);
$total_duration = (float)shell_exec($duration_cmd);

$output_file = 'uploads/c_' . bin2hex(random_bytes(4)) . '.mp4';

// 3. Run FFmpeg and capture progress
// We use '-progress' to send status to a pipe/file
$cmd = "ffmpeg -i " . escapeshellarg($input_file) . " -vcodec libx264 -crf 28 -preset faster -progress pipe:1 " . escapeshellarg($output_file);

$descriptorspec = array(
   0 => array("pipe", "r"), 
   1 => array("pipe", "w"), 
   2 => array("pipe", "w") 
);

$process = proc_open($cmd, $descriptorspec, $pipes);

if (is_resource($process)) {
    while (!feof($pipes[1])) {
        $status = fgets($pipes[1]);
        // Look for 'out_time_ms' in the ffmpeg progress output
        if (preg_match('/out_time_ms=(\d+)/', $status, $match)) {
            $current_time = $match[1] / 1000000; // convert microseconds to seconds
            $percent = min(100, round(($current_time / $total_duration) * 100));
            
            // Update DB with percentage
            $conn->query("UPDATE images SET progress = $percent, status = 'processing' WHERE id = $image_id");
        }
    }
    fclose($pipes[1]);
    $return_value = proc_close($process);
}

// 4. Finalize
if (file_exists($output_file)) {
    unlink($input_file);
    $final_name = basename($output_file);
    $conn->query("UPDATE images SET file_name = '$final_name', status = 'ready', progress = 100 WHERE id = $image_id");
}