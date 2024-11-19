<?php
include 'session.php';
require 'db.php';

$sourceGalleries = $_POST['source_galleries'] ?? [];
$targetGallery = $_POST['target_gallery'] ?? null;

if (empty($sourceGalleries) || !$targetGallery) {
    die(json_encode(["error" => "Invalid input."]));
}

// Begin transaction
$conn->begin_transaction();
try {
    foreach ($sourceGalleries as $galleryId) {
        // Update images in the source galleries to point to the target gallery
        $query = "UPDATE images SET gallery_id = ? WHERE gallery_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $targetGallery, $galleryId);
        $stmt->execute();

        // Optionally delete the source gallery
        $deleteQuery = "DELETE FROM galleries WHERE id = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param("i", $galleryId);
        $deleteStmt->execute();
    }

    $conn->commit();
    echo json_encode(["success" => "Galleries merged successfully."]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["error" => "Failed to merge galleries: " . $e->getMessage()]);
}
?>
