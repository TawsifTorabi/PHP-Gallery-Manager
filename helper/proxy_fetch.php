<?php
// Simple proxy to bypass CORS for public videos
$videoUrl = $_GET['url'] ?? '';

if (filter_var($videoUrl, FILTER_VALIDATE_URL)) {
    $headers = get_headers($videoUrl, 1);
    header("Content-Type: " . ($headers['Content-Type'] ?? 'video/mp4'));
    header("Access-Control-Allow-Origin: *"); // Allow your JS to read this
    
    // Stream the file directly to the browser
    readfile($videoUrl);
}