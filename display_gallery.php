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
$stmt_media = $conn->prepare("SELECT id, file_type, file_name, uploaded_at FROM images WHERE gallery_id = ? ORDER BY uploaded_at DESC");
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
        .gallery-img {
            /* cursor: pointer; */
            width: auto;
            /* max-height: 150px; */
            height: 20rem;
            object-fit: cover;
            object-position: center;
        }

        .thumb-img {

            object-fit: cover;
            height: 20rem;
            width: 100%;

        }

        .customDropdown {
            position: absolute;
            top: 4px;
            right: 8px;
            background: white;
            width: 23px;
            display: flex;
            justify-content: center;
            border-radius: 5px;
            font-size: 20px;
        }

        .customCheckbox {
            margin-right: 10px;
            position: absolute;
            transform: scale(1.9);
            top: 12px;
            left: 25px;
        }

        .play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 4rem;
            border-radius: 50%;
            background: none;
            border: none;
            color: white;
            text-shadow: -4px 3px 13px #0000002e;
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
                        window.GlightboxDefine = function() {

                            const lightbox = GLightbox({
                                selector: '.my-lightbox-toggle', // Your selector
                                touchNavigation: true,
                                loop: false,
                                openEffect: 'fade',
                                closeEffect: 'fade',
                                zoomable: true,
                                draggable: true,
                                backdrop: true,
                                preload: 5,
                                plyr: {
                                    css: 'https://cdn.plyr.io/3.5.6/plyr.css', // Plyr CSS
                                    js: 'https://cdn.plyr.io/3.5.6/plyr.js', // Plyr JS
                                    config: {
                                        ratio: '9:16', // Default ratio, can be changed dynamically
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
                    </script>


                    <style>

                    </style>

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

                    <div class="row" id="mediaContainer">
                        <?php
                        $stmt_media->execute();
                        $media_result = $stmt_media->get_result();
                        $media_files = [];
                        $i = 0;
                        while ($media = $media_result->fetch_assoc()): $media_files[] = $media; ?>
                            <div class="col-sm-3 col-6 m-auto mb-3 media-item media-style" id="mediaContent<?php echo $media['id']; ?>" data-type="<?php echo $media['file_type']; ?>">
                                <?php if ($media['file_type'] == 'image'): ?>
                                    <input type="checkbox" class="customCheckbox select-checkbox" data-id="<?php echo $media['id']; ?>" style="margin-right: 10px;" />

                                    <a href="serve_image.php?file=<?php echo urlencode($media['file_name']); ?>&w=800" class="my-lightbox-toggle" data-gallery="gallery" data-toggle="lightbox" rel="noopener noreferrer">
                                        <img loading="lazy" style="border-radius: 15px;" src="serve_image.php?file=<?php echo urlencode($media['file_name']); ?>&w=400" class="img-fluid gallery-img" alt="Image" />
                                    </a>


                                    <!-- <img style="border-radius: 15px;" src="serve_image.php?file=<?php echo urlencode($media['file_name']); ?>" class="img-fluid gallery-img" alt="Image" 
                                        data-bs-toggle="modal" data-bs-target="#lightboxModal" data-index="<?php echo $i; ?>" 
                                        onclick="openLightbox('<?php echo urlencode($media['file_type']); ?>','<?php echo urlencode($media['file_name']); ?>', <?php echo $i; ?>)"> -->
                                <?php else: ?>
                                    <div class="video-container media-style">
                                        <input type="checkbox" class="customCheckbox select-checkbox" data-id="<?php echo $media['id']; ?>" style="margin-right: 10px;">

                                        <a href="uploads/<?php echo $media['file_name']; ?>" class="my-lightbox-toggle" data-gallery="gallery" data-type="video" data-toggle="lightbox" rel="noopener noreferrer">
                                            <button id="videoplaybutton<?php echo $media['id']; ?>" class="play-button" onclick="loadVideo(<?php echo $media['id']; ?>, '<?php echo $media['file_name']; ?>')"><i class="fa-solid fa-play"></i></button>
                                            <img loading="lazy" style="border-radius: 15px;" id="videopreview<?php echo $media['id']; ?>" onclick="loadVideo(<?php echo $media['id']; ?>, '<?php echo $media['file_name']; ?>')" src="video_placeholder.php?file_name=<?php echo $media['file_name']; ?>" class="img-fluid thumb-img" alt="Video Placeholder" />
                                        </a>

                                        <!-- <img style="border-radius: 15px;" id="videopreview<?php echo $media['id']; ?>" onclick="loadVideo(<?php echo $media['id']; ?>, '<?php echo $media['file_name']; ?>')" src="video_placeholder.php?file_name=<?php echo $media['file_name']; ?>" class="img-fluid thumb-img" alt="Video Placeholder" /> -->

                                        <!-- <video class="img-fluid thumb-img" id="videoplayer<?php echo $media['id']; ?>" width="100%" controls preload="none" style="display: none;">
                                            <source src="" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video> -->

                                        <!-- Dropdown for video options -->
                                        <div class="dropdown mt-2 customDropdown">
                                            <i class="bi bi-three-dots-vertical" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;"></i>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="generate_images.php?video_file=<?php echo $media['file_name']; ?>&gallery_id=<?php echo $gallery['id']; ?>">Generate Images</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php $i++;
                        endwhile; ?>
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
            document.addEventListener('DOMContentLoaded', function() {
                // Define GLightbox options
                GlightboxDefine();
            });


            const myModal = new bootstrap.Modal(document.getElementById('lightboxModal'));

            function hideLightbox() {
                const bootstrapModal = myModal;
                bootstrapModal.hide(); // This will hide the modal
            }

            let currentIndex = 0;
            const mediaFiles = <?php echo json_encode($media_files); ?>;

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

            // Filter media based on the selected option
            function filterMedia() {
                const filter = document.getElementById('mediaFilter').value;
                const mediaItems = document.querySelectorAll('.media-item');

                mediaItems.forEach(function(item) {
                    if (filter === 'all' || item.getAttribute('data-type') === filter) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }
        </script>

</body>

</html>