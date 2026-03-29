<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to update a gallery.");
}

// Securely get the Gallery ID
$gallery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch the gallery details to ensure ownership
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
    <title>Update Gallery | Advanced Editor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7f6;
        }

        /* Full Page Drag Overlay */
        #drop-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 110, 253, 0.4);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
            pointer-events: none;
            border: 10px dashed white;
        }

        /* Manual Zone Styling */
        #manualZone.dragover {
            border: 3px solid #0d6efd;
            background-color: #e9f2ff;
        }

        /* Bulk Zone Styling */
        .bulk-drop-zone {
            border: 3px dashed #6c757d;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            background: #fff;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            cursor: pointer;
        }

        .bulk-drop-zone.dragover {
            border-color: #198754;
            background-color: #f0fff4;
        }

        /* Captured Frame Styling */
        .frames-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }

        .frame {
            position: relative;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 5px;
            background: #fff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .frame img {
            max-width: 200px;
            height: auto;
            border-radius: 5px;
            display: block;
        }

        .frame button.remove-btn {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            z-index: 30;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .frame input[type="checkbox"] {
            position: absolute;
            top: 10px;
            left: 10px;
            transform: scale(2.0);
            z-index: 30;
            cursor: pointer;
        }

        .video-container {
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }
    </style>
</head>

<body>

    <?php include 'navbar.php'; ?>


    <div id="drop-overlay">DROP TO ADD TO GALLERY</div>

    <div class="container mt-5 pb-5">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1>Update Gallery: <?php echo htmlspecialchars($gallery['title']); ?></h1>
                    <a href="display_gallery.php?id=<?php echo $gallery_id; ?>" class="btn btn-outline-secondary">Back to Gallery</a>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7" id="manualZone">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h4 class="card-title">1. Manual Frame Selector</h4>
                        <p class="text-muted small">Drop a video here to scrub and pick specific moments.</p>

                        <div class="mb-3">
                            <input type="file" accept="video/*" id="videoInput" class="form-control" />
                        </div>

                        <div class="video-container mb-3">
                            <video id="videoPlayer" controls muted class="w-100" style="max-height: 400px;"></video>
                        </div>

                        <button id="captureButton" class="btn btn-primary w-100 py-2 fw-bold" disabled>
                            📸 Capture Current Frame
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div id="bulkZone" class="bulk-drop-zone shadow-sm">
                    <h4 class="mt-2">2. Bulk Randomizer</h4>
                    <p class="text-muted">Drop multiple videos/images here.</p>
                    <div class="display-1 mb-3">📂</div>
                    <p class="small text-secondary">Videos dropped here will auto-extract a random "Frozen Clip" frame.</p>
                </div>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Queue: <span id="queueCount" class="badge bg-primary">0</span> Images</h5>
                            <button type="submit" form="uploadForm" id="submitBtn" class="btn btn-success btn-lg px-5" disabled>
                                Upload All to Server
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="progress mb-2 d-none" id="progressWrapper" style="height: 25px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                role="progressbar" style="width: 0%">0%</div>
                        </div>
                        <div class="d-flex justify-content-between mb-4 small text-muted d-none" id="statsWrapper">
                            <span id="uploadSpeed">Speed: 0 Mbps</span>
                            <span id="uploadTime">Time Remaining: --</span>
                        </div>

                        <form id="uploadForm" action="gallery_update.php?id=<?php echo $gallery_id; ?>&input_src=image_from_video" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="title" value="<?php echo htmlspecialchars($gallery['title']); ?>">
                            <textarea name="description" hidden><?php echo htmlspecialchars($gallery['description']); ?></textarea>

                            <div class="frames-container" id="framesContainer"></div>
                        </form>

                        <div id="emptyMsg" class="text-center py-5 text-muted">
                            <p>No frames captured yet. Use the tools above to start.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const videoInput = document.getElementById('videoInput');
        const videoPlayer = document.getElementById('videoPlayer');
        const captureButton = document.getElementById('captureButton');
        const framesContainer = document.getElementById('framesContainer');
        const queueCountDisplay = document.getElementById('queueCount');
        const submitBtn = document.getElementById('submitBtn');
        const dropOverlay = document.getElementById('drop-overlay');
        const emptyMsg = document.getElementById('emptyMsg');

        // This array stores the actual File objects for the AJAX upload
        let fileQueue = [];

        // --- 1. FILE INPUT (MANUAL) ---
        videoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) loadVideoIntoPlayer(file);
        });

        function loadVideoIntoPlayer(file) {
            const url = URL.createObjectURL(file);
            videoPlayer.src = url;
            videoPlayer.load();
            captureButton.disabled = false;
        }

        // --- 2. DRAG AND DROP LOGIC (SMART ROUTING) ---
        window.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropOverlay.style.display = 'flex';
        });

        window.addEventListener('dragleave', (e) => {
            if (e.relatedTarget === null) dropOverlay.style.display = 'none';
        });

        window.addEventListener('drop', (e) => {
            e.preventDefault();
            dropOverlay.style.display = 'none';

            const files = e.dataTransfer.files;
            if (!files.length) return;

            // Check if dropped specifically in the Manual Zone
            const manualZone = document.getElementById('manualZone');
            const isManualDrop = manualZone.contains(e.target);

            if (isManualDrop && files[0].type.startsWith('video/')) {
                // Route to Manual Player
                loadVideoIntoPlayer(files[0]);
            } else {
                // Route to Bulk Logic
                handleBulkFiles(files);
            }
        });

        function handleBulkFiles(files) {
            Array.from(files).forEach(file => {
                if (file.type.startsWith('image/')) {
                    addFileToQueue(file);
                } else if (file.type.startsWith('video/')) {
                    extractRandomFrame(file);
                }
            });
        }

        // --- 3. FRAME EXTRACTION ---

        // Manual Capture
        captureButton.addEventListener('click', function(e) {
            e.preventDefault();
            const canvas = document.createElement('canvas');
            canvas.width = videoPlayer.videoWidth;
            canvas.height = videoPlayer.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(videoPlayer, 0, 0, canvas.width, canvas.height);

            canvas.toBlob((blob) => {
                const file = new File([blob], `manual_${Date.now()}.png`, {
                    type: 'image/png'
                });
                addFileToQueue(file);
            }, 'image/png');
        });

        // Auto Extraction (Frozen Clips)
        function extractRandomFrame(file) {
            const tempVideo = document.createElement('video');
            tempVideo.src = URL.createObjectURL(file);
            tempVideo.muted = true;

            tempVideo.onloadedmetadata = () => {
                // Seek to a random point (between 5% and 90% of duration)
                const startTime = tempVideo.duration * 0.05;
                const endTime = tempVideo.duration * 0.9;
                tempVideo.currentTime = startTime + Math.random() * (endTime - startTime);
            };

            tempVideo.onseeked = () => {
                const canvas = document.createElement('canvas');
                canvas.width = tempVideo.videoWidth;
                canvas.height = tempVideo.videoHeight;
                canvas.getContext('2d').drawImage(tempVideo, 0, 0);

                canvas.toBlob((blob) => {
                    const newFile = new File([blob], `auto_${Date.now()}.png`, {
                        type: 'image/png'
                    });
                    addFileToQueue(newFile);
                    URL.revokeObjectURL(tempVideo.src); // Cleanup
                }, 'image/png');
            };
        }

        // --- 4. QUEUE & UI MANAGEMENT ---
        function addFileToQueue(file) {
            // Assign a unique ID so we don't delete others by mistake
            const uid = 'img_' + Math.random().toString(36).substr(2, 9);
            file.uid = uid;
            fileQueue.push(file);

            // Create Visual Element
            const frameDiv = document.createElement('div');
            frameDiv.className = 'frame';
            frameDiv.dataset.id = uid;

            const imgUrl = URL.createObjectURL(file);

            frameDiv.innerHTML = `
                <img src="${imgUrl}">
                <input type="checkbox" checked onchange="handleCheckbox('${uid}', this.checked)">
                <button type="button" class="remove-btn" onclick="removeFromQueue('${uid}')">&times;</button>
            `;

            framesContainer.appendChild(frameDiv);
            updateUI();
        }

        function handleCheckbox(uid, isChecked) {
            if (!isChecked) removeFromQueue(uid);
        }

        function removeFromQueue(uid) {
            // Remove from the JS Array
            fileQueue = fileQueue.filter(f => f.uid !== uid);

            // Remove from the DOM
            const element = document.querySelector(`.frame[data-id="${uid}"]`);
            if (element) element.remove();

            updateUI();
        }

        function updateUI() {
            const count = fileQueue.length;
            queueCountDisplay.innerText = count;
            submitBtn.disabled = (count === 0);
            emptyMsg.style.display = (count === 0) ? 'block' : 'none';
        }

        // --- 5. AJAX UPLOAD (FIXED) ---
        document.getElementById('uploadForm').onsubmit = async function(e) {
            e.preventDefault();

            const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB Chunks
            const submitBtn = document.getElementById('submitBtn');
            const bar = document.getElementById('progressBar');
            const wrapper = document.getElementById('progressWrapper');
            const statsWrapper = document.getElementById('statsWrapper');
            const speedLabel = document.getElementById('uploadSpeed');
            const timeLabel = document.getElementById('uploadTime');

            // UI State
            wrapper.classList.remove('d-none');
            statsWrapper.classList.remove('d-none');
            submitBtn.disabled = true;

            // Global Progress Tracking
            const totalBatchSize = fileQueue.reduce((acc, file) => acc + file.size, 0);
            let totalBytesUploaded = 0;
            const overallStartTime = Date.now();

            // Process each file in the queue one by one
            for (let i = 0; i < fileQueue.length; i++) {
                const file = fileQueue[i];
                const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

                // Generate a unique identifier for this file
                const identifier = btoa(file.name + file.size + '<?php echo $gallery_id; ?>').replace(/=/g, '');

                for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                    const start = chunkIndex * CHUNK_SIZE;
                    const end = Math.min(start + CHUNK_SIZE, file.size);
                    const chunk = file.slice(start, end);
                    const chunkStartTime = Date.now();

                    const formData = new FormData();
                    formData.append('file_chunk', chunk);
                    formData.append('chunk_index', chunkIndex);
                    formData.append('total_chunks', totalChunks);
                    formData.append('identifier', identifier);
                    formData.append('filename', file.name);
                    formData.append('gallery_id', '<?php echo $gallery_id; ?>');
                    // Check if it's a video for your worker logic
                    formData.append('is_video', file.type.startsWith('video/') ? '1' : '0');

                    try {
                        const response = await fetch('gallery_update.php', {
                            method: 'POST',
                            body: formData
                        });

                        if (!response.ok) throw new Error("Server Error");

                        // --- SPEED & ETA CALCULATIONS ---
                        const uploadedInThisChunk = (end - start);
                        totalBytesUploaded += uploadedInThisChunk;

                        const chunkDuration = (Date.now() - chunkStartTime) / 1000;

                        // Calculate Speed in Mbps (Megabits)
                        const mbps = ((uploadedInThisChunk * 8) / (1024 * 1024)) / chunkDuration;
                        speedLabel.innerText = `Speed: ${mbps.toFixed(2)} Mbps`;

                        // Calculate ETA
                        const totalElapsed = (Date.now() - overallStartTime) / 1000;
                        const avgSpeedBytes = totalBytesUploaded / totalElapsed;
                        const remainingBytes = totalBatchSize - totalBytesUploaded;
                        const secondsLeft = remainingBytes / avgSpeedBytes;

                        if (secondsLeft > 0) {
                            const mins = Math.floor(secondsLeft / 60);
                            const secs = Math.round(secondsLeft % 60);
                            timeLabel.innerText = `Time Remaining: ${mins}m ${secs}s`;
                        }

                        // Update Progress Bar
                        const percent = (totalBytesUploaded / totalBatchSize) * 100;
                        bar.style.width = percent + '%';

                        // Units in GB
                        const uploadedGB = (totalBytesUploaded / (1024 ** 3)).toFixed(2);
                        const totalGB = (totalBatchSize / (1024 ** 3)).toFixed(2);
                        bar.innerText = `${Math.round(percent)}% (${uploadedGB}GB / ${totalGB}GB)`;

                    } catch (error) {
                        console.error(error);
                        alert("Upload failed at chunk " + chunkIndex + ". Check your laptop server connection.");
                        submitBtn.disabled = false;
                        return; // Stop the loop on error
                    }
                }
            }

            // Success: Redirect back to the gallery
            window.location.href = `display_gallery.php?id=<?php echo $gallery_id; ?>&msg=success`;
        };
    </script>
</body>

</html>