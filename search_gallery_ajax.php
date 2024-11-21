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
$stmt = $conn->prepare("SELECT id, title, created_at, description, hero_images FROM galleries WHERE created_by = ? AND title LIKE ? ORDER BY id DESC LIMIT 30");
$search_query = "%" . $query . "%";
$stmt->bind_param("is", $user_id, $search_query);
$stmt->execute();
$result = $stmt->get_result();
$galleries = $result->fetch_all(MYSQLI_ASSOC);

if (count($galleries) > 0):
    foreach ($galleries as $gallery): ?>
        <tr>
            <td>
                <div>
                    <!-- Display Hero Images -->
                    <?php if (!empty($gallery['hero_images'])): ?>
                        <div class="mb-2">
                            <div class="hero-images">
                                <?php
                                $hero_images = explode('$%@!', $gallery['hero_images']);
                                foreach ($hero_images as $image): ?>
                                    <img src="serve_image.php?w=80&file=<?php echo rawurlencode($image); ?>"
                                        class=" mini-thumb img-thumbnail"
                                        alt="Hero Image">
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </td>
            <td>
                <a href="display_gallery.php?id=<?php echo $gallery['id']; ?>"><?php echo htmlspecialchars($gallery['title']); ?></a>
                <br>
                <small class="text-muted text-sm" style="font-size: 12px"><?php echo date('Y-m-d H:i:s', strtotime($gallery['created_at'])); ?></small>
            </td>
            <td>
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                        &nbsp;
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
    <?php endforeach;
else: ?>
    <tr>
        <td colspan="3">No galleries found.</td>
    </tr>
<?php endif; ?>