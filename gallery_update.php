<?php
include 'session.php';
require 'db.php';
require 'imageHash.php'; 

if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to update a gallery.");
}

$gallery_id = $_GET['id']; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];

    $stmt = $conn->prepare("UPDATE galleries SET title = ?, description = ? WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ssii", $title, $description, $gallery_id, $_SESSION['user_id']);
    $stmt->execute();

    $files_added = 0;
    $upload_dir = 'uploads/';

    foreach ($_FILES['media']['name'] as $key => $file_name) {
        $file_tmp = $_FILES['media']['tmp_name'][$key];
        $file_type = mime_content_type($file_tmp);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $media_type = (strpos($file_type, 'image') !== false) ? 'image' : 'video';
        
        // Generate a base unique name
        $unique_base = uniqid() . '-' . time();
        $final_file_name = $unique_base . '.' . $file_ext;
        $target_path = $upload_dir . $final_file_name;

        if (move_uploaded_file($file_tmp, $target_path)) {
            
            // --- WEBP TO JPG CONVERSION LOGIC ---
            if ($file_type === 'image/webp' || $file_ext === 'webp') {
                $jpg_file_name = $unique_base . '.jpg';
                $jpg_path = $upload_dir . $jpg_file_name;

                // Load the WebP image
                $image = imagecreatefromwebp($target_path);
                if ($image) {
                    // Create a blank true color image (to handle potential transparency issues)
                    $bg = imagecreatetruecolor(imagesx($image), imagesy($image));
                    $white = imagecolorallocate($bg, 255, 255, 255);
                    imagefill($bg, 0, 0, $white);
                    imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                    
                    // Save as JPG (Quality: 80)
                    if (imagejpeg($bg, $jpg_path, 80)) {
                        // Clean up: delete the original .webp and update variables
                        unlink($target_path); 
                        $final_file_name = $jpg_file_name;
                        $target_path = $jpg_path;
                    }
                    
                    imagedestroy($image);
                    imagedestroy($bg);
                }
            }
            // --------------------------------------

            // Handle Image Hashing
            $imagehash = ($media_type == 'image') ? getImageHash($target_path) : '';

            // Database Insertion
            $stmt = $conn->prepare("INSERT INTO images (gallery_id, file_name, file_type, imageHash_hamming) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $gallery_id, $final_file_name, $media_type, $imagehash);
            
            if($stmt->execute()) {
                $files_added++;
            }
        } else {
            echo "Failed to upload file: " . $file_name;
        }
    }

    $msg = ($files_added > 0) ? "$files_added file(s) added." : "No files added, only text updated.";
    header("Location: display_gallery.php?id=$gallery_id&msg=true&msg_content=" . urlencode($msg));
    exit();
}