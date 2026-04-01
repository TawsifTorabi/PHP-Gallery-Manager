<?php
// Configuration
$upload_dir = 'uploads/';
$cache_dir  = 'cache/';

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
    if ($requested_width < 2500 && $requested_width > 0) {
        $width = $requested_width;
    }
}

// 3. CACHING LOGIC
$image_type = mime_content_type($file_path);
$extension  = pathinfo($file_path, PATHINFO_EXTENSION);
// Unique cache key based on filename and width
$cache_file = $cache_dir . md5($file_name . $width) . '.' . $extension;

if (file_exists($cache_file) && filemtime($cache_file) > filemtime($file_path)) {
    header('Content-Type: ' . $image_type);
    header('Cache-Control: public, max-age=604800');
    header('X-Cache: HIT');
    readfile($cache_file);
    exit;
}

// 4. RESIZING LOGIC
header('Content-Type: ' . $image_type);
header('Cache-Control: public, max-age=604800');
header('X-Cache: MISS');

// List of formats GD CANNOT handle in your current setup
$modern_formats = ['image/webp', 'image/heic', 'image/heif'];

if (in_array($image_type, $modern_formats)) {
    /**
     * Use ImageMagick for WebP/HEIC
     * -thumbnail: Faster than -resize (strips metadata)
     * -auto-orient: Fixes iPhone HEIC rotation
     */
    $cmd = "convert " . escapeshellarg($file_path) . " -auto-orient -thumbnail " . escapeshellarg($width) . " " . escapeshellarg($cache_file);
    shell_exec($cmd);
    
    if (file_exists($cache_file)) {
        readfile($cache_file);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        die('Failed to process modern image format.');
    }
} else {
    // FALLBACK TO GD for JPEG, PNG, GIF
    list($original_width, $original_height) = getimagesize($file_path);
    if (!$original_width) die('Invalid image.');
    
    $aspect_ratio   = $original_height / $original_width;
    $desired_height = (int)round($width * $aspect_ratio);
    $resized_image  = imagecreatetruecolor($width, $desired_height);

    if ($image_type == 'image/png' || $image_type == 'image/gif') {
        imagealphablending($resized_image, false);
        imagesavealpha($resized_image, true);
    }

    switch ($image_type) {
        case 'image/jpeg': $original_image = @imagecreatefromjpeg($file_path); break;
        case 'image/png':  $original_image = @imagecreatefrompng($file_path);  break;
        case 'image/gif':  $original_image = @imagecreatefromgif($file_path);  break;
        default: die('Unsupported type.');
    }

    if ($original_image) {
        imagecopyresampled($resized_image, $original_image, 0, 0, 0, 0, $width, $desired_height, $original_width, $original_height);
        
        // Save to cache and output simultaneously
        switch ($image_type) {
            case 'image/jpeg': imagejpeg($resized_image, $cache_file, 85); break;
            case 'image/png':  imagepng($resized_image, $cache_file, 8);  break;
            case 'image/gif':  imagegif($resized_image, $cache_file);      break;
        }
        readfile($cache_file); // Final output from cache
        
        imagedestroy($resized_image);
        imagedestroy($original_image);
    }
}
?>