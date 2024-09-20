<?php
// Define menu items in an associative array
$menu_items = [
  'dashboard.php' => 'Dashboard',
  'gallery_form.php' => 'Create Gallery',
  'logout.php' => 'Logout' // You can easily add more items here
];

// Get the current file name to determine the active menu item
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Gallery</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <?php foreach ($menu_items as $file => $title) : ?>
          <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == $file) ? 'active' : ''; ?>" href="<?php echo $file; ?>">
              <?php echo $title; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <!-- Search bar -->
      <form class="d-flex ms-3" action="search_gallery.php" method="get">
        <input class="form-control me-2" type="search" name="query" placeholder="Search for galleries" required>
        <button class="btn btn-outline-light" type="submit">Search</button>
      </form>
    </div>
  </div>
</nav>

<br><br><br><br>
