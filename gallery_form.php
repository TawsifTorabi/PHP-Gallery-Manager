<?php
include 'session.php';
require 'db.php'; // Include your database connection file
require 'imageHash.php'; // Include image hash helper

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to create a gallery.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $hero_images = '';
    
    // Create a new gallery
    $stmt = $conn->prepare("INSERT INTO galleries (title, description, hero_images, created_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $title, $description,$hero_images, $user_id);
    $stmt->execute();
    $gallery_id = $stmt->insert_id;

    // Handle media uploads
    if (isset($_FILES['media'])) {
        foreach ($_FILES['media']['name'] as $key => $file_name) {
            $file_tmp = $_FILES['media']['tmp_name'][$key];
            if (empty($file_tmp)) continue;

            $file_type_mime = mime_content_type($file_tmp);
            $media_type = (strpos($file_type_mime, 'image') !== false) ? 'image' : 'video';

            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_file_name = uniqid() . '-' . time() . '.' . $file_ext;
            $upload_dir = 'uploads/';

            if (move_uploaded_file($file_tmp, $upload_dir . $unique_file_name)) {
                $imagehash = ($media_type == 'image') ? getImageHash($upload_dir . $unique_file_name) : '';

                $stmt = $conn->prepare("INSERT INTO images (gallery_id, file_name, file_type, imageHash_hamming) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $gallery_id, $unique_file_name, $media_type, $imagehash);
                $stmt->execute();
            }
        }
    }

    // Success response for AJAX
    http_response_code(200);
    echo json_encode(['status' => 'success', 'gallery_id' => $gallery_id]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Gallery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">
    <script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
    <style>
        .media-preview-container {
            display: flex;
            flex-wrap: wrap;
            margin-top: 15px;
            gap: 10px;
        }

        .media-preview {
            position: relative;
            width: 150px;
            height: 150px;
            border: 2px solid #ddd;
            background: #f9f9f9;
        }

        .media-preview img,
        .media-preview video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .remove-media,
        .crop-media {
            position: absolute;
            top: -10px;
            border: none;
            border-radius: 50%;
            width: 26px;
            height: 26px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            z-index: 5;
        }

        .remove-media {
            right: -10px;
            background-color: #dc3545;
        }

        .crop-media {
            left: -10px;
            background-color: #198754;
        }

        .ck-editor__editable_inline {
            min-height: 200px;
        }

        .video-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            background: #333;
            color: white;
            font-size: 12px;
            text-align: center;
            padding: 5px;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        <h2 class="mb-4">Create New Gallery</h2>
        <form id="galleryForm" action="gallery_form.php" method="post" enctype="multipart/form-data">
            <div class="card p-4 shadow-sm">
                <div class="form-group mb-3">
                    <label class="form-label fw-bold">Gallery Title</label>
                    <input type="text" class="form-control" id="title" name="title" required placeholder="Enter gallery name...">
                </div>

                <div class="form-group mb-3">
                    <label class="form-label fw-bold">Gallery Description</label>
                    <textarea class="form-control" id="description" name="description"></textarea>
                    <hr>
                    <button type="submit" id="createbtn" class="btn btn-primary btn-lg w-100">Create Gallery</button>

                    <div class="progress mt-4" style="height: 25px; display: none;" id="progressContainer">
                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label fw-bold">Media Upload</label>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="videoToggle" checked>
                        <label class="form-check-label" for="videoToggle">Enable Video Previews (Disable if uploading large 100MB+ files)</label>
                    </div>
                    <input type="file" class="form-control" id="mediaInput" name="media[]" multiple accept="image/*,video/*">
                    <small class="text-muted">Tip: You can paste images from your clipboard directly onto this page.</small>
                    
                    <div class="d-flex justify-content-between mt-2">
                        <small id="uploadSpeed" class="text-muted">Speed: 0 KB/s</small>
                        <small id="uploadTime" class="text-muted">Time Remaining: --:--</small>
                    </div>
                </div>

                <div class="media-preview-container mb-4" id="mediaPreviewContainer"></div>

            </div>
        </form>
    </div>

    <div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crop Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div style="max-height: 500px; overflow: hidden; background: #eee;">
                        <img id="imageToCrop" style="max-width: 100%;">
                    </div>
                </div>
                <div class="modal-footer">
                    <select id="aspectRatioPreset" class="form-select form-select-sm w-auto me-auto">
                        <option value="NaN">Free Crop</option>
                        <option value="1">Square (1:1)</option>
                        <option value="1.77777777778">Widescreen (16:9)</option>
                        <option value="0.66666666666">Portrait (2:3)</option>
                    </select>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="cropButton" class="btn btn-success">Apply Crop</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>

    <script>
        const mediaInput = document.getElementById('mediaInput');
        const mediaPreviewContainer = document.getElementById('mediaPreviewContainer');
        const videoToggle = document.getElementById('videoToggle');
        const progressBar = document.getElementById('progressBar');
        const progressContainer = document.getElementById('progressContainer');

        let selectedFiles = [];
        let cropper;
        let currentFileIndex;
        let descriptionEditor;

        // 1. Initialize Editor
        ClassicEditor.create(document.querySelector('#description')).then(editor => {
            descriptionEditor = editor;
        });

        // 2. State Management: Sync input files with our array
        function syncFiles() {
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            mediaInput.files = dt.files;
            renderPreviews();
        }

        // 3. Handle File Input
        mediaInput.addEventListener('change', (e) => {
            const newFiles = Array.from(e.target.files);
            // Append and maintain state
            selectedFiles = [...selectedFiles, ...newFiles];
            syncFiles();
        });

        // 4. Handle Paste
        window.addEventListener('paste', (e) => {
            const items = e.clipboardData.items;
            let added = false;
            for (let item of items) {
                if (item.type.startsWith("image/")) {
                    const blob = item.getAsFile();
                    const file = new File([blob], `pasted_${Date.now()}.png`, {
                        type: blob.type
                    });
                    selectedFiles.push(file);
                    added = true;
                }
            }
            if (added) syncFiles();
        });

        // 5. Render Logic
        function renderPreviews() {
            mediaPreviewContainer.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const div = document.createElement('div');
                div.className = 'media-preview';

                // Remove Button
                const rmBtn = document.createElement('button');
                rmBtn.className = 'remove-media';
                rmBtn.innerHTML = '&times;';
                rmBtn.onclick = (e) => {
                    e.preventDefault();
                    removeFile(index);
                };
                div.appendChild(rmBtn);

                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    div.appendChild(img);

                    const cropBtn = document.createElement('button');
                    cropBtn.className = 'crop-media';
                    cropBtn.innerHTML = '✂';
                    cropBtn.onclick = (e) => {
                        e.preventDefault();
                        openCrop(file, index);
                    };
                    div.appendChild(cropBtn);
                } else {
                    if (videoToggle.checked) {
                        const video = document.createElement('video');
                        video.src = URL.createObjectURL(file);
                        video.controls = true;
                        div.appendChild(video);
                    } else {
                        const placeholder = document.createElement('div');
                        placeholder.className = 'video-placeholder';
                        placeholder.innerHTML = `Video<br>(${Math.round(file.size/1024/1024)}MB)`;
                        div.appendChild(placeholder);
                    }
                }
                mediaPreviewContainer.appendChild(div);
            });
        }

        function removeFile(idx) {
            selectedFiles.splice(idx, 1);
            syncFiles();
        }

        // 6. Cropper Logic
        function openCrop(file, idx) {
            currentFileIndex = idx;
            const reader = new FileReader();
            reader.onload = (e) => {
                document.getElementById('imageToCrop').src = e.target.result;
                const modal = new bootstrap.Modal(document.getElementById('cropModal'));
                modal.show();
                if (cropper) cropper.destroy();
                setTimeout(() => {
                    cropper = new Cropper(document.getElementById('imageToCrop'), {
                        viewMode: 1
                    });
                }, 300);
            };
            reader.readAsDataURL(file);
        }

        document.getElementById('cropButton').onclick = () => {
            cropper.getCroppedCanvas().toBlob((blob) => {
                const oldFile = selectedFiles[currentFileIndex];
                selectedFiles[currentFileIndex] = new File([blob], oldFile.name, {
                    type: 'image/jpeg'
                });
                syncFiles();
                bootstrap.Modal.getInstance(document.getElementById('cropModal')).hide();
            }, 'image/jpeg', 0.9);
        };

        document.getElementById('aspectRatioPreset').onchange = (e) => {
            cropper.setAspectRatio(parseFloat(e.target.value));
        };

        // 7. Form Submission
        let startTime;
        let lastLoaded = 0;
        let lastTime = 0;

        document.getElementById('galleryForm').onsubmit = function(e) {
            e.preventDefault();
            if (descriptionEditor) document.getElementById('description').value = descriptionEditor.getData();

            const formData = new FormData(this);
            formData.delete('media[]');
            selectedFiles.forEach(f => formData.append('media[]', f));

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'gallery_form.php', true);

            progressContainer.style.display = 'block';
            document.getElementById('createbtn').disabled = true;

            // Initialize timing for speed calculation
            startTime = new Date().getTime();
            lastTime = startTime;

            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) {
                    const currentTime = new Date().getTime();
                    const duration = (currentTime - lastTime) / 1000; // time passed in seconds since last update

                    if (duration >= 0.5) { // Update speed every half second for smoothness
                        const bytesSent = event.loaded - lastLoaded;
                        const speed = bytesSent / duration; // bytes per second

                        // 1. Calculate Human Readable Speed
                        let speedText = "";
                        if (speed > 1024 * 1024) {
                            speedText = (speed / (1024 * 1024)).toFixed(2) + " MB/s";
                        } else {
                            speedText = (speed / 1024).toFixed(2) + " KB/s";
                        }
                        document.getElementById('uploadSpeed').innerText = "Speed: " + speedText;

                        // 2. Calculate Estimated Time Remaining (ETA)
                        const bytesRemaining = event.total - event.loaded;
                        const secondsRemaining = Math.round(bytesRemaining / speed);
                        const minutes = Math.floor(secondsRemaining / 60);
                        const seconds = secondsRemaining % 60;
                        document.getElementById('uploadTime').innerText = `Remaining: ${minutes}m ${seconds}s`;

                        // Update "Last" markers
                        lastLoaded = event.loaded;
                        lastTime = currentTime;
                    }

                    // Standard Progress Bar Update
                    const pct = Math.round((event.loaded / event.total) * 100);
                    progressBar.style.width = pct + '%';
                    progressBar.innerHTML = pct + '%';
                }
            };

            xhr.onload = function() {
                console.log(xhr.responseText); 
                if (xhr.status === 200) {
                    const res = JSON.parse(xhr.responseText);
                    window.location.href = `display_gallery.php?id=${res.gallery_id}&msg=true&msg_content=Gallery Created!`;
                } else {
                    alert("Upload failed. Server may have rejected the file size.");
                    document.getElementById('createbtn').disabled = false;
                }
            };

            xhr.send(formData);
        };

        videoToggle.onchange = renderPreviews;
    </script>
</body>

</html>