<?php
include 'session.php';
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$image_id = $_GET['image_id'] ?? null;

if (!$image_id) {
    echo json_encode(['status' => 'error', 'message' => 'ID required.']);
    exit;
}

// 1. Fetch details
$stmt = $conn->prepare("SELECT file_name, gallery_id, status FROM images WHERE id = ?");
$stmt->bind_param("i", $image_id);
$stmt->execute();
$image = $stmt->get_result()->fetch_assoc();

if (!$image) {
    echo json_encode(['status' => 'error', 'message' => 'Record not found.']);
    exit;
}

// 2. SAFETY: If the worker is currently compressing this, don't delete!
if ($image['status'] === 'processing') {
    echo json_encode(['status' => 'error', 'message' => 'Video is currently being compressed. Please wait.']);
    exit;
}

// 3. Define ABSOLUTE path (Best for Docker)
$file_path = __DIR__ . '/uploads/' . $image['file_name'];

// 4. Delete file if it exists
if (!empty($image['file_name']) && file_exists($file_path)) {
    // Attempt deletion
    if (!unlink($file_path)) {
        // If it fails, it's likely a permission issue or a lock
        error_log("Failed to unlink: " . $file_path); 
        // We continue anyway so the DB doesn't get out of sync with the disk
    }
}

// 5. Remove from DB
$stmt = $conn->prepare("DELETE FROM images WHERE id = ?");
$stmt->bind_param("i", $image_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode([
        'status' => 'success', 
        'message' => 'Deleted successfully.', 
        'gallery_id' => $image['gallery_id']
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'DB deletion failed.']);
}
exit;