<?php
include 'session.php';
require 'db.php';
require 'imageHash.php';

if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to update a gallery.");
}

$gallery_id = (int)$_GET['id'];
$errors = []; // Collect all issues here

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Check if the POST request was truncated (happens if post_max_size is exceeded)
    if (empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $errors[] = "The total size of your upload exceeds the server limit (post_max_size).";
    }

    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $input_src = $_GET['input_src'] ?? 'gallery_form';

    // Update gallery text info
    if ($input_src !== 'image_from_video' && empty($errors)) {
        $stmt = $conn->prepare("UPDATE galleries SET title = ?, description = ? WHERE id = ? AND created_by = ?");
        $stmt->bind_param("ssii", $title, $description, $gallery_id, $_SESSION['user_id']);
        if (!$stmt->execute()) {
            $errors[] = "Database failed to update gallery info: " . $stmt->error;
        }
        $stmt->close();
    }

    $files_added = 0;
    $upload_dir = 'uploads/';

    // 2. Process Files with Error Handling
    if (!empty($_FILES['media']['name'][0]) && empty($errors)) {
        foreach ($_FILES['media']['tmp_name'] as $key => $file_tmp) {
            
            // Check for PHP Upload Errors
            $php_error = $_FILES['media']['error'][$key];
            if ($php_error !== UPLOAD_ERR_OK) {
                $file_name = $_FILES['media']['name'][$key];
                switch ($php_error) {
                    case UPLOAD_ERR_INI_SIZE: $errors[] = "[$file_name] exceeds upload_max_filesize."; break;
                    case UPLOAD_ERR_FORM_SIZE: $errors[] = "[$file_name] exceeds form limit."; break;
                    case UPLOAD_ERR_PARTIAL: $errors[] = "[$file_name] was only partially uploaded."; break;
                    case UPLOAD_ERR_NO_TMP_DIR: $errors[] = "Server missing temporary folder."; break;
                    default: $errors[] = "[$file_name] failed with error code: $php_error";
                }
                continue; 
            }

            $original_name = $_FILES['media']['name'][$key];
            $file_type = mime_content_type($file_tmp);
            $is_image = (strpos($file_type, 'image') !== false);
            $media_type = $is_image ? 'image' : 'video';
            
            $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $unique_base = uniqid() . '-' . time();
            $final_file_name = $unique_base . '.' . $file_ext;
            $target_path = $upload_dir . $final_file_name;

            if (move_uploaded_file($file_tmp, $target_path)) {
                // --- WEBP CONVERSION ---
                if ($file_type === 'image/webp') {
                    $image = @imagecreatefromwebp($target_path);
                    if ($image) {
                        $jpg_file_name = $unique_base . '.jpg';
                        $jpg_path = $upload_dir . $jpg_file_name;
                        $bg = imagecreatetruecolor(imagesx($image), imagesy($image));
                        $white = imagecolorallocate($bg, 255, 255, 255);
                        imagefill($bg, 0, 0, $white);
                        imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                        if (imagejpeg($bg, $jpg_path, 80)) {
                            unlink($target_path);
                            $final_file_name = $jpg_file_name;
                            $target_path = $jpg_path;
                        }
                        imagedestroy($image);
                        imagedestroy($bg);
                    }
                }

                $imagehash = ($media_type === 'image') ? getImageHash($target_path) : '';

                $stmt = $conn->prepare("INSERT INTO images (gallery_id, file_name, file_type, imageHash_hamming) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $gallery_id, $final_file_name, $media_type, $imagehash);
                if ($stmt->execute()) {
                    $files_added++;
                } else {
                    $errors[] = "DB Error on [$original_name]: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = "Failed to move [$original_name] to uploads directory. Check folder permissions.";
            }
        }
    }

    // 3. Final Feedback Routing
    if (!empty($errors)) {
        // Redirect with errors
        $error_msg = implode(" | ", $errors);
        header("Location: display_gallery.php?id=$gallery_id&status=error&msg_content=" . urlencode($error_msg));
    } else {
        $msg = ($files_added > 0) ? "$files_added file(s) added successfully." : "Gallery updated.";
        header("Location: display_gallery.php?id=$gallery_id&status=success&msg_content=" . urlencode($msg));
    }
    exit();
}