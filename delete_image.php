<?php
include 'session.php';
require 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to delete an image.']);
    exit;
}

$image_id = $_GET['image_id'] ?? null; // Image ID from URL

if (!$image_id) {
    echo json_encode(['status' => 'error', 'message' => 'Image ID is required.']);
    exit;
}

// Fetch the image details
$stmt = $conn->prepare("SELECT * FROM images WHERE id = ?");
$stmt->bind_param("i", $image_id);
$stmt->execute();
$image = $stmt->get_result()->fetch_assoc();

if (!$image) {
    echo json_encode(['status' => 'error', 'message' => 'Image not found.']);
    exit;
}

// Delete the image file from the server
$file_path = 'uploads/' . $image['file_name'];
if (file_exists($file_path)) {
    if (!unlink($file_path)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete the image file.']);
        exit;
    }
}

// Remove the image record from the database
$stmt = $conn->prepare("DELETE FROM images WHERE id = ?");
$stmt->bind_param("i", $image_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['status' => 'success', 'message' => 'Image deleted successfully.', 'gallery_id' => $image['gallery_id']]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete the image from the database.']);
}

exit;
?>
