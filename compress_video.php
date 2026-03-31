<?php
require 'db.php';

// Check if image_id is passed via CLI
if (!isset($argv[1])) {
    die("[-] Error: No image ID provided.\n");
}

$image_id = (int)$argv[1];

// 1. Get file info
$res = $conn->query("SELECT file_name FROM images WHERE id = $image_id");
if (!$res || $res->num_rows === 0) {
    die("[-] Error: ID $image_id not found in database.\n");
}

$video = $res->fetch_assoc();
$input_file = 'uploads/' . $video['file_name'];

if (!file_exists($input_file)) {
    $conn->query("UPDATE images SET progress = -1, status = 'ready' WHERE id = $image_id");
    die("[-] Error: Physical file not found.\n");
}

// 2. Get total duration for percentage math
$duration_cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($input_file);
$total_duration = (float)shell_exec($duration_cmd);

// Generate unique output name
$output_name = 'cmprsd_' . bin2hex(random_bytes(4)) . '-' . time() . '.mp4';
$output_file = 'uploads/' . $output_name;

// 3. Optimized FFmpeg Command
// -vf scale/pad: Fixes "width/height not divisible by 2" which causes i3/FFmpeg hangs
// -movflags +faststart: Allows video to play while downloading (B2B requirement)
$cmd = "ffmpeg -y -i " . escapeshellarg($input_file) . 
       " -vf \"scale='if(gt(iw,ih),min(1920,iw),-2)':'if(gt(iw,ih),-2,min(1080,ih))',pad=ceil(iw/2)*2:ceil(ih/2)*2\" " .
       " -vcodec libx264 -crf 32 -preset faster -c:a aac -b:a 128k -movflags +faststart -progress pipe:1 " . 
       escapeshellarg($output_file);

$descriptorspec = array(
   0 => array("pipe", "r"), 
   1 => array("pipe", "w"), // Progress
   2 => array("pipe", "w")  // Error logs
);

echo "[*] Compressing ID $image_id...\n";
$process = proc_open($cmd, $descriptorspec, $pipes);

if (is_resource($process)) {
    // Set non-blocking to prevent the script from hanging on SATA disk latency
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $last_percent = -1;
    
    while (true) {
        $status = proc_get_status($process);
        if (!$status['running']) break;

        $progress_line = fgets($pipes[1]);
        if ($progress_line && preg_match('/out_time_ms=(\d+)/', $progress_line, $match)) {
            $current_time_seconds = $match[1] / 1000000;
            $percent = ($total_duration > 0) ? min(99, round(($current_time_seconds / $total_duration) * 100)) : 0;
            
            // Only update DB if percentage actually moved to save DB IO
            if ($percent > $last_percent) {
                $last_percent = $percent;
                $conn->query("UPDATE images SET progress = $percent, status = 'processing' WHERE id = $image_id");
            }
        }
        usleep(200000); // 0.2s poll to save CPU
    }

    $error_output = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $return_value = proc_close($process);
}

// 4. Finalize
if (file_exists($output_file) && filesize($output_file) > 0) {
    if (file_exists($input_file)) unlink($input_file);
    
    $stmt = $conn->prepare("UPDATE images SET file_name = ?, status = 'ready', progress = 100 WHERE id = ?");
    $stmt->bind_param("si", $output_name, $image_id);
    $stmt->execute();
    echo "[+] Done: $output_name\n";
} else {
    // If output is 0 bytes or doesn't exist, it failed
    $conn->query("UPDATE images SET status = 'ready', progress = -2 WHERE id = $image_id");
    file_put_contents('ffmpeg_manual_errors.log', "ID $image_id failed: " . $error_output . PHP_EOL, FILE_APPEND);
    echo "[-] Compression failed for ID $image_id. Log updated.\n";
}