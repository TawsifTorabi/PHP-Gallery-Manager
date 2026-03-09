<?php
// proxy.php
$url = $_GET['url'] ?? '';
if (filter_var($url, FILTER_VALIDATE_URL)) {
    $content = @file_get_contents($url);
    if ($content) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        header("Content-Type: " . $finfo->buffer($content));
        echo $content;
        exit;
    }
}
header("HTTP/1.0 404 Not Found");
?>