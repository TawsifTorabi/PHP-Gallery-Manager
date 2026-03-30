<?php
require 'db.php';
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// 1. Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// 2. Get the JSON payload
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['ids']) || empty($data['ids'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No image IDs provided']);
    exit;
}

$user_id = $_SESSION['user_id'];
$raw_ids = $data['ids'];

// Sanitize IDs for safety
$placeholders = implode(',', array_fill(0, count($raw_ids), '?'));
$types = str_repeat('i', count($raw_ids));

// 3. FETCH FILE NAMES & STATUS BEFORE DELETING DB RECORDS
// We join with galleries to ensure the user actually owns these files
$fetch_query = "SELECT i.id, i.file_name, i.status FROM images i 
                JOIN galleries g ON i.gallery_id = g.id 
                WHERE i.id IN ($placeholders) AND g.created_by = ?";

$stmt = $conn->prepare($fetch_query);
$params = array_merge($raw_ids, [$user_id]);
$stmt->bind_param($types . "i", ...$params);
$stmt->execute();
$result = $stmt->get_result();

$files_to_delete = [];
$ids_to_remove = [];

while ($row = $result->fetch_assoc()) {
    // Check if the worker is currently using this file
    if ($row['status'] === 'processing') {
        continue; // Skip active files to prevent FFmpeg crashes
    }

    $files_to_delete[] = $row['file_name'];
    $ids_to_remove[] = $row['id'];
}

if (empty($ids_to_remove)) {
    echo json_encode(['error' => 'No valid or idle images found to delete.']);
    exit;
}

// 4. DELETE PHYSICAL FILES
foreach ($files_to_delete as $file_name) {
    if (empty($file_name)) continue;
    
    $file_path = __DIR__ . '/uploads/' . $file_name;
    if (file_exists($file_path)) {
        @unlink($file_path); // Silently attempt delete
    }
}

// 5. REMOVE FROM DATABASE
$del_placeholders = implode(',', array_fill(0, count($ids_to_remove), '?'));
$del_types = str_repeat('i', count($ids_to_remove));

$delete_query = "DELETE FROM images WHERE id IN ($del_placeholders)";
$del_stmt = $conn->prepare($delete_query);
$del_stmt->bind_param($del_types, ...$ids_to_remove);

if ($del_stmt->execute()) {
    echo json_encode([
        'success' => 'Images deleted successfully',
        'count' => $del_stmt->affected_rows
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Error deleting records from database']);
}

$stmt->close();
$conn->close();
?>