<?php
include 'session.php';
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to delete an image.");
}

$image_id = $_GET['id']; // Image ID from URL

// Fetch the image details
$stmt = $conn->prepare("SELECT * FROM images WHERE id = ?");
$stmt->bind_param("i", $image_id);
$stmt->execute();
$image = $stmt->get_result()->fetch_assoc();

if (!$image) {
    die("Image not found.");
}

// Delete the image file from the server
$file_path = 'uploads/' . $image['file_name'];
if (file_exists($file_path)) {
    unlink($file_path);
}

// Remove the image record from the database
$stmt = $conn->prepare("DELETE FROM images WHERE id = ?");
$stmt->bind_param("i", $image_id);
$stmt->execute();

echo "Image deleted successfully!";
header("Location: display_gallery.php?id=" . $image['gallery_id']); // Redirect to gallery
?>
