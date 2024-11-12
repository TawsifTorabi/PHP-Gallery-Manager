<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to update a gallery.");
    header('Location: index.php');
}

$gallery_id = $_GET['id']; // Gallery ID from URL

// Fetch the gallery details
$stmt = $conn->prepare("SELECT * FROM galleries WHERE id = ? AND created_by = ?");
$stmt->bind_param("ii", $gallery_id, $_SESSION['user_id']);
$stmt->execute();
$gallery = $stmt->get_result()->fetch_assoc();

if (!$gallery) {
    die("Gallery not found or you don't have permission to update it.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Gallery</title>
    <!-- Bootstrap CSS -->
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
    <!-- Fixed Top Navbar -->
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h2>Update Gallery</h2>
        <form id="galleryForm" action="gallery_update.php?id=<?php echo $gallery_id; ?>" method="post" enctype="multipart/form-data">
            <div class="form-group mb-3">
                <label for="title">Gallery Title</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo $gallery['title']; ?>" required>
            </div>
            <div class="form-group mb-3">
                <label for="description">Gallery Description</label>
                <textarea class="form-control" id="description" name="description" required><?php echo $gallery['description']; ?></textarea>
            </div>
            <div class="form-group mb-3">
                <label for="media">Add More Media (Images/Videos)</label>
                <input type="file" class="form-control" id="mediaInput" name="media[]" multiple accept="image/*,video/*">
            </div>
            <button type="submit" id="createbtn" class="btn btn-primary">Update Gallery</button>
            <!-- Add progress bar in HTML -->
            <div class="progress mb-3">
                <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
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
    <!-- Bootstrap JS and Popper.js -->

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
                }, 150);
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
                    window.location.href = 'display_gallery.php?id=<?=$gallery_id?>';
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

        // Clipboard image support
        window.addEventListener('paste', (event) => {
            const clipboardItems = event.clipboardData.items;
            for (const item of clipboardItems) {
                if (item.type.startsWith('image/')) {
                    const file = item.getAsFile();
                    const files = Array.from(mediaInput.files);
                    files.push(file);
                    const dataTransfer = new DataTransfer();
                    files.forEach(f => dataTransfer.items.add(f));
                    mediaInput.files = dataTransfer.files;

                    const event = new Event('change');
                    mediaInput.dispatchEvent(event);
                }
            }
        });
    </script>

</body>

</html>