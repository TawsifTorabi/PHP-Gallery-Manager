<?php
include 'session.php';
require 'db.php'; // Include your database connection file

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to delete a gallery.");
}

$user_id = $_SESSION['user_id'];

// Fetch galleries created by the logged-in user
$stmt = $conn->prepare("SELECT id, title FROM galleries WHERE created_by = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$galleries = $result->fetch_all(MYSQLI_ASSOC);

// Handle gallery deletion
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $gallery_id = $_GET['gallery_id'];

    // Ensure the gallery belongs to the user
    $stmt = $conn->prepare("SELECT id FROM galleries WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $gallery_id, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Fetch and delete all media associated with the gallery
        $stmt = $conn->prepare("SELECT file_name FROM images WHERE gallery_id = ?");
        $stmt->bind_param("i", $gallery_id);
        $stmt->execute();
        $media_result = $stmt->get_result();

        while ($media = $media_result->fetch_assoc()) {
            // Delete the image file from the server
            $file_path = 'uploads/' . $media['file_name'];
            if (file_exists($file_path)) {
                unlink($file_path); // Delete the file from the server
            }

            // Check and delete the thumbnail if it exists
            $thumbnail_path = 'thumbnails/' . pathinfo($media['file_name'], PATHINFO_FILENAME) . '.png';
            if (file_exists($thumbnail_path)) {
                unlink($thumbnail_path); // Delete the thumbnail
            }
        }

        // Delete media records from the images table
        $stmt = $conn->prepare("DELETE FROM images WHERE gallery_id = ?");
        $stmt->bind_param("i", $gallery_id);
        $stmt->execute();

        // Delete the gallery itself
        $stmt = $conn->prepare("DELETE FROM galleries WHERE id = ?");
        $stmt->bind_param("i", $gallery_id);
        $stmt->execute();

        echo "Gallery deleted successfully!";
        header("Location: dashboard.php"); // Redirect to dashboard after deletion
        exit;
    } else {
        echo "Failed to delete gallery or you do not have permission.";
    }
}
?>
