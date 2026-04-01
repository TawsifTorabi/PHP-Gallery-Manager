<?php
// Configuration
$upload_dir = 'uploads/';
$cache_dir  = 'cache/';

// Create cache directory if it doesn't exist
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

// 1. Basic Validation
if (!isset($_GET['file']) || empty($_GET['file'])) {
    header('HTTP/1.1 400 Bad Request');
    die('No image file specified.');
}

$file_name = basename($_GET['file']);
$file_path = $upload_dir . $file_name;

if (!file_exists($file_path)) {
    header('HTTP/1.1 404 Not Found');
    die('Image not found.');
}

// 2. Validate Width
$width = 500;
if (isset($_GET['w']) && filter_var($_GET['w'], FILTER_VALIDATE_INT)) {
    $requested_width = intval($_GET['w']);
    if ($requested_width < 2000 && $requested_width > 0) {
        $width = $requested_width;
    }
}

// 3. CACHING LOGIC
// Create a unique hash for this specific file and width
$image_type  = mime_content_type($file_path);
$extension   = pathinfo($file_path, PATHINFO_EXTENSION);
$cache_file  = $cache_dir . md5($file_name . $width) . '.' . $extension;

// Check if a cached version exists and is newer than the source file
if (file_exists($cache_file) && filemtime($cache_file) > filemtime($file_path)) {
    header('Content-Type: ' . $image_type);
    header('Cache-Control: public, max-age=604800');
    header('X-Cache: HIT'); // Useful for debugging
    readfile($cache_file);
    exit;
}

// 4. RESIZING LOGIC (Only runs if cache MISS)
list($original_width, $original_height) = getimagesize($file_path);
$aspect_ratio   = $original_height / $original_width;
$desired_height = (int)round($width * $aspect_ratio);

$resized_image = imagecreatetruecolor($width, $desired_height);

// Handle Transparency for PNG/GIF
if ($image_type == 'image/png' || $image_type == 'image/gif') {
    imagealphablending($resized_image, false);
    imagesavealpha($resized_image, true);
}

switch ($image_type) {
    case 'image/jpeg': $original_image = imagecreatefromjpeg($file_path); break;
    case 'image/png':  $original_image = imagecreatefrompng($file_path);  break;
    case 'image/gif':  $original_image = imagecreatefromgif($file_path);  break;
    default:
        header('HTTP/1.1 415 Unsupported Media Type');
        die('Unsupported image type.');
}

imagecopyresampled($resized_image, $original_image, 0, 0, 0, 0, $width, $desired_height, $original_width, $original_height);

// 5. Save to Cache and Output
header('Content-Type: ' . $image_type);
header('Cache-Control: public, max-age=604800');
header('X-Cache: MISS');

switch ($image_type) {
    case 'image/jpeg':
        imagejpeg($resized_image, $cache_file, 85);
        imagejpeg($resized_image, null, 85);
        break;
    case 'image/png':
        imagepng($resized_image, $cache_file, 8);
        imagepng($resized_image, null, 8);
        break;
    case 'image/gif':
        imagegif($resized_image, $cache_file);
        imagegif($resized_image, null);
        break;
}

imagedestroy($resized_image);
imagedestroy($original_image);
?>