<?php
include 'session.php';
include 'db.php';

header('Content-Type: application/json'); // Set content type to JSON

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $media1 = intval($_POST['media_1']);
    $media2 = intval($_POST['media_2']);
    $galleryId = intval($_POST['gallery_id']);
    $matched = intval($_POST['matched']);

    // Response structure
    $response = [
        'status' => 'error',
        'message' => 'An unexpected error occurred.'
    ];

    // Check if the pair already exists in the table
    $query = "SELECT id FROM image_duplicate_flag WHERE media_1 = ? AND media_2 = ? AND gallery_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iii', $media1, $media2, $galleryId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update existing flag
        $row = $result->fetch_assoc();
        $updateQuery = "UPDATE image_duplicate_flag SET matched = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param('ii', $matched, $row['id']);
        if ($updateStmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Flag updated successfully.';
        } else {
            $response['message'] = 'Failed to update the flag.';
        }
    } else {
        // Insert new flag
        $insertQuery = "INSERT INTO image_duplicate_flag (media_1, media_2, gallery_id, matched) VALUES (?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param('iiii', $media1, $media2, $galleryId, $matched);
        if ($insertStmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Flag inserted successfully.';
        } else {
            $response['message'] = 'Failed to insert the flag.';
        }
    }

    echo json_encode($response);
} else {
    // Return an error response for non-POST requests
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method.'
    ]);
}
