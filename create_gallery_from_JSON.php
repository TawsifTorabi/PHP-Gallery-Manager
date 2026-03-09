<?php
include 'session.php';
require 'db.php';
require 'imageHash.php';

// Allow session writing while script runs
session_start(); 
set_time_limit(600);

$jsonData = json_decode(file_get_contents('php://input'), true);
$title = $jsonData['title'] ?? 'Imported Gallery';
$mediaItems = $jsonData['media'] ?? [];
$user_id = $_SESSION['user_id'];

// 1. Initialize Progress Tracking
$_SESSION['progress'] = 0;
$_SESSION['total'] = count($mediaItems);
session_write_close(); // Release lock so other scripts can read progress

$richDescription = "<strong>Source Links:</strong><br>";
$gallery_id = 0;

// 2. Create Gallery Entry
$stmt = $conn->prepare("INSERT INTO galleries (title, description, created_by) VALUES (?, '', ?)");
$stmt->bind_param("si", $title, $user_id);
$stmt->execute();
$gallery_id = $stmt->insert_id;

$upload_dir = 'uploads/';
$linkHtml = "";

foreach ($mediaItems as $index => $item) {
    $url = $item['mediaElem'];
    $type = strtolower($item['mediatype']);

    if ($type === 'url') {
        $linkHtml .= '🔗 <a href="'.htmlspecialchars($url).'">View Source</a><br>';
    } else {
        // Handle Image/Video Download
        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $newFileName = uniqid() . '-' . time() . '.' . $ext;
        
        $content = @file_get_contents($url);
        if ($content) {
            file_put_contents($upload_dir . $newFileName, $content);
            $hash = ($type === 'image') ? getImageHash($upload_dir . $newFileName) : '';

            $stmt = $conn->prepare("INSERT INTO images (gallery_id, file_name, file_type, imageHash_hamming) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $gallery_id, $newFileName, $type, $hash);
            $stmt->execute();
        }
    }

    // 3. Update Progress Session
    session_start();
    $_SESSION['progress'] = $index + 1;
    session_write_close();
}

// Update final description
$stmt = $conn->prepare("UPDATE galleries SET description = ? WHERE id = ?");
$stmt->bind_param("si", $linkHtml, $gallery_id);
$stmt->execute();

echo json_encode(['success' => true, 'gallery_id' => $gallery_id]);