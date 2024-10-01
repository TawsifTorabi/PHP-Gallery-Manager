<?php
include 'session.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $gallery_id = $_POST['gallery_id'];
    $hero_images = isset($_POST['hero_images']) ? $_POST['hero_images'] : [];

    // Convert array of image IDs to a separated string
    $hero_images_str = implode('$%@!', $hero_images);

    // Update the gallery with the selected hero images
    $stmt = $conn->prepare("UPDATE galleries SET hero_images = ? WHERE id = ?");
    $stmt->bind_param("si", $hero_images_str, $gallery_id);

    if ($stmt->execute()) {
        // Redirect back to the gallery display page with a success message
        header("Location: display_gallery.php?id=$gallery_id&msg_content=Hero images updated successfully.");
    } else {
        // Handle error
        echo "Error updating hero images: " . $conn->error;
    }
}
