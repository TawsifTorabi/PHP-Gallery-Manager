<?php
include 'session.php';
include 'db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to search galleries.");
}

$user_id = $_SESSION['user_id'];
$query = isset($_GET['query']) ? $_GET['query'] : '';

// Fetch galleries matching the search query
$stmt = $conn->prepare("SELECT id, title, created_at FROM galleries WHERE created_by = ? AND title LIKE ? ORDER BY id DESC");
$search_query = "%" . $query . "%";
$stmt->bind_param("is", $user_id, $search_query);
$stmt->execute();
$result = $stmt->get_result();
$galleries = $result->fetch_all(MYSQLI_ASSOC);

if (count($galleries) > 0):
    foreach ($galleries as $gallery): ?>
        <tr>
            <td><?php echo htmlspecialchars($gallery['title']); ?></td>
            <td><?php echo date('Y-m-d H:i:s', strtotime($gallery['created_at'])); ?></td>
            <td>
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                        Action
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                        <li><a class="dropdown-item" href="display_gallery.php?id=<?php echo $gallery['id']; ?>">View</a></li>
                        <li><a class="dropdown-item text-danger" href="delete_gallery.php?gallery_id=<?php echo $gallery['id']; ?>" onclick="return confirm('Are you sure you want to delete this gallery?');">Delete</a></li>
                    </ul>
                </div>
            </td>
        </tr>
    <?php endforeach; 
else: ?>
    <tr>
        <td colspan="3">No galleries found.</td>
    </tr>
<?php endif; ?>
