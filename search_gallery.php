<?php
include 'session.php';
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to search galleries.");
}

// Number of records per page
$records_per_page = 15;

// Get the current page or set default to 1
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$start_from = ($page - 1) * $records_per_page;

$search_query = isset($_GET['query']) ? $_GET['query'] : '';

// Prepare the query with LIMIT for pagination
$stmt = $conn->prepare("SELECT * FROM galleries WHERE (title LIKE ? OR description LIKE ?) AND created_by = ? ORDER BY id DESC LIMIT ?, ?");
$search_like = "%" . $search_query . "%";
$stmt->bind_param("ssiii", $search_like, $search_like, $_SESSION['user_id'], $start_from, $records_per_page);
$stmt->execute();
$galleries = $stmt->get_result();

// Get total number of galleries for pagination
$total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM galleries WHERE (title LIKE ? OR description LIKE ?) AND created_by = ?");
$total_stmt->bind_param("ssi", $search_like, $search_like, $_SESSION['user_id']);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_galleries = $total_row['total'];
$total_pages = ceil($total_galleries / $records_per_page);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .hero-images {
            display: flex;
            justify-content: space-between;
            overflow: hidden;
            border: 1px solid #ddd;
            border-radius: 5px;
            height: 150px;
            /* Set height to keep uniform shape */
        }

        .hero-images img {
            width: 25%;
            /* Four images per row */
            height: auto;
            /* Maintain aspect ratio */
            object-fit: cover;
            /* Ensure images cover the box */
        }
    </style>
</head>

<body>
    <!-- Fixed Top Navbar -->
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h2>Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h2>

        <form action="search_gallery.php" method="get">
            <div class="input-group mb-3">
                <input type="text" class="form-control" name="query" placeholder="Search for galleries" required>
                <button class="btn btn-outline-secondary" type="submit">Search</button>
            </div>
        </form>

        <br>

        <!-- Pagination Controls -->
        <nav aria-label="Gallery pagination">
            <ul class="pagination justify-content-center flex-wrap">
                <!-- Previous Page Link -->
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="search_gallery.php?query=<?php echo urlencode($search_query); ?>&page=<?php echo $page - 1; ?>">Previous</a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link">Previous</span>
                    </li>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="search_gallery.php?query=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <!-- Next Page Link -->
                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="search_gallery.php?query=<?php echo urlencode($search_query); ?>&page=<?php echo $page + 1; ?>">Next</a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link">Next</span>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <?php while ($gallery = $galleries->fetch_assoc()): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($gallery['title']); ?></h5>
                    <p class="card-text"><?php echo htmlspecialchars($gallery['description']); ?></p>

                    <!-- Display Hero Images -->
                    <?php if (!empty($gallery['hero_images'])): ?>
                        <div class="mb-2">
                            <div class="hero-images">
                                <?php
                                $hero_images = explode(',', $gallery['hero_images']);
                                foreach ($hero_images as $image): ?>
                                    <img src="serve_image.php?w=180&file=<?php echo urlencode($image); ?>" alt="Hero Image">
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <a href="display_gallery.php?id=<?php echo $gallery['id']; ?>" class="btn btn-primary">View Gallery</a>
                </div>
            </div>
        <?php endwhile; ?>

        <!-- Pagination Controls -->
        <nav aria-label="Gallery pagination">
            <ul class="pagination justify-content-center flex-wrap">
                <!-- Previous Page Link -->
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="search_gallery.php?query=<?php echo urlencode($search_query); ?>&page=<?php echo $page - 1; ?>">Previous</a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link">Previous</span>
                    </li>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="search_gallery.php?query=<?php echo urlencode($search_query); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <!-- Next Page Link -->
                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="search_gallery.php?query=<?php echo urlencode($search_query); ?>&page=<?php echo $page + 1; ?>">Next</a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link">Next</span>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

</body>

</html>