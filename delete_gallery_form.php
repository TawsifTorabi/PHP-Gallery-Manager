<?php
include 'session.php';
require 'db.php'; // Include your database connection file

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to delete a gallery.");
}

$user_id = $_SESSION['user_id'];

// Fetch galleries created by the logged-in user
$stmt = $conn->prepare("SELECT id, title FROM galleries WHERE created_by = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$galleries = $result->fetch_all(MYSQLI_ASSOC);

// Handle gallery deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gallery_id = $_POST['gallery_id'];

    // Ensure the gallery belongs to the user
    $stmt = $conn->prepare("SELECT id FROM galleries WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $gallery_id, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Fetch and delete all media associated with the gallery
        $stmt = $conn->prepare("SELECT file_name FROM images WHERE gallery_id = ?");
        $stmt->bind_param("i", $gallery_id);
        $stmt->execute();
        $media_result = $stmt->get_result();

        while ($media = $media_result->fetch_assoc()) {
            $file_path = 'uploads/' . $media['file_name'];
            if (file_exists($file_path)) {
                unlink($file_path); // Delete the file from the server
            }
        }

        // Delete media records from the images table
        $stmt = $conn->prepare("DELETE FROM images WHERE gallery_id = ?");
        $stmt->bind_param("i", $gallery_id);
        $stmt->execute();

        // Delete the gallery itself
        $stmt = $conn->prepare("DELETE FROM galleries WHERE id = ?");
        $stmt->bind_param("i", $gallery_id);
        $stmt->execute();

        echo "Gallery deleted successfully!";
        header("Location: dashboard.php"); // Redirect to dashboard after deletion
        exit;
    } else {
        echo "Failed to delete gallery or you do not have permission.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Gallery</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <!-- Fixed Top Navbar -->
    <?php include 'navbar.php'; ?>

    <div class="container mt-2">
        <h2>Delete Gallery</h2>

        <?php if (count($galleries) > 0): ?>
            <form action="delete_gallery.php" method="post">
                <div class="form-group mb-3">
                    <label for="gallery_id">Select Gallery to Delete</label>
                    <select class="form-control" id="gallery_id" name="gallery_id" required>
                        <?php foreach ($galleries as $gallery): ?>
                            <option value="<?= htmlspecialchars($gallery['id']) ?>">
                                <?= htmlspecialchars($gallery['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-danger">Delete Gallery</button>
            </form>
        <?php else: ?>
            <p>You have no galleries to delete.</p>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
</body>

</html>
