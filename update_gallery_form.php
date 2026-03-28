<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$gallery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">
    <script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
    <style>
        .media-preview-container {
            display: flex;
            flex-wrap: wrap;
            margin-top: 10px;
            gap: 15px;
        }

        .media-preview {
            position: relative;
            width: 150px;
            height: 150px;
            border: 2px solid #ddd;
            background: #eee;
            overflow: hidden;
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
            top: 5px;
            width: 25px;
            height: 25px;
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            z-index: 10;
            font-weight: bold;
        }

        .remove-media {
            right: 5px;
            background-color: rgba(255, 0, 0, 0.8);
        }

        .crop-media {
            left: 5px;
            background-color: rgba(0, 128, 0, 0.8);
        }

        .video-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            background: #333;
            color: #fff;
            font-size: 10px;
            text-align: center;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h2>Update Gallery: <?php echo htmlspecialchars($gallery['title']); ?></h2>
        <form id="galleryForm" action="gallery_update.php?id=<?php echo $gallery_id; ?>" method="post" enctype="multipart/form-data">
            <div class="form-group mb-3">
                <label for="title">Gallery Title</label>
                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($gallery['title']); ?>" required>
            </div>
            <div class="form-group mb-3">
                <label for="description">Gallery Description</label>
                <textarea class="form-control" id="description" rows="40" name="description"><?php echo htmlspecialchars($gallery['description']); ?></textarea>
            </div>

            <hr>

            <button type="submit" id="submitBtn" class="btn btn-primary w-100 py-2">Update Gallery</button>

            <div class="progress mt-3" style="height: 25px; display: none;" id="progressWrapper">
                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
            </div>

            <hr>

            <div class="card p-3 mb-3">
                <label class="fw-bold mb-2">Media Management</label>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="videoPreviewToggle" checked>
                    <label class="form-check-label" for="videoPreviewToggle">Enable Video Previews (Disable for 100MB+ files to prevent crashes)</label>
                </div>
                <input type="file" class="form-control" id="mediaInput" name="media[]" multiple accept="image/*,video/*">
                <small class="text-muted">You can also paste images directly from your clipboard.</small>

                <div class="d-flex justify-content-between mt-2">
                    <small id="uploadSpeed" class="text-muted">Speed: 0 KB/s</small>
                    <small id="uploadTime" class="text-muted">Time Remaining: --:--</small>
                </div>
            </div>

            <div class="media-preview-container mb-4" id="mediaPreviewContainer"></div>

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
                    <div style="max-height: 500px; overflow: hidden;">
                        <img id="imageToCrop" style="max-width: 100%;">
                    </div>
                </div>
                <div class="modal-footer">
                    <select id="aspectRatioPreset" class="form-select form-select-sm w-auto me-auto">
                        <option value="NaN">Free Crop</option>
                        <option value="1">Square (1:1)</option>
                        <option value="1.77777777778">16:9</option>
                        <option value="0.66666666666">2:3</option>
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
        const videoToggle = document.getElementById('videoPreviewToggle');
        const progressWrapper = document.getElementById('progressWrapper');
        const progressBar = document.getElementById('progressBar');

        let selectedFiles = []; // Master list of files
        let cropper;
        let currentFileIndex;
        let descriptionEditor;

        // CKEditor Initialization
        ClassicEditor.create(document.querySelector('#description')).then(editor => {
            descriptionEditor = editor;
        });

        // Helper to update the actual hidden input with the current selectedFiles array
        function syncInput() {
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            mediaInput.files = dt.files;
            renderPreviews();
        }

        // Handle File Selection (Explorer)
        mediaInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            // Append new files to our master list instead of overwriting
            selectedFiles = [...selectedFiles, ...files];
            syncInput();
        });

        // Handle Paste from Clipboard
        window.addEventListener('paste', (e) => {
            const items = e.clipboardData.items;
            let pasted = false;
            for (let item of items) {
                if (item.type.indexOf("image") !== -1) {
                    const blob = item.getAsFile();
                    const file = new File([blob], `pasted_image_${Date.now()}.png`, {
                        type: blob.type
                    });
                    selectedFiles.push(file);
                    pasted = true;
                }
            }
            if (pasted) syncInput();
        });

        // Render the Preview UI
        function renderPreviews() {
            mediaPreviewContainer.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const div = document.createElement('div');
                div.className = 'media-preview';

                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-media';
                removeBtn.innerHTML = '&times;';
                removeBtn.onclick = (e) => {
                    e.preventDefault();
                    removeFile(index);
                };
                div.appendChild(removeBtn);

                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    div.appendChild(img);

                    const cropBtn = document.createElement('button');
                    cropBtn.className = 'crop-media';
                    cropBtn.innerHTML = '✂';
                    cropBtn.onclick = (e) => {
                        e.preventDefault();
                        openCropModal(file, index);
                    };
                    div.appendChild(cropBtn);
                } else if (file.type.startsWith('video/')) {
                    if (videoToggle.checked) {
                        const video = document.createElement('video');
                        video.src = URL.createObjectURL(file);
                        video.controls = true;
                        div.appendChild(video);
                    } else {
                        const placeholder = document.createElement('div');
                        placeholder.className = 'video-placeholder';
                        placeholder.innerHTML = `Video File<br>(${Math.round(file.size/1024/1024)}MB)`;
                        div.appendChild(placeholder);
                    }
                }
                mediaPreviewContainer.appendChild(div);
            });
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            syncInput();
        }

        // Cropping Logic
        function openCropModal(file, index) {
            currentFileIndex = index;
            const reader = new FileReader();
            reader.onload = function(e) {
                const image = document.getElementById('imageToCrop');
                image.src = e.target.result;
                const modal = new bootstrap.Modal(document.getElementById('cropModal'));
                modal.show();
                if (cropper) cropper.destroy();
                setTimeout(() => {
                    cropper = new Cropper(image, {
                        viewMode: 1,
                        aspectRatio: NaN
                    });
                }, 300);
            };
            reader.readAsDataURL(file);
        }

        document.getElementById('cropButton').addEventListener('click', () => {
            cropper.getCroppedCanvas().toBlob((blob) => {
                const oldFile = selectedFiles[currentFileIndex];
                const newFile = new File([blob], oldFile.name, {
                    type: 'image/jpeg'
                });
                selectedFiles[currentFileIndex] = newFile;
                syncInput();
                bootstrap.Modal.getInstance(document.getElementById('cropModal')).hide();
            }, 'image/jpeg', 0.9);
        });

        document.getElementById('aspectRatioPreset').addEventListener('change', (e) => {
            cropper.setAspectRatio(parseFloat(e.target.value));
        });

        // Form Submission with Progress Bar
        // Form Submission with Progress Bar, Speed, and ETA
        document.getElementById('galleryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            if (descriptionEditor) document.getElementById('description').value = descriptionEditor.getData();

            const formData = new FormData(this);
            formData.delete('media[]');
            selectedFiles.forEach(file => formData.append('media[]', file));

            const xhr = new XMLHttpRequest();
            xhr.open('POST', this.action, true);

            progressWrapper.style.display = 'block';
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;

            // Tracking variables for speed
            let startTime = Date.now();
            let lastTime = startTime;
            let lastLoaded = 0;

            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) {
                    const now = Date.now();
                    const timeDiff = (now - lastTime) / 1000; // seconds passed since last update

                    // Update speed stats every 500ms to avoid flickering
                    if (timeDiff >= 0.5 || event.loaded === event.total) {
                        const loadedDiff = event.loaded - lastLoaded;
                        const bps = loadedDiff / timeDiff; // Bytes per second

                        // 1. Calculate and Format Speed
                        let speedText = "Speed: ";
                        if (bps > 1024 * 1024) {
                            speedText += (bps / (1024 * 1024)).toFixed(2) + " MB/s";
                        } else {
                            speedText += (bps / 1024).toFixed(2) + " KB/s";
                        }

                        // 2. Calculate and Format ETA (Time Remaining)
                        const remainingBytes = event.total - event.loaded;
                        const secondsRemaining = remainingBytes / bps;
                        let etaText = "Time Remaining: ";

                        if (isFinite(secondsRemaining) && bps > 0) {
                            const mins = Math.floor(secondsRemaining / 60);
                            const secs = Math.round(secondsRemaining % 60);
                            etaText += mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
                        } else {
                            etaText += "Calculating...";
                        }

                        // 3. UPDATE THE ACTUAL HTML ELEMENTS
                        document.getElementById('uploadSpeed').innerText = speedText;
                        document.getElementById('uploadTime').innerText = etaText;

                        // Update trackers for the next calculation
                        lastTime = now;
                        lastLoaded = event.loaded;
                    }

                    // Update Progress Bar
                    const pct = Math.round((event.loaded / event.total) * 100);
                    progressBar.style.width = pct + '%';
                    progressBar.innerHTML = pct + '%';
                }
            };

            xhr.onload = function() {
                if (xhr.status === 200) {
                    // alert('Gallery updated successfully!');
                    window.location.href = 'display_gallery.php?id=<?php echo $gallery_id; ?>?msg=true&msg_content=' + encodeURIComponent("Gallery updated successfully!");
                } else {
                    alert('Upload failed. Check server limits (post_max_size/upload_max_filesize).');
                    // window.location.href = 'display_gallery.php?id=<?php echo $gallery_id; ?>?msg=false&msg_content=' + encodeURIComponent('Failed to update gallery.');
                    submitBtn.disabled = false;
                }
            };

            xhr.send(formData);
        });

        videoToggle.onchange = renderPreviews;
    </script>
</body>

</html>