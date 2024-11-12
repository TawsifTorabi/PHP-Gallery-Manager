<?php
include 'session.php';
include 'db.php';

// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Pagination setup
$items_per_page = 20; // Adjust this number as needed
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Fetch total galleries for pagination
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM galleries WHERE created_by = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$total_result = $stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_galleries = $total_row['total'];
$total_pages = ceil($total_galleries / $items_per_page);

// Fetch galleries created by the logged-in user with pagination
$stmt = $conn->prepare("SELECT id, title, created_at FROM galleries WHERE created_by = ? ORDER BY id DESC LIMIT ?, ?");
$stmt->bind_param("iii", $_SESSION['user_id'], $offset, $items_per_page);
$stmt->execute();
$result = $stmt->get_result();
$galleries = $result->fetch_all(MYSQLI_ASSOC);



// Function to calculate the size of a folder
function folderSize($dir)
{
    $totalSize = 0;

    // Open the directory
    $files = scandir($dir);

    // Loop through all files
    foreach ($files as $file) {
        if ($file !== "." && $file !== "..") {
            $path = $dir . DIRECTORY_SEPARATOR . $file;

            // If it's a directory, recursively calculate the size
            if (is_dir($path)) {
                $totalSize += folderSize($path);
            } else {
                // If it's a file, add the file size
                $totalSize += filesize($path);
            }
        }
    }

    return $totalSize;
}

// Function to convert bytes to a human-readable format
function formatSize($size)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $unitIndex = 0;

    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }

    return round($size, 2) . ' ' . $units[$unitIndex];
}

// Folder path (change to the folder you want to check)
$folderPath = 'uploads'; // Example: '/var/www/html/myfolder'

// Calculate folder size
$size = folderSize($folderPath);

// Get total disk space and free space for the partition the folder resides in
$diskTotalSpace = disk_total_space($folderPath);
$diskFreeSpace = disk_free_space($folderPath);

// Calculate used space on the disk
$diskUsedSpace = $diskTotalSpace - $diskFreeSpace;

// Output the folder size and disk space information
$folderSize = formatSize($size);
$totalDiskSpace = formatSize($diskTotalSpace);
$freeDiskSpace = formatSize($diskFreeSpace);
$usedDiskSpace = formatSize($diskUsedSpace);

// Calculate percentage of space used
$usagePercentage = ($size / $diskFreeSpace) * 100;


?>

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <!-- Fixed Top Navbar -->
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h2 class="mb-4">Welcome, <?php echo $_SESSION['username']; ?>!</h2>
        <p>You are logged in. This is your dashboard.</p>
        <div class="row">
            <div class="col-6">
                Storage Usage: <?= $folderSize; ?> / <?= $freeDiskSpace; ?> (<?= round($usagePercentage, 2); ?>%)
                <!-- Bootstrap Progress Bar -->
                <div class="progress">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $usagePercentage; ?>%;" aria-valuenow="<?= $usagePercentage; ?>" aria-valuemin="0" aria-valuemax="100"><?= round($usagePercentage, 2); ?>%</div>
                </div>
            </div>
            <div class="col-6">
                <a href="gallery_form.php" class="btn btn-primary">Create New Gallery</a>
                <a href="logout.php" class="btn btn-danger" onclick="return confirm('Are you sure you want to logout?');">Logout</a>
            </div>
        </div>
    </div>

    <div class="container mt-5">
        <h1>Your Galleries</h1>

        <form action="search_gallery.php" method="get" id="searchForm" class="mt-2">
            <div class="input-group mb-3">
                <input type="text" class="form-control" name="query" placeholder="Search Your galleries" id="searchInput" required>
                <button class="btn btn-outline-secondary" type="submit">Search</button>
            </div>
        </form>

        <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center flex-wrap">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <?php if (count($galleries) > 0): ?>
            <table class="table table-striped" id="galleryTable">
                <thead>
                    <tr>
                        <th>Gallery Title</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($galleries as $gallery): ?>
                        <tr>
                            <td><a  href="display_gallery.php?id=<?php echo $gallery['id']; ?>"><?php echo htmlspecialchars($gallery['title']); ?></a></td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($gallery['created_at'])); ?></td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                        Action
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                        <li><a class="dropdown-item" href="display_gallery.php?id=<?php echo $gallery['id']; ?>">View Gallery</a></li>
                                        <li><a class="dropdown-item" href="hero_images.php?id=<?php echo $gallery['id']; ?>">Set Hero Images</a></li>
                                        <li><a class="dropdown-item" href="update_gallery_form.php?id=<?php echo $gallery['id']; ?>">Upload Image/Video</a></li>
                                        <li><a class="dropdown-item" href="image_from_video.php?id=<?php echo $gallery['id']; ?>">Upload Image From Video</a></li>
                                        <li><a class="dropdown-item text-danger" href="delete_gallery.php?gallery_id=<?php echo $gallery['id']; ?>" onclick="return confirm('Are you sure you want to delete this gallery?');">Delete</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center flex-wrap">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>

        <?php else: ?>
            <p>No galleries found.</p>
        <?php endif; ?>

    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

    <script>
        // Dynamic search functionality
        $(document).ready(function() {
            $('#searchInput').on('input', function() {
                const query = $(this).val();
                $.ajax({
                    url: 'search_gallery_ajax.php', // Endpoint to search galleries
                    method: 'GET',
                    data: {
                        query: query
                    },
                    success: function(response) {
                        $('#galleryTable tbody').html(response);
                    }
                });
            });
        });
    </script>

</body>

</html>