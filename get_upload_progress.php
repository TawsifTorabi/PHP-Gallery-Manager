<?php
require 'db.php';
$ids = $_GET['ids'] ?? '';
if (!$ids) die(json_encode([]));

// We now include file_name to update the UI once ready
$res = $conn->query("SELECT id, progress, status, file_name FROM images WHERE id IN ($ids)");
echo json_encode($res->fetch_all(MYSQLI_ASSOC));