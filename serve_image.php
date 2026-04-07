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

// 3. NEW: Validate Quality (q)
$quality = 80; // Default quality
if (isset($_GET['q']) && filter_var($_GET['q'], FILTER_VALIDATE_INT)) {
    $requested_q = intval($_GET['q']);
    if ($requested_q >= 1 && $requested_q <= 100) {
        $quality = $requested_q;
    }
}

// 4. CACHING LOGIC
$image_type = mime_content_type($file_path);
$extension  = pathinfo($file_path, PATHINFO_EXTENSION);

// UPDATED: Unique cache key now includes quality parameter
$cache_key  = md5($file_name . $width . $quality);
$cache_file = $cache_dir . $cache_key . '.' . $extension;

if (file_exists($cache_file) && filemtime($cache_file) > filemtime($file_path)) {
    header('Content-Type: ' . $image_type);
    header('Cache-Control: public, max-age=604800');
    header('X-Cache: HIT');
    readfile($cache_file);
    exit;
}

// 5. RESIZING & COMPRESSION LOGIC
header('Content-Type: ' . $image_type);
header('Cache-Control: public, max-age=604800');
header('X-Cache: MISS');

$modern_formats = ['image/webp', 'image/heic', 'image/heif'];

if (in_array($image_type, $modern_formats)) {
    /**
     * ImageMagick Compression
     * -quality: 1-100 scale
     */
    $cmd = "convert " . escapeshellarg($file_path) . " -auto-orient -thumbnail " . escapeshellarg($width) . " -quality " . escapeshellarg($quality) . " " . escapeshellarg($cache_file);
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
        
        // Save to cache with Quality
        switch ($image_type) {
            case 'image/jpeg': 
                imagejpeg($resized_image, $cache_file, $quality); 
                break;
            case 'image/png':  
                /**
                 * PNG Quality in GD is 0 (no compression) to 9 (max compression).
                 * We convert the 0-100 scale to 0-9.
                 */
                $png_quality = (int)round((100 - $quality) / 10);
                imagepng($resized_image, $cache_file, $png_quality);  
                break;
            case 'image/gif':  
                imagegif($resized_image, $cache_file); 
                break;
        }
        readfile($cache_file);
        
        imagedestroy($resized_image);
        imagedestroy($original_image);
    }
    // Temporary debug: Tell us the file size of the generated cache
header('X-Debug-Filesize: ' . filesize($cache_file));
header('X-Debug-Quality: ' . $quality);
}
?>