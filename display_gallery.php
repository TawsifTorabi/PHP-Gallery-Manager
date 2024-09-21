<?php
session_start();
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

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Galleries</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .gallery-img {
            cursor: pointer;
        }
    </style>
</head>

<body>
    <!-- Fixed Top Navbar -->
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h2>Your Galleries</h2>

        <!-- Display the message from the GET parameter -->
        <?php if ($message): ?>
            <div class="alert alert-info">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php while ($gallery = $galleries->fetch_assoc()): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $gallery['title']; ?></h5>
                    <p class="card-text"><?php echo $gallery['description']; ?></p>

                    <!-- Display image and video count, and the last updated time -->
                    <p class="text-muted">
                        <?php echo $images_count; ?> Images, <?php echo $videos_count; ?> Videos
                    </p>
                    <p class="text-muted">
                        Last updated: <?php echo $last_updated_formatted; ?>
                    </p>

                    <a class="btn btn-secondary" href="update_gallery_form.php?id=<?php echo $gallery['id']; ?>">Update Gallery</a>
                    <br><br>

                    <!-- Filter Dropdown -->
                    <label for="mediaFilter" class="form-label">Filter Media:</label>
                    <select id="mediaFilter" class="form-select mb-3" onchange="filterMedia()">
                        <option value="all">All</option>
                        <option value="image">Photos</option>
                        <option value="video">Videos</option>
                    </select>

                    <!-- Select All and Bulk Delete buttons -->
                    <button id="deleteSelectedBtn" class="btn btn-danger mb-3" style="display: none;" onclick="deleteSelected()">Delete Selected</button>

                    <!-- Select All Checkbox -->
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll" style="display: none;" onclick="selectAllImages()">
                        <label class="form-check-label" for="selectAll">Select All</label>
                    </div>

                    <!-- Fetch and display media for the gallery -->
                    <div class="row" id="mediaContainer">
                        <?php
                        $stmt_media->execute();
                        $media_result = $stmt_media->get_result();
                        $media_files = [];
                        $i = 0;
                        while ($media = $media_result->fetch_assoc()): $media_files[] = $media; ?>
                            <div class="col-12 col-sm-4 mb-3 media-item" data-type="<?php echo $media['file_type']; ?>">
                                <input type="checkbox" class="select-checkbox" data-id="<?php echo $media['id']; ?>" style="margin-right: 10px;">
                                <?php if ($media['file_type'] == 'image'): ?>
                                    <img src="serve_image.php?file=<?php echo urlencode($media['file_name']); ?>" class="img-fluid gallery-img" alt="Image" data-bs-toggle="modal" data-bs-target="#lightboxModal" data-index="<?php echo $i; ?>">
                                <?php else: ?>
                                    <video width="100%" controls>
                                        <source src="uploads/<?php echo $media['file_name']; ?>" type="video/mp4">
                                    </video>
                                <?php endif; ?>
                            </div>
                        <?php $i++;
                        endwhile; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>

    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <script>
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
            const selectAll = document.getElementById('selectAll');
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
