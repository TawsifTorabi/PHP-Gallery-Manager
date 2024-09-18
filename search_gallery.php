<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to search galleries.");
}

$search_query = $_GET['query'];

$stmt = $conn->prepare("SELECT * FROM galleries WHERE (title LIKE ? OR description LIKE ?) AND created_by = ?");
$search_like = "%" . $search_query . "%";
$stmt->bind_param("ssi", $search_like, $search_like, $_SESSION['user_id']);
$stmt->execute();
$galleries = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

    <div class="container mt-5">
        <h2>Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h2>

        <form action="search_gallery.php" method="get">
            <div class="input-group mb-3">
                <input type="text" class="form-control" name="query" placeholder="Search for galleries" required>
                <button class="btn btn-outline-secondary" type="submit">Search</button>
            </div>
        </form>

        <?php while ($gallery = $galleries->fetch_assoc()): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $gallery['title']; ?></h5>
                    <p class="card-text"><?php echo $gallery['description']; ?></p>
                    <a href="display_gallery.php?id=<?php echo $gallery['id']; ?>" class="btn btn-primary">View Gallery</a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

</body>

</html>