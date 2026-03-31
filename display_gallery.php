<?php
include 'session.php';
require 'db.php';
date_default_timezone_set('Asia/Dhaka');

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to view galleries.");
}

$user_id = $_SESSION['user_id'];
$gallery_id = $_GET['id'];
$message = isset($_GET['msg_content']) ? $_GET['msg_content'] : null;

// Fetch galleries created by the logged-in user
$stmt = $conn->prepare("SELECT * FROM galleries WHERE created_by = ? AND id = ?");
$stmt->bind_param("ii", $user_id, $gallery_id);
$stmt->execute();
$galleries = $stmt->get_result();

// Fetch the number of images and videos, and the last updated time for the gallery
$stmt_media = $conn->prepare("SELECT id, file_type, dimension, status, progress, file_name, uploaded_at FROM images WHERE gallery_id = ? ORDER BY uploaded_at DESC");
$stmt_media->bind_param("i", $gallery_id);
$stmt_media->execute();
$media_result = $stmt_media->get_result();

$images_count = 0;
$videos_count = 0;
$last_updated = null;

while ($media = $media_result->fetch_assoc()) {
    if ($media['file_type'] == 'image') {
        $images_count++;
    } else {
        $videos_count++;
    }
    if (!$last_updated) {
        $last_updated = $media['uploaded_at'];
    }
}

// Format the last updated time
$last_updated_formatted = $last_updated ? date('g:i A, jS F, Y', strtotime($last_updated)) : 'No updates yet';
?>
<!DOCTYPE html>
<html lang="en">

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Galleries</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">


    <style>
        /* 1. GRID CONTAINER */
        #mediaContainer {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 10px 0;
        }

        /* 2. MEDIA ITEM (The Cloud Box) */
        .media-item {
            flex: 1 1 auto;
            position: relative;
            height: 300px;
            /* Base height for all rows */
            min-width: 150px;
            /* Prevents tiny squeezed boxes */
            background-color: #eee;
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s ease, flex-basis 0.3s ease;
        }

        .media-item:hover {
            transform: scale(1.02);
            z-index: 10;
        }

        /* 3. IMAGES & THUMBNAILS */
        .media-item img,
        .media-item .lazy-load,
        .thumb-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            /* Ensures no gaps, aspect ratio handled by flex */
            display: block;
            opacity: 0;
            transition: filter 0.5s ease, opacity 0.5s ease, transform 0.2s ease;
            will-change: filter, opacity;
        }

        /* Show image when loaded via JS */
        .media-item img.loaded {
            opacity: 1;
        }

        /* 4. ASPECT RATIO HINTS (Prevents Jumping) */
        .is-portrait {
            flex-basis: 180px;
            /* Narrower reservation for vertical */
        }

        .is-landscape {
            flex-basis: 450px;
            /* Wider reservation for horizontal */
        }

        /* Ensure the last row doesn't stretch awkwardly to full width */
        #mediaContainer::after {
            content: "";
            flex-grow: 999;
        }

        /* 5. OVERLAYS & UI ELEMENTS */
        .customCheckbox {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 20;
            transform: scale(1.5);
            /* Sized for easy touch */
            cursor: pointer;
            accent-color: #0d6efd;
        }

        .video-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            backdrop-filter: blur(4px);
            z-index: 10;
        }

        .play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 3.5rem;
            color: white;
            opacity: 0.8;
            text-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            transition: opacity 0.3s, transform 0.2s;
            z-index: 5;
            pointer-events: none;
            /* Let clicks pass to the anchor tag */
        }

        .media-item:hover .play-button {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1.1);
        }

        /* 6. BACKGROUND COMPRESSION OVERLAY */
        .processing-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            z-index: 15;
            font-size: 0.8rem;
            gap: 10px;
            backdrop-filter: blur(3px);
            transition: opacity 0.5s ease, visibility 0.5s;
        }


        /* Optional: added a nice "Success" flash when it finishes */
        .media-item:not(.is-processing) {
            transition: all 0.5s ease;
        }

        /* 7. SKELETON SHIMMER */
        @keyframes shimmer {
            0% {
                background-position: -468px 0;
            }

            100% {
                background-position: 468px 0;
            }
        }

        .skeleton {
            background: #f6f7f8;
            background-image: linear-gradient(to right, #f6f7f8 0%, #edeef1 20%, #f6f7f8 40%, #f6f7f8 100%);
            background-repeat: no-repeat;
            background-size: 800px 100%;
            animation: shimmer 1.5s infinite linear;
        }

        /* Helper for GLightbox/Videos still processing */
        .disabled-link {
            pointer-events: none;
            filter: grayscale(0.5);
        }
    </style>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/glightbox/3.3.0/css/glightbox.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/glightbox/3.3.0/js/glightbox.min.js"></script>
</head>

<body>

    <!-- Fixed Top Navbar -->
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <!-- Display the message from the GET parameter -->
        <?php if ($message): ?>
            <div class="alert alert-info">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php while ($gallery = $galleries->fetch_assoc()): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="dropdown ms-auto" style="float: right;">
                        <i class="bi bi-three-dots-vertical" data-bs-toggle="dropdown" aria-expanded="false"></i>
                        <ul class="dropdown-menu">
                            <li>
                                <span class="dropdown-item">
                                    <a class="btn btn-danger ml-2" href="delete_gallery.php?gallery_id=<?php echo $gallery['id']; ?>"><i class="bi bi-trash"></i> Delete</a>
                                </span>
                            </li>
                        </ul>
                    </div>
                    <h2 class="card-title"><?php echo $gallery['title']; ?></h2>
                    <p class="card-text"><?php echo $gallery['description']; ?></p>

                    <p class="text-muted">
                        <?php echo $images_count; ?> Images, <?php echo $videos_count; ?> Videos
                    </p>
                    <p class="text-muted">
                        Last updated: <?php echo $last_updated_formatted; ?>
                    </p>


                    <div class="container mt-4">
                        <div id="duplicate-images-container" class="row_2 row overflow-auto" style="white-space: nowrap;">
                            <!-- Duplicate image cards will appear here -->
                        </div>
                    </div>

                    <script>
                        // script.js

                        const galleryId = '<?php echo $gallery['id']; ?>'; // Replace with actual gallery ID

                        // Function to load duplicate images from the server
                        window.loadDuplicates = function(galleryId) {
                            $.getJSON(`get_gallery_duplicates.php?gallery_id=${galleryId}`, function(response) {
                                if (response.duplicates && response.duplicates.length > 0) {
                                    displayDuplicates(response.duplicates);
                                } else {
                                    $('#duplicate-images-container').html('<p>No duplicates found.</p>');
                                }
                            }).fail(function() {
                                alert('Error loading duplicate images.');
                            });
                        }

                        $(document).ready(function() {
                            loadDuplicates(galleryId);
                        });

                        // Function to display duplicate images in cards
                        window.displayDuplicates = function(duplicates) {
                            let html = '';
                            duplicates.forEach((pair, index) => {
                                html += `
                    <div class="card duplicate-card mr-5">
                        <div class="card-body">
                            <h5 class="card-title">Duplicate Pair #${index + 1}</h5>
                            <div class="row">
                                <div class="col-6">
                                    <div class="image-container">                   
                                        <a href="serve_image.php?file=${pair.image1_file}&w=800" class="my-lightbox-toggle" data-gallery="pair${index + 1}" data-toggle="lightbox" rel="noopener noreferrer">
                                            <img loading="lazy" src="serve_image.php?file=${pair.image1_file}&w=300" class="img-fluid uniform-image gallery-img"  alt="Image 1">
                                            <div class="overlay">
                                                <div class="text">#${pair.image1_id}</div>
                                            </div>
                                        </a>
                                    </div>
                                    <button class="btn btn-success mt-2" onclick="keepImage(${pair.image1_id}, ${pair.image2_id})">Keep This</button>
                                </div>
                                <div class="col-6">
                                    <div class="image-container">
                                        <a href="serve_image.php?file=${pair.image2_file}&w=800" class="my-lightbox-toggle" data-gallery="pair${index + 1}" data-toggle="lightbox" rel="noopener noreferrer">
                                            <img loading="lazy" src="serve_image.php?file=${pair.image2_file}&w=300" class="img-fluid uniform-image gallery-img"  alt="Image 1">
                                            <div class="overlay">
                                                <div class="text">#${pair.image2_id}</div>
                                            </div>
                                        </a>
                                    </div>
                                    <button class="btn btn-danger mt-2" onclick="keepImage(${pair.image2_id}, ${pair.image1_id})">Keep This</button>
                                </div>
                                <button class="btn btn-secondary mt-3" onclick="flagAsNonDuplicate(${pair.image1_id}, ${pair.image2_id}, ${galleryId}, ${index}, this.closest('.card'))">Keep Both</button>
                            </div>
                        </div>
                    </div>
                `;
                            });

                            $('#duplicate-images-container').html(html);
                            GlightboxDefine();
                        }

                        // Flag as non-duplicate
                        window.flagAsNonDuplicate = function(media1, media2, galleryId, cardIndex, element) {
                            $.post('flag_duplicate.php', {
                                media_1: media1,
                                media_2: media2,
                                gallery_id: galleryId,
                                matched: 0
                            }, function(response) {
                                if (response.status === 'success') {
                                    $(`#duplicate-card-${cardIndex}`).remove();
                                    element.remove();
                                } else {
                                    alert('Error flagging the images.');
                                }
                            });
                        }

                        // Function to handle keeping one image and deleting the other
                        window.keepImage = function(keepImageId, deleteImageId) {
                            $.get(`delete_image.php?image_id=${deleteImageId}`, function(response) {
                                if (response.status === 'success') {
                                    // Remove the card for this duplicate pair
                                    $(`#duplicate-card-${keepImageId}`).remove();
                                    // Optionally, reload the next duplicates after deletion
                                    loadDuplicates(galleryId);
                                    document.getElementById('mediaContent' + deleteImageId).remove();
                                } else {
                                    alert('Error deleting the image.');
                                }
                            });
                        }
                    </script>

                    <!-- Add the following CSS for the uniform image size and horizontal scroll effect -->
                    <style>
                        .uniform-image {
                            width: 100%;
                            height: 200px;
                            /* Fixed height for uniform images */
                            object-fit: cover;
                            /* Maintain aspect ratio while filling the area */
                        }

                        .duplicate-card {
                            display: inline-block;
                            /* Display cards horizontally */
                            width: 300px;
                            /* Set a fixed width for each card */
                        }

                        .row_2 {
                            display: flex;
                            flex-wrap: nowrap;
                            /* Keep images in one row without wrapping */
                            justify-content: space-between;
                        }

                        .image-container {
                            position: relative;
                        }

                        .overlay {
                            position: absolute;
                            top: 0;
                            left: 0;
                            right: 0;
                            bottom: 0;
                            background-color: rgba(0, 0, 0, 0.5);
                            color: white;
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            opacity: 0.5;
                            transition: opacity 0.3s;
                        }

                        .image-container:hover .overlay {
                            opacity: 1;
                        }

                        .overlay .text {
                            font-size: 12px;
                            /* Smaller text */
                            text-align: center;
                        }

                        /* Enable horizontal scroll for the duplicate images container */
                        #duplicate-images-container {
                            display: flex;
                            overflow-x: auto;
                            padding: 10px;
                        }

                        /* Prevent horizontal scroll from showing the "no duplicates" message */
                        #duplicate-images-container p {
                            margin: 0;
                            padding: 10px;
                        }
                    </style>





                    <div class="row">
                        <div class="col-4">
                            <button id="deleteSelectedBtn" class="btn btn-danger mb-3" style="display: none;" onclick="deleteSelected()"><i class="bi bi-trash"></i></button>
                            <div class="form-check" id="selectAllContainer" style="border-bottom: 1px solid blue; display: none;">
                                <input class="form-check-input" type="checkbox" id="selectAll" onclick="selectAllImages()">
                                <label class="form-check-label" for="selectAll">Select All</label>
                            </div>
                        </div>
                        <div class="col-4">
                            <select id="mediaFilter" class="form-select mb-3" onchange="filterMedia()">
                                <option value="all">Filter</option>
                                <option value="all">All</option>
                                <option value="image">Photos</option>
                                <option value="video">Videos</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <a class="btn btn-primary ml-2" href="update_gallery_form.php?id=<?php echo $gallery['id']; ?>"><i class="bi bi-plus-circle-fill"></i></a>
                            <a class="btn btn-primary ml-2" href="hero_images.php?id=<?php echo $gallery['id']; ?>"><i class="bi bi-border-all"></i></a>
                            <a class="btn btn-primary ml-2" href="image_from_video.php?id=<?php echo $gallery['id']; ?>"><i class="bi bi-image"></i></a>
                        </div>
                    </div>

                    <style>
                        .media-style {
                            position: relative;
                            overflow: hidden;
                            width: auto;
                        }
                    </style>

                    <div id="mediaContainer" class="media-grid">
                        <?php
                        $stmt_media->execute();
                        $media_result = $stmt_media->get_result();
                        $media_files = [];

                        while ($media = $media_result->fetch_assoc()):
                            $media_files[] = $media;

                            // 1. Extract Ratio (e.g., 1920/1080)
                            $ratio = "1 / 1"; // Square fallback
                            if (!empty($media['dimension']) && strpos($media['dimension'], 'x') !== false) {
                                $dims = explode('x', $media['dimension']);
                                if (count($dims) === 2 && (int)$dims[1] > 0) {
                                    // We use the raw numbers for the CSS aspect-ratio property
                                    $ratio = (int)$dims[0] . " / " . (int)$dims[1];
                                }
                            }

                            $is_processing = ($media['file_type'] === 'video' && ($media['status'] === 'pending' || $media['status'] === 'processing'));
                        ?>
                            <div class="media-item skeleton media-style <?php echo $is_processing ? 'is-processing' : ''; ?>"
                                id="mediaContent<?php echo $media['id']; ?>"
                                data-type="<?php echo $media['file_type']; ?>"
                                style="aspect-ratio: <?php echo $ratio; ?>; position: relative;">

                                <input type="checkbox" class="customCheckbox select-checkbox" data-id="<?php echo $media['id']; ?>" />

                                <?php if ($media['file_type'] == 'image'): ?>
                                    <a href="serve_image.php?file=<?php echo urlencode($media['file_name']); ?>&w=1200"
                                        class="my-lightbox-toggle"
                                        style="display: block; width: 100%; height: 100%;">
                                        <img class="lazy-load"
                                            style="width: 100%; height: 100%; object-fit: cover; display: block;"
                                            data-src="serve_image.php?file=<?php echo urlencode($media['file_name']); ?>&w=400"
                                            alt="Photo">
                                    </a>
                                <?php else: ?>
                                    <a href="uploads/<?php echo $media['file_name']; ?>"
                                        class="my-lightbox-toggle <?php echo $is_processing ? 'disabled-link' : ''; ?>"
                                        style="display: block; width: 100%; height: 100%; position: relative;">

                                        <?php if ($is_processing): ?>
                                            <div class="processing-overlay" id="prog-container-<?php echo $media['id']; ?>">
                                                <div class="spinner-border spinner-border-sm text-light" role="status"></div>
                                                <div class="progress w-75 mt-2" style="height: 5px; background: rgba(255,255,255,0.2);">
                                                    <div id="bar-<?php echo $media['id']; ?>"
                                                        class="progress-bar bg-success"
                                                        style="width: <?php echo $media['progress']; ?>%"></div>
                                                </div>
                                                <small style="font-size: 9px;"><span id="pct-<?php echo $media['id']; ?>"><?php echo $media['progress']; ?></span>%</small>
                                            </div>
                                        <?php else: ?>
                                            <div class="play-button"><i class="fa-solid fa-circle-play"></i></div>
                                            <span class="video-indicator"><i class="bi bi-camera-video-fill"></i> Video</span>
                                        <?php endif; ?>

                                        <img class="lazy-load"
                                            style="width: 100%; height: 100%; object-fit: cover; display: block;"
                                            data-src="video_placeholder.php?file_name=<?php echo urlencode($media['file_name']); ?>"
                                            alt="Video Thumbnail">
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>


        <style>
            .container1 {
                /* display: flex; */
                /* justify-content: space-between; */
                /* align-items: center; */
                height: 74vh;
                flex-wrap: nowrap;
                align-content: center;
                overflow: hidden;
            }

            .centered1 {
                /* width: 50%; */
                /* Adjust as needed */
                /* height: 50%; */
                /* Adjust as needed */
                background-color: lightblue;
            }
        </style>


        <!-- Lightbox Modal -->
        <div class="modal fade" id="lightboxModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Media Viewer</h5>
                        <button type="button" class="btn-close" onclick="hideLightbox()" aria-label="Close"></button>
                    </div>
                    <div class="modal-body container1">
                        <div id="mediaCarousel" class="carousel slide" data-ride="carousel">
                            <div class="carousel-inner">
                                <div class="carousel-item active d-flex justify-content-center">
                                    <img id="lightboxImage" class="d-block centered1" style="max-height: 41rem;" src="" alt="Media">
                                </div>
                                <div class="carousel-item">
                                    <video class="d-block w-100" controls>
                                        <source src="" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                            </div>
                            <a class="carousel-control-prev" href="#mediaCarousel" role="button" data-slide="prev" onclick="changeImage(-1)">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="sr-only">Previous</span>
                            </a>
                            <a class="carousel-control-next" href="#mediaCarousel" role="button" data-slide="next" onclick="changeImage(1)">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="sr-only">Next</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bootstrap JS and Popper.js -->
        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

        <script>
            // --- 1. LAZY LOADING LOGIC ---
            document.addEventListener("DOMContentLoaded", function() {
                const lazyImages = document.querySelectorAll('img.lazy-load');

                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.onload = () => {
                                img.classList.add('loaded');
                                img.parentElement.closest('.media-item').classList.remove('skeleton');
                            };
                            observer.unobserve(img);
                        }
                    });
                }, {
                    rootMargin: '200px'
                }); // Load images 200px before they appear

                lazyImages.forEach(image => imageObserver.observe(image));

                // Re-run GLightbox
                GlightboxDefine();
            });


            const myModal = new bootstrap.Modal(document.getElementById('lightboxModal'));

            function hideLightbox() {
                const bootstrapModal = myModal;
                bootstrapModal.hide(); // This will hide the modal
            }

            let currentIndex = 0;


            // At the bottom of your file in the <script> tag
            const mediaFiles = <?php echo json_encode($media_files); ?>;

            // If the array is empty, ensure it defaults to an empty array instead of null
            if (!mediaFiles) {
                const mediaFiles = [];
            }

            function openLightbox(filetype, fileName, index) {
                currentIndex = index;
                const lightboxImage = document.getElementById('lightboxImage');

                if (filetype === 'image') {
                    lightboxImage.src = 'serve_image.php?file=' + fileName + '&w=700';
                } else {
                    // Video handling logic can go here if needed
                }

                myModal.show(); // Use this to show the modal
            }

            function changeImage(direction) {
                currentIndex += direction;
                if (currentIndex < 0) {
                    currentIndex = mediaFiles.length - 1; // Wrap to last image
                } else if (currentIndex >= mediaFiles.length) {
                    currentIndex = 0; // Wrap to first image
                }
                const nextFileName = mediaFiles[currentIndex].file_name;

                if (mediaFiles[currentIndex].file_type == 'image') {
                    document.getElementById('lightboxImage').src = 'serve_image.php?file=' + encodeURIComponent(nextFileName);
                } else {
                    document.getElementById('lightboxImage').src = 'video_placeholder.php?file_name=' + +encodeURIComponent(nextFileName); // Set the source for the lightbox image
                }
            }


            function loadVideo(videoId, fileName) {
                // Get the video element
                const videoElement = document.getElementById('videoplayer' + videoId);
                const playButton = document.getElementById('videoplaybutton' + videoId);
                const sourceElement = videoElement.querySelector('source');

                // Set the video source
                sourceElement.src = 'uploads/' + encodeURIComponent(fileName);

                // Load the video
                videoElement.load();

                // Show the video and hide the placeholder image
                document.querySelector('#videopreview' + videoId).style.display = 'none';
                playButton.style.display = 'none';
                videoElement.style.display = 'block';

                // Play the video
                videoElement.play();
            }

            let selectedImages = [];

            // Track selected checkboxes
            document.querySelectorAll('.select-checkbox').forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    const imageId = this.getAttribute('data-id');
                    if (this.checked) {
                        selectedImages.push(imageId);
                    } else {
                        selectedImages = selectedImages.filter(id => id !== imageId);
                    }
                    toggleDeleteButton();
                    toggleSelectAllCheckbox();
                });
            });

            // Toggle 'Delete Selected' and 'Select All' button visibility
            function toggleDeleteButton() {
                const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
                if (selectedImages.length > 0) {
                    deleteSelectedBtn.style.display = 'inline-block';
                } else {
                    deleteSelectedBtn.style.display = 'none';
                }
            }

            function toggleSelectAllCheckbox() {
                const selectAll = document.getElementById('selectAllContainer');
                if (selectedImages.length > 0) {
                    selectAll.style.display = 'inline-block';
                } else {
                    selectAll.style.display = 'none';
                }
            }

            // Select all images
            function selectAllImages() {
                const selectAll = document.getElementById('selectAll');
                const checkboxes = document.querySelectorAll('.select-checkbox');
                selectedImages = [];
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = selectAll.checked;
                    if (selectAll.checked) {
                        selectedImages.push(checkbox.getAttribute('data-id'));
                    }
                });
                toggleDeleteButton();
            }

            // Delete selected images
            function deleteSelected() {
                if (!confirm('Are you sure you want to delete the selected images?')) {
                    return;
                }

                // Send the selected image IDs to the server for deletion
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'delete_images.php', true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        alert('Selected images deleted successfully.');
                        location.reload(); // Reload the page to reflect changes
                    } else {
                        alert('Error deleting images. Please try again.');
                    }
                };
                xhr.send(JSON.stringify({
                    ids: selectedImages
                }));
            }

            // --- 2. IMPROVED FILTERING (Fixes Display Gaps) ---
            function filterMedia() {
                const filter = document.getElementById('mediaFilter').value;
                const items = document.querySelectorAll('.media-item');

                items.forEach(item => {
                    if (filter === 'all' || item.getAttribute('data-type') === filter) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }


            // --- 3. POLLING FOR VIDEO COMPRESSION PROGRESS ---
            function pollProgress() {
                const processingItems = document.querySelectorAll('.media-item.is-processing');
                if (processingItems.length === 0) return;

                const ids = Array.from(processingItems).map(item => item.id.replace('mediaContent', ''));

                fetch(`get_upload_progress.php?ids=${ids.join(',')}`)
                    .then(res => res.json())
                    .then(data => {
                        data.forEach(item => {
                            const container = document.getElementById(`mediaContent${item.id}`);
                            const bar = document.getElementById(`bar-${item.id}`);
                            const pct = document.getElementById(`pct-${item.id}`);
                            // Select the text label inside the processing overlay
                            const statusLabel = container.querySelector('.status-label');

                            // --- QUEUE & PROGRESS UI UPDATE ---
                            if (item.status === 'pending') {
                                if (bar) bar.style.width = '0%';
                                if (pct) pct.innerText = '0';
                                if (statusLabel) statusLabel.innerText = `In Queue: #${item.queue_pos}`;
                            } else if (item.status === 'processing') {
                                if (bar) bar.style.width = item.progress + '%';
                                if (pct) pct.innerText = item.progress;
                                if (statusLabel) statusLabel.innerText = `Compressing...`;
                            }

                            // SEAMLESS TRANSITION WHEN READY
                            if (item.status === 'ready') {
                                if (!container || !container.classList.contains('is-processing')) return;

                                const link = container.querySelector('a');
                                const img = container.querySelector('img');

                                // 1. Force the Browser to ignore cached video versions with a timestamp
                                const timestamp = new Date().getTime();
                                const newVideoUrl = `uploads/${item.file_name}?v=${timestamp}`;

                                // 2. Update the DOM Attributes
                                container.classList.remove('is-processing');
                                link.classList.remove('disabled-link');
                                link.href = newVideoUrl;
                                link.setAttribute('href', newVideoUrl);
                                link.setAttribute('data-href', newVideoUrl); // GLightbox priority
                                link.setAttribute('data-type', 'video');

                                // 3. Cleanup UI Overlays
                                const overlay = container.querySelector('.processing-overlay');
                                if (overlay) {
                                    overlay.style.opacity = '0';
                                    setTimeout(() => overlay.remove(), 500);
                                }

                                // 4. Inject Play Button UI (if not already there)
                                if (!container.querySelector('.play-button')) {
                                    const playBtnHtml = `
                            <div class="play-button" style="pointer-events: none;"><i class="fa-solid fa-circle-play"></i></div>
                            <span class="video-indicator"><i class="bi bi-camera-video-fill"></i> Video</span>
                        `;
                                    link.insertAdjacentHTML('afterbegin', playBtnHtml);
                                }

                                // 5. Handle Thumbnail Transition (Blur effect)
                                img.style.filter = 'blur(10px)';
                                img.style.transition = 'filter 0.5s ease';

                                const newThumbUrl = `video_placeholder.php?file_name=${encodeURIComponent(item.file_name)}`;
                                const tempImg = new Image();
                                tempImg.src = newThumbUrl;
                                tempImg.onload = () => {
                                    img.src = newThumbUrl;
                                    img.style.filter = 'none';
                                    img.classList.add('loaded');
                                };

                                // 6. THE FIX: RE-BIND LIGHTBOX
                                if (typeof window.GlightboxDefine === 'function') {
                                    window.GlightboxDefine();
                                }

                                console.log(`Successfully transitioned Video ID: ${item.id} to Ready state.`);
                            }
                        });
                    });
            }

            setInterval(pollProgress, 2000); // Poll every 5 seconds


            window.GlightboxDefine = function() {
                // 1. If an instance already exists, kill it to clear the internal URL cache
                if (window.currentLightboxInstance) {
                    window.currentLightboxInstance.destroy();
                }

                // 2. Initialize a fresh instance that scans the current DOM
                window.currentLightboxInstance = GLightbox({
                    selector: '.my-lightbox-toggle',
                    touchNavigation: true,
                    loop: false,
                    openEffect: 'fade',
                    closeEffect: 'fade',
                    zoomable: true,
                    draggable: true,
                    backdrop: true,
                    preload: 5,
                    plyr: {
                        css: 'https://cdn.plyr.io/3.5.6/plyr.css',
                        js: 'https://cdn.plyr.io/3.5.6/plyr.js',
                        config: {
                            ratio: '9:16',
                            muted: false,
                            hideControls: true,
                            youtube: {
                                noCookie: true,
                                rel: 0,
                                showinfo: 0,
                                iv_load_policy: 3
                            },
                            vimeo: {
                                byline: false,
                                portrait: false,
                                title: false,
                                speed: true,
                                transparent: false
                            }
                        }
                    }
                });
            };

            // Run it once on page load
            document.addEventListener("DOMContentLoaded", window.GlightboxDefine);
        </script>

</body>

</html>