<?php
require 'db.php';
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// Get the JSON payload from the AJAX request
$data = json_decode(file_get_contents('php://input'), true);

// Ensure 'ids' is provided in the request
if (!isset($data['ids']) || empty($data['ids'])) {
    http_response_code(400); // Bad request
    echo json_encode(['error' => 'No image IDs provided']);
    exit;
}

// Delete images by ID
$ids = implode(',', array_map('intval', $data['ids']));
$user_id = $_SESSION['user_id'];

// Ensure the images belong to the logged-in user
$query = "DELETE FROM images WHERE id IN ($ids) AND gallery_id IN (SELECT id FROM galleries WHERE created_by = ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
if ($stmt->execute()) {
    echo json_encode(['success' => 'Images deleted successfully']);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Error deleting images']);
}

$stmt->close();
$conn->close();
?>
