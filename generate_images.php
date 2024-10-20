<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to update a gallery.");
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .frames-container {
            display: flex;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .frame {
            margin: 10px;
            position: relative;
        }

        .frame img {
            max-width: 200px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .frame button {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 0px;
            cursor: pointer;
            border-radius: 27%;
            width: 39px;
            font-size: 23px;
            height: 43px;
            font-weight: bold;
        }

        .frame input[type="checkbox"] {
            position: absolute;
            top: 20px;
            left: 20px;
            transform: scale(2.5);
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h2>Update Gallery</h2>



        <div class="container mt-4">
            <!-- Video Section -->
            <div class="row">
                <div class="col-12 col-md-8 mx-auto">
                    <div class="card p-3">
                        <div class="mb-3">
                            <label for="videoInput" class="form-label">Select a video file:</label>
                            <input type="file" accept="video/*" id="videoInput" class="form-control" />
                        </div>
                        <video id="videoPlayer" controls class="w-100 rounded mb-3"></video>
                        <button id="captureButton" class="btn btn-primary" disabled>Capture Frame</button>
                    </div>
                </div>
            </div>

            <!-- Captured Frames -->
            <div class="row">
                <div class="col-12">
                    <h5 class="mt-4">Captured Frames:</h5>
                    <div class="frames-container" id="framesContainer"></div>
                </div>
            </div>

            <!-- Hidden form input and submit button -->
            <form id="uploadForm" class="mt-4" action="gallery_update.php?id=<?php echo $gallery_id; ?>" method="post" enctype="multipart/form-data">
                <input type="file" id="capturedFrames" name="media[]" multiple hidden />
                <button type="submit" class="btn btn-success">Upload Selected Frames</button>
                <div class="progress mb-3">
                    <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                </div>
            </form>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(event) {
            event.preventDefault();

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

            xhr.onload = function() {
                if (xhr.status === 200) {
                    alert('Gallery updated successfully!');
                    window.location.href = 'dashboard.php'; // Redirect on success
                } else {
                    alert('Failed to update gallery. Please try again.');
                }
            };

            xhr.onerror = function() {
                alert('An error occurred while uploading the files.');
            };

            xhr.send(formData);
        });
    </script>

    <script>
        document.getElementById('videoInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const videoPlayer = document.getElementById('videoPlayer');
                const url = URL.createObjectURL(file);
                videoPlayer.src = url;
                videoPlayer.load();
                document.getElementById('captureButton').disabled = false;
            }
        });

        document.getElementById('captureButton').addEventListener('click', function() {
            const video = document.getElementById('videoPlayer');
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');

            // Set canvas size equal to video size
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            // Draw the current frame onto the canvas
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            // Convert canvas to image data URL
            const frameURL = canvas.toDataURL('image/png');

            // Display the captured frame
            displayCapturedFrame(frameURL);
        });

        function displayCapturedFrame(frameURL) {
            const framesContainer = document.getElementById('framesContainer');

            // Create a div for the frame
            const frameDiv = document.createElement('div');
            frameDiv.classList.add('frame');

            // Create image element for the frame
            const img = document.createElement('img');
            img.src = frameURL;

            // Create checkbox to select the frame
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = true; // Automatically checked by default

            // Create remove button
            const removeButton = document.createElement('button');
            removeButton.innerHTML = '&times;'; // Cross icon for remove

            // Append the frame, checkbox, and remove button to the container
            frameDiv.appendChild(img);
            frameDiv.appendChild(checkbox);
            frameDiv.appendChild(removeButton);
            framesContainer.appendChild(frameDiv);

            // Attach the frame to the form input if selected
            checkbox.addEventListener('change', function() {
                updateCapturedFramesInput(frameURL, checkbox.checked);
            });

            // Handle removing the frame
            removeButton.addEventListener('click', function() {
                removeCapturedFrame(frameDiv, frameURL);
            });

            // Add the captured frame to the hidden input by default
            updateCapturedFramesInput(frameURL, true);
        }

        function updateCapturedFramesInput(frameURL, isChecked) {
            const inputFile = document.getElementById('capturedFrames');
            const dataTransfer = new DataTransfer(); // To manage the input files

            // Get existing files
            for (let i = 0; i < inputFile.files.length; i++) {
                dataTransfer.items.add(inputFile.files[i]);
            }

            if (isChecked) {
                // Create a new file from the data URL
                const file = dataURLtoFile(frameURL, `frame_${Date.now()}.png`);
                dataTransfer.items.add(file);
            } else {
                // Remove the file from the selection
                const filteredFiles = Array.from(inputFile.files).filter(file => file.name !== `frame_${Date.now()}.png`);
                filteredFiles.forEach(file => dataTransfer.items.add(file));
            }

            inputFile.files = dataTransfer.files;
        }

        // Function to remove the frame both visually and from input
        function removeCapturedFrame(frameDiv, frameURL) {
            // Remove the frame from the DOM
            frameDiv.remove();

            // Remove the frame from the input files
            updateCapturedFramesInput(frameURL, false);
        }

        // Convert Data URL to File object
        function dataURLtoFile(dataurl, filename) {
            const arr = dataurl.split(',');
            const mime = arr[0].match(/:(.*?);/)[1];
            const bstr = atob(arr[1]);
            let n = bstr.length;
            const u8arr = new Uint8Array(n);
            while (n--) {
                u8arr[n] = bstr.charCodeAt(n);
            }
            return new File([u8arr], filename, {
                type: mime
            });
        }
    </script>
</body>

</html>