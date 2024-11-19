<?php
include 'session.php';
require 'db.php';

$searchTerm = $_GET['term'] ?? '';

// Search galleries by title or description
$query = "SELECT id, title, `description`, hero_images FROM galleries WHERE id LIKE CONCAT('%', ?, '%') OR title LIKE CONCAT('%', ?, '%') OR `description` LIKE CONCAT('%', ?, '%') ORDER BY title ASC LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$galleries = [];
while ($row = $result->fetch_assoc()) {
    $galleries[] = $row;
}

header('Content-Type: application/json');
echo json_encode($galleries);
?>
