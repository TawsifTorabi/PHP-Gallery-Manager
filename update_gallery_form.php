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
                <<div class="modal-footer">
                    <button type="button" id="autoDetectBtn" class="btn btn-outline-primary btn-sm me-auto">
                        ✨ Auto-Detect
                    </button>
                    <select id="aspectRatioPreset" class="form-select form-select-sm w-auto me-2">
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
                    if (videoToggle.checked && file.size < 100 * 1024 * 1024) { // Only preview if < 100MB
                        const video = document.createElement('video');
                        video.src = URL.createObjectURL(file);
                        video.onloadedmetadata = () => URL.revokeObjectURL(video.src); // Clean up memory
                        video.controls = true;
                        div.appendChild(video);
                    } else {
                        const placeholder = document.createElement('div');
                        placeholder.className = 'video-placeholder';
                        placeholder.innerHTML = `Large Video<br>(${Math.round(file.size/1024/1024)}MB)`;
                        div.appendChild(placeholder);
                    }
                }
                mediaPreviewContainer.appendChild(div);
            });
        }

        function removeFile(index) {
            // Find the preview element and revoke its object URL if it exists
            const preview = mediaPreviewContainer.children[index];
            const media = preview.querySelector('img, video');
            if (media && media.src.startsWith('blob:')) {
                URL.revokeObjectURL(media.src);
            }
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
        document.getElementById('galleryForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (descriptionEditor) document.getElementById('description').value = descriptionEditor.getData();

            const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB
            const submitBtn = document.getElementById('submitBtn');
            const totalFiles = selectedFiles.length;

            // UI Elements
            const speedLabel = document.getElementById('uploadSpeed');
            const timeLabel = document.getElementById('uploadTime');
            const progressWrapper = document.getElementById('progressWrapper');
            const progressBar = document.getElementById('progressBar');

            submitBtn.disabled = true;
            progressWrapper.style.display = 'block';

            // Calculate Total Size for the whole batch
            const totalBatchSize = selectedFiles.reduce((acc, file) => acc + file.size, 0);
            let totalBytesUploaded = 0;
            const overallStartTime = Date.now();

            // 1. Meta Data Sync (Quick Title/Desc Update)
            const metaData = new FormData();
            metaData.append('gallery_id', '<?php echo $gallery_id; ?>');
            metaData.append('title', document.getElementById('title').value);
            metaData.append('description', document.getElementById('description').value);
            metaData.append('is_meta_only', 'true');
            await fetch('gallery_update.php', {
                method: 'POST',
                body: metaData
            });

            // 2. File Upload Loop
            for (let fileIndex = 0; fileIndex < totalFiles; fileIndex++) {
                const file = selectedFiles[fileIndex];
                const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
                const identifier = btoa(file.name + file.size + '<?php echo $gallery_id; ?>').replace(/=/g, '');

                for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                    let success = false;

                    while (!success) {
                        try {
                            const chunkStartTime = Date.now();
                            const start = chunkIndex * CHUNK_SIZE;
                            const end = Math.min(start + CHUNK_SIZE, file.size);
                            const chunk = file.slice(start, end);

                            const chunkForm = new FormData();
                            chunkForm.append('file_chunk', chunk);
                            chunkForm.append('chunk_index', chunkIndex);
                            chunkForm.append('total_chunks', totalChunks);
                            chunkForm.append('identifier', identifier);
                            chunkForm.append('filename', file.name);
                            chunkForm.append('gallery_id', '<?php echo $gallery_id; ?>');

                            const response = await fetch('gallery_update.php', {
                                method: 'POST',
                                body: chunkForm
                            });

                            if (!response.ok) throw new Error("Server Error");

                            success = true;

                            // --- SPEED & ETA CALCULATIONS ---
                            totalBytesUploaded += (end - start);

                            // 1. Calculate Duration and Bytes for this chunk
                            const chunkEndTime = Date.now();
                            const durationInSeconds = (chunkEndTime - chunkStartTime) / 1000;

                            // 2. Convert to Megabits (Bytes * 8 / 1024 / 1024)
                            const bitsUploaded = (end - start) * 8;
                            const mbps = (bitsUploaded / (1024 * 1024)) / durationInSeconds;

                            // 3. Display with "Mbps" label
                            if (mbps > 1000) {
                                speedLabel.innerText = `Speed: ${(mbps / 1024).toFixed(2)} Gbps`;
                            } else {
                                speedLabel.innerText = `Speed: ${mbps.toFixed(2)} Mbps`;
                            }

                            // 4. Calculate ETA using Average Speed (in Bytes per second)
                            const totalDurationSoFar = (Date.now() - overallStartTime) / 1000;
                            const avgSpeedBytes = totalBytesUploaded / totalDurationSoFar;
                            const bytesRemaining = totalBatchSize - totalBytesUploaded;
                            const secondsRemaining = bytesRemaining / avgSpeedBytes;

                            if (secondsRemaining > 0) {
                                const hours = Math.floor(secondsRemaining / 3600);
                                const mins = Math.floor((secondsRemaining % 3600) / 60);
                                const secs = Math.round(secondsRemaining % 60);

                                let timeStr = `Time Remaining: `;
                                if (hours > 0) timeStr += `${hours}h `;
                                timeStr += `${mins}m ${secs}s`;
                                timeLabel.innerText = timeStr;
                            }

                            // 5. Update Progress Bar with GB units
                            const overallPercent = (totalBytesUploaded / totalBatchSize) * 100;
                            progressBar.style.width = overallPercent + '%';
                            const uploadedGB = (totalBytesUploaded / (1024 ** 3)).toFixed(2);
                            const totalGB = (totalBatchSize / (1024 ** 3)).toFixed(2);
                            progressBar.innerHTML = `${Math.round(overallPercent)}% (${uploadedGB}GB / ${totalGB}GB)`;

                        } catch (error) {
                            console.error("Upload failed", error);
                            progressBar.classList.replace('bg-primary', 'bg-danger');

                            let retryBtn = document.getElementById('retryBtn');
                            if (!retryBtn) {
                                retryBtn = document.createElement('button');
                                retryBtn.id = 'retryBtn';
                                retryBtn.className = "btn btn-warning w-100 mt-2";
                                retryBtn.innerText = "Connection Lost. Click to Retry";
                                progressWrapper.after(retryBtn);
                            }
                            retryBtn.style.display = 'block';

                            await new Promise(resolve => {
                                retryBtn.onclick = () => {
                                    retryBtn.style.display = 'none';
                                    progressBar.classList.replace('bg-danger', 'bg-primary');
                                    resolve();
                                };
                            });
                        }
                    }
                }
            }
            window.location.href = 'display_gallery.php?id=<?php echo $gallery_id; ?>&msg=true';
        });

        videoToggle.onchange = renderPreviews;


        document.getElementById('autoDetectBtn').addEventListener('click', () => {
            const img = document.getElementById('imageToCrop');

            // Try Edge Detection first (Best for blurred/complex backgrounds)
            let bounds = detectSubjectInBlur(img);

            // Fallback to Color Detection (Best for solid white/black backdrops)
            if (!bounds) {
                bounds = detectContentBounds(img); // Your previous function
            }

            if (bounds && cropper) {
                cropper.setAspectRatio(NaN);
                cropper.setData({
                    x: bounds.left,
                    y: bounds.top,
                    width: bounds.width,
                    height: bounds.height
                });
            }
        });

        function detectContentBounds(imageElement) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');

            // Use natural dimensions for accuracy
            canvas.width = imageElement.naturalWidth;
            canvas.height = imageElement.naturalHeight;
            ctx.drawImage(imageElement, 0, 0);

            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const data = imageData.data;
            const w = canvas.width;
            const h = canvas.height;

            // 1. Sample the 4 corners to find the background color
            const corners = [
                getPixel(data, 0, 0, w), // Top-Left
                getPixel(data, w - 1, 0, w), // Top-Right
                getPixel(data, 0, h - 1, w), // Bottom-Left
                getPixel(data, w - 1, h - 1, w) // Bottom-Right
            ];

            // Average the corners to get a baseline Background Color
            const bg = {
                r: corners.reduce((a, b) => a + b.r, 0) / 4,
                g: corners.reduce((a, b) => a + b.g, 0) / 4,
                b: corners.reduce((a, b) => a + b.b, 0) / 4
            };

            let minX = w,
                minY = h,
                maxX = 0,
                maxY = 0;
            let found = false;

            // 2. Tolerance: How different must a pixel be to be "content"?
            // A lower number (like 15) makes it more sensitive to "almost white" pixels.
            const tolerance = 15;

            for (let y = 0; y < h; y++) {
                for (let x = 0; x < w; x++) {
                    const p = getPixel(data, x, y, w);
                    const diff = Math.sqrt(
                        Math.pow(p.r - bg.r, 2) +
                        Math.pow(p.g - bg.g, 2) +
                        Math.pow(p.b - bg.b, 2)
                    );

                    if (diff > tolerance) {
                        if (x < minX) minX = x;
                        if (x > maxX) maxX = x;
                        if (y < minY) minY = y;
                        if (y > maxY) maxY = y;
                        found = true;
                    }
                }
            }

            if (!found) return null;

            // 2. SET PADDING TO ZERO (OR NEGATIVE)
            // If you want it exactly on the edge, use 0. 
            // If you want to "bite" into the image slightly to ensure NO white, use -2.
            const padding = 0;

            return {
                left: Math.max(0, minX - padding),
                top: Math.max(0, minY - padding),
                width: Math.min(w, (maxX - minX) + (padding * 2)),
                height: Math.min(h, (maxY - minY) + (padding * 2))
            };
        }

        function detectSubjectInBlur(imageElement) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const w = imageElement.naturalWidth;
            const h = imageElement.naturalHeight;
            canvas.width = w;
            canvas.height = h;
            ctx.drawImage(imageElement, 0, 0);

            const imageData = ctx.getImageData(0, 0, w, h);
            const data = imageData.data;
            const grayscale = new Uint8Array(w * h);

            // 1. Grayscale conversion
            for (let i = 0; i < data.length; i += 4) {
                grayscale[i / 4] = (data[i] * 0.299 + data[i + 1] * 0.587 + data[i + 2] * 0.114);
            }

            let minX = w,
                minY = h,
                maxX = 0,
                maxY = 0;
            let found = false;

            // 2. Sobel Pass with High Sensitivity
            // Increase threshold to ignore soft blur, decrease to catch fine details
            const edgeThreshold = 50;

            for (let y = 1; y < h - 1; y++) {
                for (let x = 1; x < w - 1; x++) {
                    const idx = y * w + x;

                    const gh =
                        (-1 * grayscale[idx - w - 1]) + (1 * grayscale[idx - w + 1]) +
                        (-2 * grayscale[idx - 1]) + (2 * grayscale[idx + 1]) +
                        (-1 * grayscale[idx + w - 1]) + (1 * grayscale[idx + w + 1]);

                    const gv =
                        (-1 * grayscale[idx - w - 1]) + (-2 * grayscale[idx - w]) + (-1 * grayscale[idx - w + 1]) +
                        (1 * grayscale[idx + w - 1]) + (2 * grayscale[idx + w]) + (1 * grayscale[idx + w + 1]);

                    const magnitude = Math.sqrt(gh * gh + gv * gv);

                    if (magnitude > edgeThreshold) {
                        if (x < minX) minX = x;
                        if (x > maxX) maxX = x;
                        if (y < minY) minY = y;
                        if (y > maxY) maxY = y;
                        found = true;
                    }
                }
            }

            if (!found) return null;

            // 3. TIGHTNESS CONTROL
            // Set padding to 0 for a pixel-perfect hug of the edges.
            // Set to -5 if you want to slightly "shave" the edges to ensure NO background remains.
            const padding = 0;

            return {
                left: Math.max(0, minX - padding),
                top: Math.max(0, minY - padding),
                width: Math.min(w, (maxX - minX) + (padding * 2)),
                height: Math.min(h, (maxY - minY) + (padding * 2))
            };
        }

        // Helper to extract RGB from the flat Uint8ClampedArray
        function getPixel(data, x, y, width) {
            const i = (y * width + x) * 4;
            return {
                r: data[i],
                g: data[i + 1],
                b: data[i + 2]
            };
        }
    </script>
</body>

</html>