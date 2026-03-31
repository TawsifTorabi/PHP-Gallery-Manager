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

// Fetch gallery created by the logged-in user
$stmt = $conn->prepare("SELECT * FROM galleries WHERE created_by = ? AND id = ?");
$stmt->bind_param("ii", $user_id, $gallery_id);
$stmt->execute();
$galleries = $stmt->get_result();
$gallery = $galleries->fetch_assoc(); // Fetch one gallery

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

// Fetch the hero images if set
$hero_images = !empty($gallery['hero_images']) ? explode('$%@!', $gallery['hero_images']) : [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Hero Image</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" crossorigin="anonymous">
    <style>
        .gallery-img {
            cursor: pointer;
            max-width: 10em;
        }

        .modal-dialog-scrollable {
            max-height: 90%;
        }

        :root {
            --primary-glow: #0d6efd4d;
        }

        .gallery-card {
            position: relative;
            transition: all 0.2s ease-in-out;
            border: 2px solid transparent;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
        }

        .gallery-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        /* State when checkbox is checked */
        .hero-checkbox:checked+.gallery-label .gallery-card {
            border-color: #0d6efd;
            box-shadow: 0 0 15px var(--primary-glow);
        }

        .hero-checkbox:checked+.gallery-label .selection-badge {
            display: flex !important;
        }

        .selection-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #0d6efd;
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .hero-checkbox {
            display: none;
        }

        /* Hide the actual checkbox */

        .sticky-save-bar {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            background: white;
            padding: 15px 30px;
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid #eee;
        }
    </style>
</head>

<body>
    <!-- Fixed Top Navbar -->
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h2>Set Hero Image</h2>

        <?php if ($message): ?>
            <div class="alert alert-info">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><?php echo htmlspecialchars($gallery['title']); ?></h5>
                <p class="card-text"><?php echo htmlspecialchars($gallery['description']); ?></p>

                <p class="text-muted">
                    <?php echo $images_count; ?> Images, <?php echo $videos_count; ?> Videos
                </p>
                <p class="text-muted">
                    Last updated: <?php echo $last_updated_formatted; ?>
                </p>

                <!-- Hero Image Selection -->
                <div class="mb-3">
                    <h5>Hero Images</h5>
                    <p>Select up to 4 images as hero images.</p>
                    <form id="heroImageForm" method="post" action="set_hero_images.php">
                        <input type="hidden" name="gallery_id" value="<?php echo $gallery_id; ?>">

                        <div class="sticky-save-bar">
                            <span class="fw-bold"><span id="selected-count">0</span> / 4 Selected</span>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">Save Hero Selection</button>
                        </div>

                        <div class="row">
                            <?php
                            $stmt_media->execute();
                            $media_result = $stmt_media->get_result();
                            while ($media = $media_result->fetch_assoc()):
                                if ($media['file_type'] == 'image'):
                                    $is_selected = in_array($media['file_name'], $hero_images);
                            ?>
                                    <div class="col-xl-2 col-lg-3 col-md-4 col-6 mb-4">
                                        <input class="hero-checkbox"
                                            id="image_<?php echo $media['id']; ?>"
                                            type="checkbox"
                                            name="hero_images[]"
                                            value="<?php echo $media['file_name']; ?>"
                                            <?php echo $is_selected ? 'checked' : ''; ?>>

                                        <label for="image_<?php echo $media['id']; ?>" class="gallery-label w-100">
                                            <div class="gallery-card">
                                                <div class="selection-badge"><i class="bi bi-check-lg"></i></div>
                                                <img src="serve_image.php?file=<?php echo urlencode($media['file_name']); ?>&w=400"
                                                    class="img-fluid"
                                                    alt="Gallery Image"
                                                    loading="lazy">
                                            </div>
                                        </label>
                                    </div>
                            <?php endif;
                            endwhile; ?>
                        </div>
                    </form>
                </div>


            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <script>
        $(document).ready(function() {
            function updateCounter() {
                const count = $('.hero-checkbox:checked').length;
                $('#selected-count').text(count);

                // Visual feedback for the bar
                if (count > 0) {
                    $('.sticky-save-bar').fadeIn();
                } else {
                    $('.sticky-save-bar').fadeOut();
                }
            }

            // Initial count
            updateCounter();

            $('.hero-checkbox').on('change', function() {
                const count = $('.hero-checkbox:checked').length;

                if (count > 4) {
                    this.checked = false;
                    // Use a toast or a nicer alert if you have one
                    alert("Maximum 4 hero images allowed.");
                }

                updateCounter();
            });
        });
    </script>

</body>

</html>