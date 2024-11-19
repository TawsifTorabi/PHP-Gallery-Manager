<?php
include 'session.php';
require 'db.php';
date_default_timezone_set('Asia/Dhaka');

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to view galleries.");
}

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
</head>

<body>
    <!-- Fixed Top Navbar -->
    <?php include 'navbar.php'; ?>







    <div class="container my-5">
        <h1 class="text-center mb-4">Merge Galleries</h1>
        <form id="mergeForm" class="card p-4 shadow-sm">
            <!-- Source Galleries -->
            <div class="mb-3">
                <label for="searchGalleries" class="form-label">Search and Select Galleries to Merge:</label>
                <input
                    type="text"
                    id="searchGalleries"
                    class="form-control"
                    placeholder="Type to search galleries...">
                <div id="sourceGalleries" class="mt-3">
                    <!-- Dynamically populated checkboxes -->
                </div>
            </div>

            <!-- Target Gallery -->
            <div class="mb-3">
                <label for="searchTargetGallery" class="form-label">Search Target Gallery:</label>
                <input
                    type="text"
                    id="searchTargetGallery"
                    class="form-control"
                    placeholder="Type to search for a target gallery...">
                <div id="targetGalleryResults" class="mt-3">
                    <!-- Dynamically populated radio buttons -->
                </div>
            </div>

            <!-- Submit Button -->
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Merge Galleries</button>
            </div>
        </form>
    </div>

    <script>
        $(document).ready(function() {
            // Search and display source galleries
            $('#searchGalleries').on('input', function() {
                const searchTerm = $(this).val();
                if (searchTerm.length < 2) return; // Wait until 2 characters are typed

                $.get('ajax_search_galleries.php', {
                    term: searchTerm
                }, function(data) {
                    const galleryList = $('#sourceGalleries');
                    galleryList.empty();

                    data.forEach(gallery => {
                        // Get the first hero image (if exists) for display
                        const heroImages = gallery.hero_images.split('$%@!');
                        const thumbnail = heroImages[0] ? `serve_image.php?file=${heroImages[0]}&w=80` : '/serve_image.php';

                        galleryList.append(`
                            <div class="form-check d-flex align-items-start mb-3">
                                <input
                                    type="checkbox"
                                    class="form-check-input mt-1 me-2"
                                    name="source_galleries[]"
                                    value="${gallery.id}"
                                    id="gallery${gallery.id}">
                                <label class="form-check-label w-100" for="gallery${gallery.id}">
                                    <div class="d-flex align-items-start">
                                        <img
                                            src="${thumbnail}"
                                            alt="${gallery.title} Thumbnail"
                                            class="img-thumbnail me-3"
                                            style="width: 80px; height: 80px; object-fit: cover;">
                                        <div>
                                            <strong>${gallery.title}</strong>
                                            <small class="text-muted d-block">ID: ${gallery.id}</small>
                                            <small class="text-muted d-block">${gallery.description}</small>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        `);
                    });
                });
            });


            // Search and display target gallery
            $('#searchTargetGallery').on('input', function() {
                const searchTerm = $(this).val();
                if (searchTerm.length < 2) return; // Wait until 2 characters are typed

                $.get('ajax_search_galleries.php', {
                    term: searchTerm
                }, function(data) {
                    const targetGalleryList = $('#targetGalleryResults');
                    targetGalleryList.empty();

                    data.forEach(gallery => {
                        // Get the first hero image (if exists) for display
                        const heroImages = gallery.hero_images.split('$%@!');
                        const thumbnail = heroImages[0] ? `serve_image.php?file=${heroImages[0]}&w=80` : '/path/to/default-image.jpg';

                        targetGalleryList.append(`
                            <div class="form-check d-flex align-items-start mb-3">
                                <input
                                    type="radio"
                                    class="form-check-input mt-1 me-2"
                                    name="target_gallery"
                                    value="${gallery.id}"
                                    id="targetGallery${gallery.id}"
                                    required>
                                <label class="form-check-label w-100" for="targetGallery${gallery.id}">
                                    <div class="d-flex align-items-start">
                                        <img
                                            src="${thumbnail}"
                                            alt="${gallery.title} Thumbnail"
                                            class="img-thumbnail me-3"
                                            style="width: 80px; height: 80px; object-fit: cover;">
                                        <div>
                                            <strong>${gallery.title}</strong>
                                            <small class="text-muted d-block">ID: ${gallery.id}</small>
                                            <small class="text-muted d-block">${gallery.description}</small>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        `);
                    });
                });
            });



            // Handle form submission
            $('#mergeForm').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();

                $.post('merge_galleries.php', formData, function(response) {
                    if (response.success) {
                        alert(response.success);
                        location.reload();
                    } else {
                        alert(response.error);
                    }
                }, 'json');
            });
        });
    </script>


    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
</body>

</html>