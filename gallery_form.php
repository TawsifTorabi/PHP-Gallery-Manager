<?php
include 'session.php';
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

        // Generate a unique file name using uniqid and timestamp
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION); // Get the file extension
        $unique_file_name = uniqid() . '-' . time() . '.' . $file_ext; // Append timestamp and extension
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


    $msg = 'Gallery created successfully!';
    //     //header("Location: dashboard.php"); // Redirect to dashboard after creation
    header("Location: display_gallery.php?id=$gallery_id&msg=true&msg_content=$msg");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Gallery with Crop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">
    <style>
        /* Your existing styles */
        .media-preview-container {
            display: flex;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .media-preview {
            position: relative;
            margin: 10px;
        }

        .media-preview img,
        .media-preview video {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 2px solid #ddd;
        }

        .remove-media,
        .crop-media {
            position: absolute;
            top: -10px;
            background-color: red;
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            width: 25px;
            height: 25px;
        }

        .remove-media {
            right: -10px;
        }

        .crop-media {
            left: -10px;
            background-color: green;
        }

        .progress {
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-2">
        <h2>Create New Gallery</h2>
        <form id="galleryForm" action="gallery_form.php" method="post" enctype="multipart/form-data">
            <!-- Form fields for title, description, media upload -->
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
                <input type="file" class="form-control" id="mediaInput" name="media[]" multiple required>
            </div>
            <button type="submit" id="createbtn" class="btn btn-primary">Create Gallery</button>
            <div class="progress">
                <div class="progress-bar" id="progressBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
            </div>
        </form>
        <div class="media-preview-container" id="mediaPreviewContainer"></div>
    </div>

    <!-- Modal for cropping -->
    <div class="modal fade" id="cropModal" tabindex="-1" aria-labelledby="cropModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cropModalLabel">Crop Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div style="max-height: 400px;">
                        <img id="imageToCrop" style="max-width: 100%;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="cropButton" class="btn btn-primary">Crop</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <script>
        const mediaInput = document.getElementById('mediaInput');
        const mediaPreviewContainer = document.getElementById('mediaPreviewContainer');
        const progressBar = document.getElementById('progressBar');
        let selectedFiles = [];
        let cropper;
        let currentFileIndex;

        // Event listener for input file change
        mediaInput.addEventListener('change', (event) => {
            const files = Array.from(event.target.files);
            selectedFiles = [...files]; // Store selected files in selectedFiles array

            renderPreviews(); // Use a separate function to render previews to avoid duplication
        });

        // Function to render previews
        function renderPreviews() {
            mediaPreviewContainer.innerHTML = ''; // Clear previous previews

            selectedFiles.forEach((file, index) => {
                const fileType = file.type.split('/')[0];
                if (fileType === 'image') {
                    previewImage(file, index);
                } else if (fileType === 'video') {
                    previewVideo(file, index);
                }
            });
        }

        // Function to preview images
        function previewImage(file, index) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const mediaPreviewDiv = document.createElement('div');
                mediaPreviewDiv.classList.add('media-preview');
                mediaPreviewDiv.dataset.index = index; // Set unique identifier

                const img = document.createElement('img');
                img.src = e.target.result;

                const removeButton = document.createElement('button');
                removeButton.classList.add('remove-media');
                removeButton.innerHTML = '&times;';
                removeButton.onclick = () => removeMedia(index);

                const cropButton = document.createElement('button');
                cropButton.classList.add('crop-media');
                cropButton.innerHTML = '✂';
                cropButton.onclick = () => openCropModal(file, index);

                mediaPreviewDiv.appendChild(img);
                mediaPreviewDiv.appendChild(removeButton);
                mediaPreviewDiv.appendChild(cropButton);
                mediaPreviewContainer.appendChild(mediaPreviewDiv);
            };
            reader.readAsDataURL(file);
        }

        // Function to preview videos
        function previewVideo(file, index) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const mediaPreviewDiv = document.createElement('div');
                mediaPreviewDiv.classList.add('media-preview');
                mediaPreviewDiv.dataset.index = index; // Set unique identifier

                const video = document.createElement('video');
                video.src = e.target.result;
                video.controls = true;

                const removeButton = document.createElement('button');
                removeButton.classList.add('remove-media');
                removeButton.innerHTML = '&times;';
                removeButton.onclick = () => removeMedia(index);

                mediaPreviewDiv.appendChild(video);
                mediaPreviewDiv.appendChild(removeButton);
                mediaPreviewContainer.appendChild(mediaPreviewDiv);
            };
            reader.readAsDataURL(file);
        }

        // Remove media function
        function removeMedia(index) {
            // Remove the file from the selectedFiles array
            selectedFiles.splice(index, 1);

            // Update the files in the input element
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));

            // Clear the current files in the input field and set it to the updated list
            mediaInput.files = dataTransfer.files;

            // Optionally, reset the input field to trigger the change event
            // This ensures the input field reflects the new state and avoids duplicates
            mediaInput.value = ''; // Clear the input field

            // Reassign files to input (necessary for browsers to refresh the input state)
            mediaInput.files = dataTransfer.files;

            // Trigger the change event to update the file preview
            const event = new Event('change');
            mediaInput.dispatchEvent(event);
        }


        // Open crop modal and initialize Cropper
        function openCropModal(file, index) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imageToCrop').src = e.target.result;
                currentFileIndex = index;

                const cropModal = new bootstrap.Modal(document.getElementById('cropModal'));
                cropModal.show();

                if (cropper) {
                    cropper.destroy(); // Ensure cropper is fully destroyed before re-initializing
                }

                setTimeout(() => {
                    cropper = new Cropper(document.getElementById('imageToCrop'), {
                        viewMode: 1,
                    });
                }, 500);
            };
            reader.readAsDataURL(file);
        }

        // Crop image and replace the file in selectedFiles array
        document.getElementById('cropButton').addEventListener('click', () => {
            if (cropper) {
                cropper.getCroppedCanvas().toBlob((blob) => {
                    const croppedFile = new File([blob], selectedFiles[currentFileIndex].name, {
                        type: selectedFiles[currentFileIndex].type,
                        lastModified: Date.now(),
                    });

                    selectedFiles[currentFileIndex] = croppedFile; // Replace the file with cropped version
                    console.log("After cropping, selectedFiles:", selectedFiles);
                    updateMediaInput(); // Update the input and re-render previews
                    cropper.destroy();
                    cropper = null;
                    const cropModal = bootstrap.Modal.getInstance(document.getElementById('cropModal'));
                    cropModal.hide();
                });
            }
        });

        // Update media input and re-render previews
        function updateMediaInput() {
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            mediaInput.files = dataTransfer.files; // Assign unique files to input
            console.log("Updating media input, mediaInput.files:", mediaInput.files);

            renderPreviews(); // Re-render previews with updated selectedFiles
        }

        // Submit form with files and show progress
        document.getElementById('galleryForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            document.getElementById('createbtn').setAttribute('disabled', true);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.action, true);

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressBar.style.width = percentComplete + '%';
                    progressBar.setAttribute('aria-valuenow', percentComplete);
                    progressBar.textContent = Math.round(percentComplete) + '%';
                }
            });

            xhr.onload = function() {
                if (xhr.status === 200) {
                    alert('Gallery created successfully!');
                    window.location.href = 'dashboard.php';
                } else {
                    alert('Error creating gallery');
                }
            };

            
            //selectedFiles.forEach(file => formData.append('media[]', file));
            
            // Handle errors
            xhr.onerror = function() {
                alert('An error occurred while uploading the files.');
            };

            // Send the form data
            xhr.send(formData);
            
        });
    </script>
</body>

</html>