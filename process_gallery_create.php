<?php
include 'session.php';
require 'db.php';
require 'imageHash.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? 1;

    // --- ACTION 1: Create the Gallery Container ---
    if (isset($_POST['action']) && $_POST['action'] === 'create_gallery') {
        $title = $_POST['title'] ?? 'Untitled Gallery';
        $description = $_POST['description'] ?? '';
        $hero_images = ''; // This can be updated later based on uploaded media
        
        $stmt = $conn->prepare("INSERT INTO galleries (title, description, hero_images, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $title, $description, $hero_images, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'gallery_id' => $stmt->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }

    // --- ACTION 2: Handle Chunked Uploads ---
    if (isset($_FILES['file'])) {
        $gallery_id = (int)$_POST['gallery_id'];
        $fileName = $_POST['fileName'];
        $chunkIndex = (int)$_POST['chunkIndex'];
        $totalChunks = (int)$_POST['totalChunks'];
        
        $temp_dir = 'uploads/temp/' . $gallery_id . '/';
        if (!is_dir($temp_dir)) mkdir($temp_dir, 0777, true);

        $chunkPath = $temp_dir . $fileName . '.part' . $chunkIndex;
        move_uploaded_file($_FILES['file']['tmp_name'], $chunkPath);

        // If this is the last chunk, reassemble the file
        if ($chunkIndex == $totalChunks - 1) {
            $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
            $uniqueName = uniqid() . '-' . time() . '.' . $fileExt;
            $finalPath = 'uploads/' . $uniqueName;

            $outFile = fopen($finalPath, 'wb');
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkFile = $temp_dir . $fileName . '.part' . $i;
                if (file_exists($chunkFile)) {
                    fwrite($outFile, file_get_contents($chunkFile));
                    unlink($chunkFile); // Clean up chunk
                }
            }
            fclose($outFile);

            // Media Type & Hashing
            $mime = mime_content_type($finalPath);
            $media_type = (strpos($mime, 'image') !== false) ? 'image' : 'video';
            $imagehash = ($media_type == 'image') ? getImageHash($finalPath) : '';
            $status = ($media_type == 'video') ? 'processing' : 'ready';

            $stmt = $conn->prepare("INSERT INTO images (gallery_id, file_name, file_type, imageHash_hamming, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $gallery_id, $uniqueName, $media_type, $imagehash, $status);
            
            if ($stmt->execute()) {
                $new_id = $stmt->insert_id;
                if ($media_type === 'video') {
                    $bg_cmd = "php " . __DIR__ . "/compress_video.php $new_id > /dev/null 2>&1 &";
                    shell_exec($bg_cmd);
                }
                echo json_encode(['success' => true, 'status' => 'completed', 'file_id' => $new_id]);
            }
        } else {
            echo json_encode(['success' => true, 'status' => 'chunk_saved']);
        }
        exit;
    }
}