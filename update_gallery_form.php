<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to update a gallery.");
    header('Location: index.php');
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
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<!-- Fixed Top Navbar -->
<?php include 'navbar.php'; ?>

<div class="container mt-5">
    <h2>Update Gallery</h2>
    <form action="gallery_update.php?id=<?php echo $gallery_id; ?>" method="post" enctype="multipart/form-data">
        <div class="form-group mb-3">
            <label for="title">Gallery Title</label>
            <input type="text" class="form-control" id="title" name="title" value="<?php echo $gallery['title']; ?>" required>
        </div>
        <div class="form-group mb-3">
            <label for="description">Gallery Description</label>
            <textarea class="form-control" id="description" name="description" required><?php echo $gallery['description']; ?></textarea>
        </div>
        <div class="form-group mb-3">
            <label for="media">Add More Media (Images/Videos)</label>
            <input type="file" class="form-control" id="media" name="media[]" multiple>
        </div>
        <button type="submit" class="btn btn-primary">Update Gallery</button>
    </form>
</div>

<!-- Bootstrap JS and Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

</body>
</html>
