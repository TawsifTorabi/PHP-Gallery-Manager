<?php
// proxy_fetch.php
$targetUrl = $_GET['url'] ?? '';

if (filter_var($targetUrl, FILTER_VALIDATE_URL)) {
    // Initialize cURL to fetch the file
    $ch = curl_init($targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For HTTPS flexibility
    
    $data = curl_exec($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($data !== false) {
        // Set the content type based on the source file
        header("Content-Type: " . $contentType);
        header("Access-Control-Allow-Origin: *");
        echo $data;
        exit;
    }
}

http_response_code(400);
echo "Invalid URL or content.";