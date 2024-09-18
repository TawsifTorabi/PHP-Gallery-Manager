<?php
session_start();
require 'db.php';

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

    // Handle additional media uploads
    if (!empty($_FILES['media']['name'][0])) {
        foreach ($_FILES['media']['name'] as $key => $file_name) {
            $file_tmp = $_FILES['media']['tmp_name'][$key];
            $file_type = mime_content_type($file_tmp);

            $media_type = (strpos($file_type, 'image') !== false) ? 'image' : 'video';
            $unique_file_name = uniqid() . '-' . basename($file_name);
            $upload_dir = 'uploads/';

            if (move_uploaded_file($file_tmp, $upload_dir . $unique_file_name)) {
                $stmt = $conn->prepare("INSERT INTO images (gallery_id, file_name, file_type) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $gallery_id, $unique_file_name, $media_type);
                $stmt->execute();
            }
        }
    }

    echo "Gallery updated successfully!";
    header("Location: display_gallery.php");
}
?>
