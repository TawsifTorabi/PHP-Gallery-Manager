<?php
// Path to the uploaded image directory
$upload_dir = 'uploads/';

// Check if image file name is passed
if (!isset($_GET['file']) || empty($_GET['file'])) {
    header('HTTP/1.1 400 Bad Request');
    die('No image file specified.');
}

// Get the file name from the request and sanitize it
$file_name = basename($_GET['file']);
$file_path = $upload_dir . $file_name;

// Validate image width, default to 350
$width = 350;
if (isset($_GET['w']) && filter_var($_GET['w'], FILTER_VALIDATE_INT)) {
    $requested_width = intval($_GET['w']);
    if ($requested_width < 1000 && $requested_width > 0) {
        $width = $requested_width;
    }
}

// Ensure the file exists
if (!file_exists($file_path)) {
    header('HTTP/1.1 404 Not Found');
    die('Image not found.');
}

// Get the original image dimensions
list($original_width, $original_height) = getimagesize($file_path);

// Calculate the new height to maintain the aspect ratio
$aspect_ratio = $original_height / $original_width;
$desired_height = $width * $aspect_ratio;

// Create a new blank image with the desired size
$resized_image = imagecreatetruecolor($width, $desired_height);

// Load the original image based on file type
$image_type = mime_content_type($file_path);
switch ($image_type) {
    case 'image/jpeg':
        $original_image = imagecreatefromjpeg($file_path);
        break;
    case 'image/png':
        $original_image = imagecreatefrompng($file_path);
        break;
    case 'image/gif':
        $original_image = imagecreatefromgif($file_path);
        break;
    default:
        header('HTTP/1.1 415 Unsupported Media Type');
        die('Unsupported image type.');
}

// Resize the original image into the resized image
imagecopyresampled($resized_image, $original_image, 0, 0, 0, 0, $width, $desired_height, $original_width, $original_height);

// Output headers for the resized image
header('Content-Type: ' . $image_type);
header('Cache-Control: public, max-age=604800'); // Cache for a week
header('Content-Disposition: inline; filename="' . $file_name . '"');

// Output the resized image to the browser
switch ($image_type) {
    case 'image/jpeg':
        imagejpeg($resized_image, null, 85); // 85% quality for JPEG
        break;
    case 'image/png':
        imagepng($resized_image, null, 8); // PNG compression level 8
        break;
    case 'image/gif':
        imagegif($resized_image);
        break;
}

// Free up memory
imagedestroy($resized_image);
imagedestroy($original_image);

?>
