<?php
require 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

require('class/assets.php');
Assets::use('bootstrap', 'css');
Assets::use('bootstrap_bundle', 'js');



?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Universal Media Scraper</title>

    <?php Assets::renderCSS(); ?>
    <?php Assets::renderJS(); ?>

    <style>
        body {
            background: #f8f9fa;
        }

        .media-card img {
            height: 180px;
            object-fit: cover;
        }

        .media-card {
            transition: 0.2s;
        }

        .media-card:hover {
            transform: scale(1.02);
        }

        .top-bar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: white;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        .loading-overlay {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .badge-type {
            position: absolute;
            top: 8px;
            left: 8px;
        }
    </style>
</head>

<body>

    <?php include 'navbar.php'; ?>

    <div class="container py-3">

        <!-- TOP BAR -->
        <div class="top-bar shadow-sm rounded">
            <div class="row g-2">

                <div class="col-9">
                    <input type="text" id="urlInput" class="form-control" placeholder="Enter URL to scrape...">
                </div>

                <div class="col-3 d-grid">
                    <button class="btn btn-primary" onclick="scrape()">Scrape</button>
                </div>

            </div>
        </div>

        <!-- LOADING -->
        <div id="loading" class="loading-overlay">
            <div class="spinner-border text-primary"></div>
            <p class="mt-2">Scraping...</p>
        </div>

        <!-- TITLE -->
        <h5 class="mt-3" id="pageTitle"></h5>

        <!-- ACTION BAR -->
        <div class="my-2 d-none" id="actionBar">

            <input type="text"
                id="gallerySearch"
                class="form-control"
                placeholder="Search gallery name...">

            <div id="searchResults" class="list-group mt-2"></div>

            <button class="btn btn-success mt-2" onclick="uploadToGallery()">
                Upload to Selected Gallery
            </button>

            <div class="text-muted mt-1">
                Selected: <span id="selectedGalleryName">None</span>
            </div>

            <button class="btn btn-success" onclick="createGallery()">
                Create Gallery
            </button>

            <span class="ms-2 text-muted" id="selectedCount">0 selected</span>

        </div>

        <!-- RESULTS -->
        <div class="row g-3" id="results"></div>

    </div>
    <script>
        //Frontend logic for searching galleries
        let selectedGalleryId = null;

        window.addEventListener("DOMContentLoaded", () => {

            const input = document.getElementById("gallerySearch");
            const resultsBox = document.getElementById("searchResults");

            if (!input) return;

            let debounceTimer;

            input.addEventListener("input", function() {

                clearTimeout(debounceTimer);

                const query = this.value.trim();

                if (query.length < 2) {
                    resultsBox.innerHTML = "";
                    return;
                }

                debounceTimer = setTimeout(async () => {

                    const res = await fetch("helper/api_get_galleries.php?q=" + encodeURIComponent(query));
                    const data = await res.json();

                    resultsBox.innerHTML = "";

                    data.forEach(g => {
                        const div = document.createElement("div");
                        div.className = "list-group-item list-group-item-action";
                        div.innerText = g.title;

                        div.onclick = () => {
                            selectedGalleryId = g.id;
                            document.getElementById("selectedGalleryName").innerText = g.title;
                            resultsBox.innerHTML = "";
                            input.value = g.title;
                        };

                        resultsBox.appendChild(div);
                    });

                }, 300);
            });
        });


        async function uploadToGallery() {

            if (selectedMedia.length === 0) {
                alert("Select items first");
                return;
            }

            // ✅ THIS IS WHERE IT GOES
            const galleryId = selectedGalleryId;

            if (!galleryId) {
                alert("Please select a gallery from search");
                return;
            }

            const btn = document.querySelector("#actionBar button");
            btn.disabled = true;
            btn.innerText = "Uploading...";

            try {

                const res = await fetch('helper/create_gallery_from_scrape.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        mode: "attach",
                        gallery_id: galleryId,
                        media: selectedMedia
                    })
                });

                const data = await res.json();

                console.log(data);

                if (data.status === "complete") {
                    alert("Uploaded successfully!");
                    window.location.href = "display_gallery.php?id=" + galleryId;
                } else {
                    alert("Failed: " + (data.message || "Unknown error"));
                }

            } catch (e) {
                console.error(e);
                alert("Upload error");
            }

            btn.disabled = false;
            btn.innerText = "Upload to Gallery";
        }
    </script>
    <script>
        let selectedMedia = [];

        /* ---------------------------
           SCRAPE FUNCTION
        ----------------------------*/
        async function scrape() {
            const url = document.getElementById('urlInput').value;
            if (!url) return alert("Enter URL");

            document.getElementById('loading').style.display = "block";
            document.getElementById('results').innerHTML = "";
            document.getElementById('actionBar').classList.add('d-none');
            selectedMedia = [];

            selectedGalleryId = null;

            document.getElementById("searchResults").innerHTML = "";
            document.getElementById("gallerySearch").value = "";
            document.getElementById("selectedGalleryName").innerText = "None";


            try {
                const res = await fetch("helper/api_scrape.php?url=" + encodeURIComponent(url));
                const data = await res.json();

                document.getElementById('loading').style.display = "none";

                if (!data.success) {
                    alert(data.error);
                    return;
                }

                document.getElementById('pageTitle').innerText = data.title;
                render(data.results);

            } catch (e) {
                document.getElementById('loading').style.display = "none";
                alert("Failed to scrape");
                console.error(e);
            }
        }

        /* ---------------------------
           RENDER RESULTS
        ----------------------------*/
        function render(items) {
            const container = document.getElementById('results');

            items.forEach((item, i) => {

                const col = document.createElement('div');
                col.className = "col-6 col-md-4 col-lg-3";

                col.innerHTML = `
                    <div class="card media-card h-100 position-relative">

                        <span class="badge bg-dark badge-type">${item.type}</span>

                        ${renderPreview(item)}

                        <div class="card-body p-2">

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                    onchange="toggleSelect(this, '${item.url}', '${item.type}')">
                                <label class="form-check-label small">
                                    ${(item.size / 1024).toFixed(1)} KB
                                </label>
                            </div>

                            <a href="${item.url}" target="_blank" class="small text-truncate d-block">
                                ${item.url.split('/').pop().substring(0, 30)}
                            </a>

                        </div>
                    </div>
                    `;

                container.appendChild(col);
            });
        }

        function renderPreview(item) {

            if (item.type === "Image") {
                return `
            <img src="proxy_images.php?url=${encodeURIComponent(item.url)}"
                 class="card-img-top"
                 style="height:180px; object-fit:cover;">
                `;
            }

            if (item.type === "Video") {
                return `
                    <video class="w-100"
                        controls
                        preload="metadata"
                        style="height:180px; object-fit:cover;">
                        <source src="${item.url}">
                    </video>
                `;
            }

            return `
                <div class="p-5 text-center text-muted">
                    No Preview
                </div>
            `;
        }


        /* ---------------------------
           SELECT MEDIA
        ----------------------------*/
        function toggleSelect(el, url, type) {

            if (el.checked) {
                selectedMedia.push({
                    mediatype: type.toLowerCase(),
                    mediaElem: url
                });
            } else {
                selectedMedia = selectedMedia.filter(m => m.mediaElem !== url);
            }

            const bar = document.getElementById('actionBar');

            if (selectedMedia.length > 0) {
                bar.classList.remove('d-none');
            } else {
                bar.classList.add('d-none');
            }

            document.getElementById('selectedCount').innerText =
                selectedMedia.length + " selected";
        }

        /* ---------------------------
           CREATE GALLERY
        ----------------------------*/
        async function createGallery() {

            if (selectedMedia.length === 0) {
                alert("Select items first");
                return;
            }

            const title = prompt("Gallery name:");
            if (!title) return;

            const btn = document.querySelector("#actionBar button");
            btn.disabled = true;
            btn.innerText = "Creating...";

            try {

                const res = await fetch('helper/create_gallery_from_scrape.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        title,
                        media: selectedMedia
                    })
                });

                const data = await res.json();

                console.log(data);

                if (data.status === "complete") {
                    window.location.href = "display_gallery.php?id=" + data.gallery_id;
                } else {
                    alert("Failed: " + (data.message || "Unknown error"));
                }

            } catch (e) {
                console.error(e);
                alert("Error creating gallery");
            }

            btn.disabled = false;
            btn.innerText = "Create Gallery";
        }
    </script>

</body>

</html>