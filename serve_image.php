<?php
// Path to the uploaded image directory
$upload_dir = 'uploads/';

// Check if image file name is passed
if (!isset($_GET['file'])) {
    die('No image file specified.');
}

// Get the file name from the request
$file_name = basename($_GET['file']);
$file_path = $upload_dir . $file_name;

// Ensure the file exists
if (!file_exists($file_path)) {
    die('Image not found.');
}

// Get the desired width for the compressed image (you can adjust this)
$desired_width = 350; // Set your desired width for the image

// Get the original image dimensions
list($original_width, $original_height) = getimagesize($file_path);

// Calculate the new height to maintain aspect ratio
$aspect_ratio = $original_height / $original_width;
$desired_height = $desired_width * $aspect_ratio;

// Create a new blank image with the desired size
$resized_image = imagecreatetruecolor($desired_width, $desired_height);

// Load the original image
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
        die('Unsupported image type.');
}

// Resize the original image into the resized image
imagecopyresampled($resized_image, $original_image, 0, 0, 0, 0, $desired_width, $desired_height, $original_width, $original_height);

// Output the resized image to the browser
header('Content-Type: ' . $image_type);

switch ($image_type) {
    case 'image/jpeg':
        imagejpeg($resized_image, null, 85); // 85% quality
        break;
    case 'image/png':
        imagepng($resized_image, null, 8); // Compression level
        break;
    case 'image/gif':
        imagegif($resized_image);
        break;
}

// Free up memory
imagedestroy($resized_image);
imagedestroy($original_image);
?>
