<?php
// Define menu items in an associative array
$menu_items = [
  'dashboard.php' => 'Dashboard',
  'merge_gallery_view.php' => 'Merge Galleries',
  'gallery_form.php' => 'Create Gallery',
  'full_scraper.php' => 'Web Scraper',
  'logout.php' => 'Logout' // You can easily add more items here
];

// Get the current file name to determine the active menu item
$current_page = basename($_SERVER['PHP_SELF']);
?>

<script>
  (function() {
    // Load saved mode
    const savedMode = localStorage.getItem("darkmode") || "light";
    applyMode(savedMode);

    // Global toggle function
    window.toggledarkmode = function() {
      const current = document.documentElement.getAttribute("data-bs-theme") === "dark" ? "dark" : "light";
      const next = current === "dark" ? "light" : "dark";
      applyMode(next);
      localStorage.setItem("darkmode", next);
    };

    function applyMode(mode) {
      // Bootstrap native support
      document.documentElement.setAttribute("data-bs-theme", mode);

      if (mode === "dark") {
        enableCustomDark();
      } else {
        disableCustomDark();
      }
    }

    function enableCustomDark() {
      if (document.getElementById("custom-darkmode-style")) return;

      const style = document.createElement("style");
      style.id = "custom-darkmode-style";
      style.innerHTML = `
            body {
                background-color: #121212 !important;
                color: #e0e0e0 !important;
            }

            /* Text elements */
            p, span, label, div, li, a, h1, h2, h3, h4, h5, h6 {
                color: #e0e0e0 !important;
            }

            /* Links */
            a {
                color: #90caf9 !important;
            }

            /* Cards, modals, dropdowns */
            .card, .modal-content, .dropdown-menu {
                background-color: #1e1e1e !important;
                color: #e0e0e0 !important;
                border-color: #333 !important;
            }

            /* Inputs */
            input, textarea, select {
                background-color: #1e1e1e !important;
                color: #e0e0e0 !important;
                border: 1px solid #444 !important;
            }

            /* Tables */
            table {
                color: #e0e0e0 !important;
            }
            thead {
                background-color: #222 !important;
            }

            /* Buttons */
            .btn {
                border-color: #444 !important;
            }

            /* Scrollbar (optional) */
            ::-webkit-scrollbar {
                width: 8px;
            }
            ::-webkit-scrollbar-track {
                background: #121212;
            }
            ::-webkit-scrollbar-thumb {
                background: #444;
                border-radius: 4px;
            }

            /* Images (optional tweak) */
            img {
                opacity: 0.9;
            }
        `;
      document.head.appendChild(style);
    }

    function disableCustomDark() {
      const style = document.getElementById("custom-darkmode-style");
      if (style) style.remove();
    }
  })();
</script>

<style>
  #darkModeBtn {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    border: none;
    background: #212529;
    color: #fff;
    font-size: 18px;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    transition: all 0.3s ease;
  }

  #darkModeBtn:hover {
    transform: scale(1.1);
  }

  /* Light mode appearance */
  [data-bs-theme="light"] #darkModeBtn {
    background: #f8f9fa;
    color: #000;
  }
</style>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
  <div class="container-fluid">
    <span class="navbar-brand">
      Gallery
      <button onclick="toggledarkmode()" id="darkModeBtn">
        🌙
      </button>
    </span>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <?php foreach ($menu_items as $file => $title) : ?>
          <li class="nav-item">
            <a
              class="nav-link <?php echo ($current_page == $file) ? 'active' : ''; ?>"
              href="<?php echo $file; ?>"
              <?php if ($file === 'logout.php') echo 'onclick="return confirm(\'Are you sure you want to logout?\');"'; ?>>
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