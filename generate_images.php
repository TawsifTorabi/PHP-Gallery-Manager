<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to update a gallery.");
}

$gallery_id = (int)$_GET['gallery_id'];
$video_file = $_GET['video_file'];

$stmt = $conn->prepare("SELECT * FROM galleries WHERE id = ? AND created_by = ?");
$stmt->bind_param("ii", $gallery_id, $_SESSION['user_id']);
$stmt->execute();
$gallery = $stmt->get_result()->fetch_assoc();

if (!$gallery) {
    die("Gallery not found.");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Gallery | <?= htmlspecialchars($gallery['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-soft: #f8f9fa;
        }

        body {
            background-color: #f4f7f6;
        }

        .video-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .frames-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .frame {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid transparent;
            transition: 0.3s;
        }

        .frame.selected {
            border-color: #0d6efd;
            box-shadow: 0 0 10px rgba(13, 110, 253, 0.3);
        }

        .frame img {
            width: 100%;
            height: auto;
            display: block;
            cursor: pointer;
        }

        .frame .badge-check {
            position: absolute;
            top: 10px;
            left: 10px;
            transform: scale(1.5);
            cursor: pointer;
            z-index: 10;
        }

        .btn-remove {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            font-size: 14px;
            line-height: 1;
        }

        #progressBarContainer {
            display: none;
            margin-top: 20px;
        }

        .suggested-label {
            font-size: 0.8rem;
            background: #006da7;
            color: #000000;
            padding: 2px 8px;
            border-radius: 4px;
            position: absolute;
            bottom: 5px;
            right: 5px;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Refine Your Gallery</h2>
                <p class="text-muted">Extract high-quality stills from your video upload</p>
            </div>
            <a href="display_gallery.php?id=<?= $gallery_id ?>" class="btn btn-outline-secondary">
                &larr; Back to Gallery
            </a>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <div class="card video-card p-3 mb-4">
                    <div class="col-lg-7">
                        <div class="card video-card p-3 mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-dark" id="timestampDisplay">00:00.00</span>
                                <small class="text-muted">Use buttons below for frame-perfect selection</small>
                            </div>

                            <video id="videoPlayer" muted src="uploads/<?= htmlspecialchars($video_file); ?>" crossorigin="anonymous" class="w-100 rounded mb-3" controls></video>

                            <div class="d-flex justify-content-center gap-2 mb-3">
                                <button class="btn btn-outline-secondary" onclick="seekFrames(-5)">« -5s</button>
                                <button class="btn btn-secondary" onclick="seekFrames(-1)">‹ Prev Frame</button>
                                <button id="captureButton" class="btn btn-primary px-5">Capture Current Frame</button>
                                <button class="btn btn-secondary" onclick="seekFrames(1)">Next Frame ›</button>
                                <button class="btn btn-outline-secondary" onclick="seekFrames(5)">+5s »</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card video-card p-4">
                    <h5>Ready to Upload?</h5>
                    <p class="small text-muted">Review the frames captured below. We've also suggested a few random frames to get you started.</p>

                    <button id="uploadAllBtn" class="btn btn-success w-100 btn-lg mb-2" disabled>
                        Upload <span id="frameCounter">0</span> Frames
                    </button>
                    <button class="btn btn-outline-secondary w-100 mb-2" onclick="generateRandomFrames()">Generate 5 Random Frames</button>


                    <div id="progressBarContainer">
                        <div class="progress" style="height: 25px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
                        </div>
                        <p id="statusText" class="text-center mt-2 small">Preparing chunks...</p>
                    </div>

                    <div class="col-12">
                        <div class="d-flex justify-content-between">
                            <h4>Captured & Suggested Frames</h4>
                            <button title="Clear All Frames" class="btn btn-sm btn-link text-danger" onclick="clearAllFrames()">🗑</button>
                        </div>
                        <div class="frames-container" id="framesContainer">
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-5">

        <div class="row">

        </div>
    </div>

    <script>
        const video = document.getElementById('videoPlayer');
        const framesContainer = document.getElementById('framesContainer');
        const uploadBtn = document.getElementById('uploadAllBtn');
        const frameCounter = document.getElementById('frameCounter');
        let capturedFiles = []; // Array of {id, file, dataUrl}

        // --- 1. AUTO-SUGGESTION LOGIC ---
        // Suggest 5 random frames when video is ready
        function generateRandomFrames() {
            const duration = video.duration;
            for (let i = 0; i < 4; i++) {
                const randomTime = Math.random() * duration;
                generateHiddenFrame(randomTime, true);
            }
        }

        function generateHiddenFrame(time, isSuggested) {
            const tempVideo = document.createElement('video');
            tempVideo.src = video.src;
            tempVideo.crossOrigin = "anonymous";
            tempVideo.currentTime = time;
            tempVideo.addEventListener('seeked', () => {
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(tempVideo, 0, 0);
                addFrameToUI(canvas.toDataURL('image/jpeg', 0.9), isSuggested);
            }, {
                once: true
            });
        }

        // --- 2. UI & CAPTURE ---
        document.getElementById('captureButton').addEventListener('click', () => {
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            addFrameToUI(canvas.toDataURL('image/jpeg', 0.9), false);
        });

        function addFrameToUI(dataUrl, isSuggested) {
            const id = 'frame_' + Date.now() + Math.random().toString(36).substr(2, 5);
            const blob = dataURLtoBlob(dataUrl);
            const file = new File([blob], `${id}.jpg`, {
                type: 'image/jpeg'
            });

            capturedFiles.push({
                id,
                file,
                dataUrl
            });

            const frameDiv = document.createElement('div');
            frameDiv.className = 'frame selected';
            frameDiv.id = id;
            frameDiv.innerHTML = `
                <input type="checkbox" class="badge-check" checked onclick="toggleFrame('${id}')">
                <img src="${dataUrl}" onclick="toggleFrame('${id}')">
                ${isSuggested ? '<span class="suggested-label">Suggested</span>' : ''}
                <button class="btn-remove" onclick="removeFrame('${id}')">&times;</button>
            `;
            framesContainer.prepend(frameDiv);
            updateUIState();
        }

        function removeFrame(id) {
            document.getElementById(id).remove();
            capturedFiles = capturedFiles.filter(f => f.id !== id);
            updateUIState();
        }

        function toggleFrame(id) {
            const el = document.getElementById(id);
            const cb = el.querySelector('input');
            cb.checked = !cb.checked;
            el.classList.toggle('selected', cb.checked);
            updateUIState();
        }

        function updateUIState() {
            const selectedCount = framesContainer.querySelectorAll('input:checked').length;
            frameCounter.innerText = selectedCount;
            uploadBtn.disabled = selectedCount === 0;
        }

        function clearAllFrames() {
            framesContainer.innerHTML = '';
            capturedFiles = [];
            updateUIState();
        }

        // --- 3. CHUNKED UPLOAD LOGIC (Aligned with your PHP) ---
        uploadBtn.addEventListener('click', async () => {
            const selectedFrames = capturedFiles.filter(f =>
                document.getElementById(f.id).querySelector('input').checked
            );

            uploadBtn.disabled = true;
            document.getElementById('progressBarContainer').style.display = 'block';

            for (let i = 0; i < selectedFrames.length; i++) {
                const item = selectedFrames[i];
                document.getElementById('statusText').innerText = `Uploading frame ${i+1} of ${selectedFrames.length}...`;
                await uploadInChunks(item.file);
            }

            // alert('All selected frames uploaded!');
            window.location.href = `display_gallery.php?id=<?= $gallery_id ?>?msg=Frames uploaded successfully!`;
        });

        async function uploadInChunks(file) {
            const CHUNK_SIZE = 1024 * 512; // 512KB chunks
            const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
            const identifier = Math.random().toString(36).substring(2) + Date.now();

            for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                const start = chunkIndex * CHUNK_SIZE;
                const end = Math.min(start + CHUNK_SIZE, file.size);
                const chunk = file.slice(start, end);

                const formData = new FormData();
                formData.append('gallery_id', '<?= $gallery_id ?>');
                formData.append('identifier', identifier);
                formData.append('chunk_index', chunkIndex);
                formData.append('total_chunks', totalChunks);
                formData.append('filename', file.name);
                formData.append('file_chunk', chunk);

                await fetch('gallery_update.php', {
                    method: 'POST',
                    body: formData
                });

                // Update progress bar
                const overallProgress = ((chunkIndex + 1) / totalChunks) * 100;
                const pb = document.getElementById('progressBar');
                pb.style.width = overallProgress + '%';
                pb.textContent = Math.round(overallProgress) + '%';
            }
        }

        // --- HELPER ---
        function dataURLtoBlob(dataurl) {
            var arr = dataurl.split(','),
                mime = arr[0].match(/:(.*?);/)[1],
                bstr = atob(arr[1]),
                n = bstr.length,
                u8arr = new Uint8Array(n);
            while (n--) u8arr[n] = bstr.charCodeAt(n);
            return new Blob([u8arr], {
                type: mime
            });
        }


        const timestampDisplay = document.getElementById('timestampDisplay');
        const frameRate = 30; // Standard assumption, can be adjusted

        // Update timestamp display whenever the video moves
        video.addEventListener('timeupdate', () => {
            const mins = Math.floor(video.currentTime / 60);
            const secs = (video.currentTime % 60).toFixed(2);
            timestampDisplay.innerText = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        });

        /**
         * Seeks the video by a specific number of frames or seconds
         * @param {number} direction - Positive to go forward, negative to go back
         */
        function seekFrames(direction) {
            video.pause(); // Pause video when fine-tuning

            // If direction is 1 or -1, we move by 1 frame (approx 1/30th of a second)
            // If direction is 5 or -5, we move by 5 seconds
            const amount = (Math.abs(direction) === 1) ? (1 / frameRate) : direction;

            video.currentTime += (direction > 0 ? amount : -amount);
        }

        // Optional: Keyboard shortcuts for power users
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') seekFrames(-1);
            if (e.key === 'ArrowRight') seekFrames(1);
            if (e.key === 'Enter') document.getElementById('captureButton').click();
        });
    </script>
</body>

</html>