<?php
session_start();
require 'db.php';
require 'imageHash.php'; // Ensure this file exists for your Hamming logic

// 1. Critical Settings for Large Files
set_time_limit(600); // 10 minutes for merging 1.7GB+
ini_set('memory_limit', '512M');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

// 2. Handle Meta-Only Update (Title/Description)
if (isset($_POST['is_meta_only']) && $_POST['is_meta_only'] === 'true') {
    $gallery_id = (int)$_POST['gallery_id'];
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';

    $stmt = $conn->prepare("UPDATE galleries SET title = ?, description = ? WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ssii", $title, $description, $gallery_id, $_SESSION['user_id']);
    $stmt->execute();
    echo json_encode(['status' => 'meta_updated']);
    exit;
}

// 3. Handle File Chunking
$gallery_id = (int)$_POST['gallery_id'];
$identifier = preg_replace('/[^A-Za-z0-9]/', '', $_POST['identifier']); // Secure ID
$chunk_index = (int)$_POST['chunk_index'];
$total_chunks = (int)$_POST['total_chunks'];
$original_name = $_POST['filename'];

$temp_dir = __DIR__ . '/temp_chunks/';
if (!file_exists($temp_dir)) mkdir($temp_dir, 0777, true);

$temp_file = $temp_dir . $identifier . '.part';

// Append current chunk to the temp file
if (isset($_FILES['file_chunk'])) {
    $input = fopen($_FILES['file_chunk']['tmp_name'], 'rb');
    $output = fopen($temp_file, 'ab'); // 'ab' for Append Binary
    stream_copy_to_stream($input, $output);
    fclose($input);
    fclose($output);
}

// 4. Finalize File (When last chunk arrives)
if ($chunk_index === $total_chunks - 1) {
    $upload_dir = 'uploads/';
    $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $unique_base = uniqid() . '-' . time();
    $final_file_name = $unique_base . '.' . $file_ext;
    $target_path = $upload_dir . $final_file_name;

    if (rename($temp_file, $target_path)) {
        $file_type = mime_content_type($target_path);

        // --- WebP SUPPORT START ---
        // If it's a WebP, convert it to JPG immediately because GD doesn't support it
        if ($file_ext === 'webp' || $file_type === 'image/webp') {
            $jpg_name = $unique_base . '.jpg';
            $jpg_path = $upload_dir . $jpg_name;

            // ImageMagick convert (using -flatten to handle transparency/alpha channel)
            $convert_cmd = "convert " . escapeshellarg($target_path) . " -flatten " . escapeshellarg($jpg_path);
            shell_exec($convert_cmd);

            if (file_exists($jpg_path)) {
                unlink($target_path); // Delete the original .webp
                $final_file_name = $jpg_name;
                $target_path = $jpg_path;
                $file_type = 'image/jpeg';
            }
        }
        // --- WebP SUPPORT END ---

        $media_type = (strpos($file_type, 'image') !== false) ? 'image' : 'video';

        // Optional: Trigger your existing WebP to JPG conversion here if needed

        $status = ($media_type === 'video') ? 'pending' : 'ready';

        $imagehash = ($media_type === 'image') ? getImageHash($target_path) : '';
        $dimension = null;

        if ($media_type === 'video') {
            $cmd = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 " . escapeshellarg($target_path);
            $dimension = trim(shell_exec($cmd));
        } else {
            $size = @getimagesize($target_path);
            if ($size) {
                $dimension = $size[0] . "x" . $size[1];
            }
        }

        // Database Insert
        $stmt = $conn->prepare("INSERT INTO images (gallery_id, file_name, dimension, file_type, imageHash_hamming, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $gallery_id, $final_file_name, $dimension, $media_type, $imagehash, $status);

        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            if ($media_type === 'video') {
                // Trigger background FFmpeg compression
                $bg_cmd = "php " . __DIR__ . "/compress_video.php $new_id > /dev/null 2>&1 &";

                /**
                 * Note: In the current implementation of worker.php, we are designed to run one ffmpeg process at a time and it waits 
                 * for that process to finish before claiming the next task.
                 * This means that we won't have multiple concurrent ffmpeg processes that could cause CPU spikes or hangs on SATA disks, 
                 * so we can safely use shell_exec to run the compression script in the background without worrying about non-blocking issues 
                 * in this specific context.
                 * If we were to modify the worker to allow multiple concurrent ffmpeg processes in the future, 
                 * we would need to revisit this decision and potentially implement non-blocking I/O to prevent hangs. 
                 * For now, using shell_exec with an appended '&' allows us to run the compression script in the background without blocking the response, 
                 * which is suitable for our current worker design.
                 */

                // shell_exec($bg_cmd); // This will run the compression script in the background without blocking the response

            }
            echo json_encode(['status' => 'complete', 'file_id' => $new_id]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to finalize file.']);
    }
} else {
    echo json_encode(['status' => 'chunk_accepted', 'next' => $chunk_index + 1]);
}
