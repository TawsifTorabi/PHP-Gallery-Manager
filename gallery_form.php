<?php
session_start();
require 'db.php'; // Include your database connection file

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to create a gallery.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];

    // Create a new gallery
    $stmt = $conn->prepare("INSERT INTO galleries (title, description, created_by) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $title, $description, $user_id);
    $stmt->execute();
    $gallery_id = $stmt->insert_id; // Get the newly created gallery ID

    // Handle media uploads
    foreach ($_FILES['media']['name'] as $key => $file_name) {
        $file_tmp = $_FILES['media']['tmp_name'][$key];
        $file_type = mime_content_type($file_tmp);

        // Check if the file is an image or video
        $media_type = (strpos($file_type, 'image') !== false) ? 'image' : 'video';

        // Generate a unique file name
        $unique_file_name = uniqid() . '-' . basename($file_name);
        $upload_dir = 'uploads/';

        if (move_uploaded_file($file_tmp, $upload_dir . $unique_file_name)) {
            // Insert the uploaded file details into the `images` table
            $stmt = $conn->prepare("INSERT INTO images (gallery_id, file_name, file_type) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $gallery_id, $unique_file_name, $media_type);
            $stmt->execute();
        } else {
            echo "Failed to upload file: " . $file_name;
        }
    }

    echo "Gallery created successfully!";
    header("Location: dashboard.php"); // Redirect to dashboard after creation
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Gallery</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5">
    <h2>Create New Gallery</h2>
    <form id="galleryForm" action="gallery_form.php" method="post" enctype="multipart/form-data">
        <div class="form-group mb-3">
            <label for="title">Gallery Title</label>
            <input type="text" class="form-control" id="title" name="title" required>
        </div>
        <div class="form-group mb-3">
            <label for="description">Gallery Description</label>
            <textarea class="form-control" id="description" name="description" required></textarea>
        </div>
        <div class="form-group mb-3">
            <label for="media">Upload Media (Images/Videos)</label>
            <input type="file" class="form-control" id="media" name="media[]" multiple required>
        </div>
        <button type="submit" class="btn btn-primary">Create Gallery</button>
    </form>
</div>

<!-- Bootstrap JS and Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

</body>
</html>
