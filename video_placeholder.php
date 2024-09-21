<?php
// video_placeholder.php

// Ensure a file name is provided
if (isset($_GET['file_name'])) {
    $file_name = basename($_GET['file_name']);
    
    // Set the path to your videos
    $video_path = 'uploads/' . $file_name;

    // Check if the video file exists
    if (file_exists($video_path)) {
        // Define the thumbnail path
        $thumbnail_path = 'thumbnails/' . pathinfo($file_name, PATHINFO_FILENAME) . '.png';

        // Generate the thumbnail if it doesn't already exist
        if (!file_exists($thumbnail_path)) {
            // Create the thumbnails directory if it doesn't exist
            if (!is_dir('thumbnails')) {
                mkdir('thumbnails', 0755, true);
            }

            // Use FFmpeg to create a thumbnail
            $cmd = "ffmpeg -i " . escapeshellarg($video_path) . " -ss 00:00:01.000 -vframes 1 " . escapeshellarg($thumbnail_path);
            exec($cmd);
        }

        // Set the content type header for the image
        header('Content-Type: image/png');

        // Output the thumbnail
        readfile($thumbnail_path);
    } else {
        // Handle the case where the video does not exist
        header('HTTP/1.0 404 Not Found');
        echo 'Video not found.';
    }
} else {
    // Handle the case where no file name is provided
    header('HTTP/1.0 400 Bad Request');
    echo 'No file name provided.';
}
?>
