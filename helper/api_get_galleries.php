<?php
session_start();
require __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$q = $_GET['q'] ?? '';
$q = "%".$conn->real_escape_string($q)."%";

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT id, title 
    FROM galleries 
    WHERE created_by = ? 
    AND title LIKE ? 
    ORDER BY id DESC 
    LIMIT 10
");

$stmt->bind_param("is", $user_id, $q);
$stmt->execute();

$res = $stmt->get_result();

$data = [];

while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);