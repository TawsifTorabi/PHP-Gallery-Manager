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
    <style>
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

        .remove-media {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: red;
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            width: 25px;
            height: 25px;
        }

        .filename {
            margin-top: 10px;
            font-size: 0.9rem;
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
            <button type="submit" class="btn btn-primary">Update Gallery</button>
            <!-- Add progress bar in HTML -->
            <div class="progress mb-3">
                <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
            </div>

            <div class="media-preview-container" id="mediaPreviewContainer"></div>
        </form>
    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <script>
        document.getElementById('galleryForm').addEventListener('submit', function(event) {
            event.preventDefault(); // Prevent the default form submission

            const form = event.target;
            const formData = new FormData(form);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.action, true);

            // Update progress bar during the upload
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    const progressBar = document.getElementById('progressBar');
                    progressBar.style.width = percentComplete + '%';
                    progressBar.setAttribute('aria-valuenow', percentComplete);
                    progressBar.textContent = Math.round(percentComplete) + '%';
                }
            });

            // When the upload is complete
            xhr.onload = function() {
                if (xhr.status === 200) {
                    alert('Gallery created successfully!');
                    window.location.href = 'dashboard.php'; // Redirect on success
                } else {
                    alert('Failed to create gallery. Please try again.');
                }
            };

            // Handle errors
            xhr.onerror = function() {
                alert('An error occurred while uploading the files.');
            };

            // Send the form data
            xhr.send(formData);
        });


        const mediaInput = document.getElementById('mediaInput');
        const mediaPreviewContainer = document.getElementById('mediaPreviewContainer');
        let selectedFiles = [];

        mediaInput.addEventListener('change', (event) => {
            const files = Array.from(event.target.files);
            mediaPreviewContainer.innerHTML = '';
            selectedFiles = [...files]; // Update selected files

            files.forEach((file, index) => {
                const fileType = file.type.split('/')[0]; // 'image' or 'video'

                if (fileType === 'image') {
                    previewImage(file, index);
                } else if (fileType === 'video') {
                    previewVideo(file, index);
                } else {
                    displayFilename(file, index);
                }
            });
        });

        function previewImage(file, index) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const mediaPreviewDiv = document.createElement('div');
                mediaPreviewDiv.classList.add('media-preview');

                const img = document.createElement('img');
                img.src = e.target.result;

                const removeButton = document.createElement('button');
                removeButton.classList.add('remove-media');
                removeButton.innerHTML = '&times;';
                removeButton.addEventListener('click', () => removeMedia(index));

                mediaPreviewDiv.appendChild(img);
                mediaPreviewDiv.appendChild(removeButton);
                mediaPreviewContainer.appendChild(mediaPreviewDiv);
            };
            reader.readAsDataURL(file);
        }

        function previewVideo(file, index) {
            const mediaPreviewDiv = document.createElement('div');
            mediaPreviewDiv.classList.add('media-preview');

            const video = document.createElement('video');
            video.controls = true;
            video.src = URL.createObjectURL(file);

            const removeButton = document.createElement('button');
            removeButton.classList.add('remove-media');
            removeButton.innerHTML = '&times;';
            removeButton.addEventListener('click', () => removeMedia(index));

            mediaPreviewDiv.appendChild(video);
            mediaPreviewDiv.appendChild(removeButton);
            mediaPreviewContainer.appendChild(mediaPreviewDiv);
        }

        function displayFilename(file, index) {
            const mediaPreviewDiv = document.createElement('div');
            mediaPreviewDiv.classList.add('media-preview');

            const filenameDiv = document.createElement('div');
            filenameDiv.classList.add('filename');
            filenameDiv.textContent = file.name;

            const removeButton = document.createElement('button');
            removeButton.classList.add('remove-media');
            removeButton.innerHTML = '&times;';
            removeButton.addEventListener('click', () => removeMedia(index));

            mediaPreviewDiv.appendChild(filenameDiv);
            mediaPreviewDiv.appendChild(removeButton);
            mediaPreviewContainer.appendChild(mediaPreviewDiv);
        }

        function removeMedia(index) {
            selectedFiles.splice(index, 1);

            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            mediaInput.files = dataTransfer.files;

            const event = new Event('change');
            mediaInput.dispatchEvent(event);
        }

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