<?php
require 'db.php';
header('Content-Type: application/json');

$video_id = $_GET['id']; // Pass the ID of the video the user just uploaded

$stmt = $conn->prepare("SELECT status, progress, id FROM images WHERE id = ?");
$stmt->bind_param("i", $video_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    echo json_encode(['error' => 'Not found']);
    exit;
}

$response = [
    'status' => $result['status'],
    'progress' => $result['progress'],
    'queue_pos' => 0
];

if ($result['status'] === 'pending') {
    // Calculate how many pending videos are ahead of this one
    $q_stmt = $conn->prepare("SELECT COUNT(*) as pos FROM images WHERE status = 'pending' AND id < ? AND file_type = 'video'");
    $q_stmt->bind_param("i", $video_id);
    $q_stmt->execute();
    $q_res = $q_stmt->get_result()->fetch_assoc();
    
    // Position 0 means "Next up", Position 1 means "1 person ahead", etc.
    $response['queue_pos'] = $q_res['pos'] + 1; 
}

echo json_encode($response);