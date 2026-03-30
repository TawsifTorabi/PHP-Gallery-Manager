<?php
session_start();
require 'db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Gallery | Studio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">
    <script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
    <style>
        /* Full Page Drag & Drop Overlay */
        #drop-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 110, 253, 0.15);
            border: 4px dashed #0d6efd;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        #drop-overlay h2 {
            color: #0d6efd;
            font-weight: bold;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .media-preview-container {
            display: flex;
            flex-wrap: wrap;
            margin-top: 15px;
            gap: 15px;
        }

        .media-preview {
            position: relative;
            width: 160px;
            height: 160px;
            border: 2px solid #dee2e6;
            background: #f8f9fa;
            overflow: hidden;
            border-radius: 12px;
            transition: transform 0.2s;
        }

        .media-preview:hover {
            transform: scale(1.02);
            border-color: #0d6efd;
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
            top: 8px;
            width: 30px;
            height: 30px;
            border: none;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .remove-media {
            right: 8px;
            background-color: #dc3545;
        }

        .crop-media {
            left: 8px;
            background-color: #198754;
        }

        .video-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            background: #343a40;
            color: #fff;
            font-size: 11px;
            text-align: center;
            padding: 5px;
        }
    </style>
</head>

<body class="bg-light">
    <div id="drop-overlay">
        <h2>Release to Upload Files</h2>
    </div>

    <?php include 'navbar.php'; ?>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2 class="fw-bold text-dark m-0">Create New Gallery</h2>
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                        </div>

                        <form id="galleryForm">
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Gallery Title</label>
                                <input type="text" class="form-control form-control-lg" id="title" placeholder="e.g. Summer Collection 2026" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea class="form-control" id="description" rows="5"></textarea>
                            </div>

                            <div class="card bg-white border-dashed p-4 mb-4 text-center">
                                <label class="fw-bold text-primary mb-3">Upload Media Assets</label>

                                <div class="d-flex justify-content-center gap-3 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="videoPreviewToggle" checked>
                                        <label class="form-check-label small text-muted" for="videoPreviewToggle">Video Previews</label>
                                    </div>
                                </div>

                                <input type="file" class="form-control" id="mediaInput" multiple accept="image/*,video/*">
                                <div class="mt-3">
                                    <p class="text-muted small mb-0"><strong>Tip:</strong> Drag and drop files anywhere on the page, or paste directly from your clipboard.</p>
                                </div>

                                <div class="d-flex justify-content-between mt-4 px-2 border-top pt-3">
                                    <small id="uploadSpeed" class="text-muted">Speed: 0 Mbps</small>
                                    <small id="uploadTime" class="text-muted">ETA: --:--</small>
                                </div>
                            </div>

                            <div class="media-preview-container mb-4" id="mediaPreviewContainer"></div>

                            <button type="submit" id="submitBtn" class="btn btn-primary btn-lg w-100 py-3 shadow-sm fw-bold">Create & Upload Gallery</button>

                            <div class="progress mt-4" style="height: 35px; display: none;" id="progressWrapper">
                                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%;">0%</div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Fine-tune Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="bg-dark rounded overflow-hidden" style="max-height: 500px;">
                        <img id="imageToCrop" style="max-width: 100%;">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <select id="aspectRatioPreset" class="form-select form-select-sm w-auto me-auto">
                        <option value="NaN">Free Crop</option>
                        <option value="1">Square (1:1)</option>
                        <option value="1.77777777778">16:9</option>
                        <option value="0.66666666666">2:3</option>
                    </select>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="cropButton" class="btn btn-success px-4">Apply Crop</button>
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
        const dropOverlay = document.getElementById('drop-overlay');

        let selectedFiles = [];
        let cropper;
        let currentFileIndex;
        let descriptionEditor;

        // Initialize Rich Text Editor
        ClassicEditor.create(document.querySelector('#description')).then(editor => {
            descriptionEditor = editor;
        });

        // Sync local array with actual hidden input state
        function syncInput() {
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            mediaInput.files = dt.files;
            renderPreviews();
        }

        // --- DRAG & DROP HANDLERS ---
        window.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropOverlay.style.display = 'flex';
        });

        window.addEventListener('dragleave', (e) => {
            if (e.relatedTarget === null || e.clientX <= 0) {
                dropOverlay.style.display = 'none';
            }
        });

        window.addEventListener('drop', (e) => {
            e.preventDefault();
            dropOverlay.style.display = 'none';
            const files = Array.from(e.dataTransfer.files);
            if (files.length > 0) {
                selectedFiles = [...selectedFiles, ...files];
                syncInput();
            }
        });

        // Standard File Input
        mediaInput.addEventListener('change', (e) => {
            selectedFiles = [...selectedFiles, ...Array.from(e.target.files)];
            syncInput();
        });

        // Clipboard Paste Handling
        window.addEventListener('paste', (e) => {
            const items = e.clipboardData.items;
            for (let item of items) {
                if (item.type.indexOf("image") !== -1) {
                    const blob = item.getAsFile();
                    const file = new File([blob], `pasted_${Date.now()}.png`, {
                        type: blob.type
                    });
                    selectedFiles.push(file);
                    syncInput();
                }
            }
        });

        function renderPreviews() {
            mediaPreviewContainer.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const div = document.createElement('div');
                div.className = 'media-preview shadow-sm';

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
                    if (videoToggle.checked && file.size < 100 * 1024 * 1024) {
                        const video = document.createElement('video');
                        video.src = URL.createObjectURL(file);
                        div.appendChild(video);
                    } else {
                        const placeholder = document.createElement('div');
                        placeholder.className = 'video-placeholder';
                        placeholder.innerHTML = `<strong>VIDEO</strong><br>${(file.size/(1024*1024)).toFixed(1)} MB`;
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

        // Cropping Management
        function openCropModal(file, index) {
            currentFileIndex = index;
            const reader = new FileReader();
            reader.onload = (e) => {
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
                selectedFiles[currentFileIndex] = new File([blob], oldFile.name, {
                    type: 'image/jpeg'
                });
                syncInput();
                bootstrap.Modal.getInstance(document.getElementById('cropModal')).hide();
            }, 'image/jpeg', 0.9);
        });

        // FORM SUBMISSION (CHUNKED UPLOAD)
        // Replace the existing submit listener in your HTML file with this:
        document.getElementById('galleryForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            const speedLabel = document.getElementById('uploadSpeed');
            const timeLabel = document.getElementById('uploadTime');
            const progressBar = document.getElementById('progressBar');
            const progressWrapper = document.getElementById('progressWrapper');

            submitBtn.disabled = true;
            progressWrapper.style.display = 'block';

            // 1. Create Gallery (Handshake)
            const metaData = new FormData();
            metaData.append('action', 'create_gallery'); // Matches Backend
            metaData.append('title', document.getElementById('title').value);
            metaData.append('description', descriptionEditor ? descriptionEditor.getData() : '');

            const initResponse = await fetch('process_gallery_create.php', {
                method: 'POST',
                body: metaData
            });
            const initResult = await initResponse.json();

            if (!initResult.success) {
                alert("Error: " + initResult.message);
                submitBtn.disabled = false;
                return;
            }

            const newGalleryId = initResult.gallery_id;
            const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB
            const totalBatchSize = selectedFiles.reduce((acc, file) => acc + file.size, 0);
            let totalBytesUploaded = 0;
            const overallStartTime = Date.now();

            // 2. Upload Chunks
            for (let file of selectedFiles) {
                const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

                for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                    const start = chunkIndex * CHUNK_SIZE;
                    const end = Math.min(start + CHUNK_SIZE, file.size);
                    const chunk = file.slice(start, end);
                    const chunkStartTime = Date.now();

                    const chunkForm = new FormData();
                    chunkForm.append('file', chunk); // Matches $_FILES['file']
                    chunkForm.append('gallery_id', newGalleryId);
                    chunkForm.append('fileName', file.name); // Matches $_POST['fileName']
                    chunkForm.append('chunkIndex', chunkIndex); // Matches $_POST['chunkIndex']
                    chunkForm.append('totalChunks', totalChunks); // Matches $_POST['totalChunks']

                    await fetch('process_gallery_create.php', {
                        method: 'POST',
                        body: chunkForm
                    });

                    totalBytesUploaded += (end - start);

                    // UI Updates
                    const duration = (Date.now() - chunkStartTime) / 1000;
                    const mbps = (((end - start) * 8) / (1024 * 1024)) / duration;
                    speedLabel.innerText = `Speed: ${mbps.toFixed(2)} Mbps`;

                    const percent = Math.round((totalBytesUploaded / totalBatchSize) * 100);
                    progressBar.style.width = percent + '%';
                    progressBar.innerText = `${percent}%`;
                }
            }
            window.location.href = `display_gallery.php?id=${newGalleryId}&msg=success`;
        });

        videoToggle.onchange = renderPreviews;
    </script>
</body>

</html>