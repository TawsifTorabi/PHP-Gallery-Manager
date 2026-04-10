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
            .card, .modal-content, .dropdown-menu .pin-container {
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


<style>
  /* Blurs everything inside this container */
  .blurred {
    filter: blur(15px);
    pointer-events: none;
    user-select: none;
  }

  #lock-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.85);
    /* Slightly darker for better contrast */
    display: none;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    color: white;
    z-index: 9999;
  }

  /* Support for Dark/Light Mode */
  .pin-container {
    background-color: #ffffff;
    /* Light mode default */
    color: #212529;
    padding: 2.5rem;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    width: 100%;
    max-width: 400px;
    transition: background-color 0.3s ease, color 0.3s ease;
  }

  /* Dark Mode specific styles for the container */
  [data-bs-theme="dark"] .pin-container {
    background-color: #1e1e1e;
    /* Matches your existing custom dark color */
    color: #e0e0e0;
    border: 1px solid #333;
  }

  .pin-container h3 {
    margin-bottom: 1rem;
    font-weight: bold;
  }

  /* PIN input styling */
  #pin-input {
    font-size: 32px;
    width: 180px;
    text-align: center;
    display: block;
    margin: 1.5rem auto;
    letter-spacing: 10px;
    border-radius: 8px;
    border: 2px solid #ddd;
    background: transparent;
    color: inherit;
    /* Inherits black or white based on theme */
  }

  [data-bs-theme="dark"] #pin-input {
    border-color: #444;
  }

  #pin-input:focus {
    border-color: #0d6efd;
    outline: none;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
  }
</style>
<div id="lock-overlay">
  <div class="pin-container">
    <input type="text" name="fake_user" style="display:none;" aria-hidden="true">
    <input type="password" name="fake_password" style="display:none;" aria-hidden="true">

    <h3>Session Locked</h3>
    <input type="password" id="pin-input" autocomplete="off">
    <button id="unlock-btn" class="btn btn-danger">Unlock</button>
    <span id="error-msg" style="color: red; display: none;">Invalid PIN</span>
  </div>
</div>
<script type="module">
  const SessionManager = {
    timeout: 30000,
    timer: null,
    contentEl: document.getElementById('main-content'),
    overlayEl: document.getElementById('lock-overlay'),

    async init() {
      // 1. Immediate Check: If localStorage says we are locked, lock now!
      if (localStorage.getItem('is_session_locked') === 'true') {
        this.lock(false); // Lock immediately without waiting
      }

      try {
        const response = await fetch('useractivity.php?action=get_session_settings');
        const data = await response.json();

        if (!data || !data.timeout_enabled) return;

        this.timeout = parseInt(data.user_timeout_preference) * 1000;

        this.setupEventListeners();
        this.resetTimer(); // Start the first countdown
      } catch (e) {
        console.error("Session settings load failed", e);
      }
    },

    setupEventListeners() {
      ['mousemove', 'keydown', 'mousedown', 'scroll'].forEach(event => {
        document.addEventListener(event, () => {
          // Only reset if we aren't currently locked
          if (localStorage.getItem('is_session_locked') !== 'true') {
            this.resetTimer();
          }
        });
      });

      document.getElementById('unlock-btn').addEventListener('click', () => this.validatePin());
      document.getElementById('pin-input').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') this.validatePin();
      });
    },

    resetTimer() {
      clearTimeout(this.timer);
      this.timer = setTimeout(() => this.lock(true), this.timeout);
    },

    lock(saveToStorage = true) {
      if (saveToStorage) {
        localStorage.setItem('is_session_locked', 'true');
      }
      this.contentEl.classList.add('blurred');
      this.overlayEl.style.display = 'flex';
      document.getElementById('pin-input').focus();
    },

    async validatePin() {
      const pinInput = document.getElementById('pin-input');
      const error = document.getElementById('error-msg');

      const formData = new FormData();
      formData.append('pin', pinInput.value);

      try {
        const response = await fetch('useractivity.php?action=validate_pin', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();

        if (result.success) {
          this.unlock();
        } else {
          error.style.display = 'block';
          pinInput.value = '';
          pinInput.classList.add('is-invalid');
        }
      } catch (e) {
        alert("Server error during validation." + e.message);
        console.log("Server error during validation." + e.message);
      }
    },

    unlock() {
      localStorage.setItem('is_session_locked', 'false');
      this.contentEl.classList.remove('blurred');
      this.overlayEl.style.display = 'none';
      document.getElementById('pin-input').value = '';
      document.getElementById('error-msg').style.display = 'none';
      this.resetTimer();
    }
  };

  // Initialize
  SessionManager.init();
</script>
<br><br><br><br>

<div id="main-content">