<?php
session_start();
require 'db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to view galleries.");
}

$user_id = $_SESSION['user_id'];

$gallery_id = $_GET['id'];

// Fetch galleries created by the logged-in user
$stmt = $conn->prepare("SELECT * FROM galleries WHERE created_by = ? AND id = ?");
$stmt->bind_param("ii", $user_id, $gallery_id);
$stmt->execute();
$galleries = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Galleries</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
<!-- Fixed Top Navbar -->
<?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h2>Your Galleries</h2>

        <?php while ($gallery = $galleries->fetch_assoc()): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $gallery['title']; ?></h5>
                    <p class="card-text"><?php echo $gallery['description']; ?></p>

                    <!-- Fetch and display media for the gallery -->
                    <?php
                    $stmt_media = $conn->prepare("SELECT * FROM images WHERE gallery_id = ?");
                    $stmt_media->bind_param("i", $gallery_id);
                    $stmt_media->execute();
                    $media_result = $stmt_media->get_result();
                    ?>

                    <div class="row">
                        <?php while ($media = $media_result->fetch_assoc()): ?>
                            <div class="col-md-4 mb-3">
                                <?php if ($media['file_type'] == 'image'): ?>
                                    <img src="uploads/<?php echo $media['file_name']; ?>" class="img-fluid" alt="Image">
                                <?php else: ?>
                                    <video width="100%" controls>
                                        <source src="uploads/<?php echo $media['file_name']; ?>" type="video/mp4">
                                    </video>
                                <?php endif; ?>
                                <a href="delete_image.php?id=<?php echo $media['id']; ?>" class="btn btn-danger mt-2" onclick="return confirm('Are you sure you want to delete this image?')">Delete</a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>

    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

</body>

</html>