<?php
include 'session.php';
require 'db.php';
require 'imageHash.php'; // Include image hash helper

if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to update a gallery.");
}

$gallery_id = $_GET['id']; // Get the gallery ID from the URL

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];

    // Update the gallery details
    $stmt = $conn->prepare("UPDATE galleries SET title = ?, description = ? WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ssii", $title, $description, $gallery_id, $_SESSION['user_id']);
    $stmt->execute();

    // Initialize a counter for the number of files added
    $files_added = 0;

    // Handle media uploads
    foreach ($_FILES['media']['name'] as $key => $file_name) {
        $file_tmp = $_FILES['media']['tmp_name'][$key];
        $file_type = mime_content_type($file_tmp);

        // Check if the file is an image or video
        $media_type = (strpos($file_type, 'image') !== false) ? 'image' : 'video';

        // Generate a unique file name using uniqid and timestamp
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION); // Get the file extension
        $unique_file_name = uniqid() . '-' . time() . '.' . $file_ext; // Append timestamp and extension
        $upload_dir = 'uploads/';

        if (move_uploaded_file($file_tmp, $upload_dir . $unique_file_name)) {
            // Insert the uploaded file details into the `images` table
            $imagehash = getImageHash($upload_dir . $unique_file_name);
            $stmt = $conn->prepare("INSERT INTO images (gallery_id, file_name, file_type, imageHash_hamming) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $gallery_id, $unique_file_name, $media_type, $imagehash);
            $stmt->execute();
        } else {
            echo "Failed to upload file: " . $file_name;
        }
    }

    // Generate the message based on the number of files added
    if ($files_added > 0) {
        $msg = "$files_added file(s) added.";
    } else {
        $msg = "No files added, only title and description updated.";
    }

    echo "Gallery updated successfully!";
    header("Location: display_gallery.php?id=$gallery_id&msg=true&msg_content=$msg");
}
