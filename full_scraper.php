<?php

set_time_limit(120);
ini_set('memory_limit', '512M');

function fetch_page($url)
{

    $ch = curl_init();

    curl_setopt_array($ch, [

        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => '',
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36'

    ]);

    $html = curl_exec($ch);

    curl_close($ch);

    return $html;
}



function absolute_url($base, $rel)
{

    if (!$rel) return '';

    if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;

    if (substr($rel, 0, 2) == '//') {

        $parts = parse_url($base);
        return $parts['scheme'] . ":" . $rel;
    }

    if ($rel[0] == '/') {

        $parts = parse_url($base);
        return $parts['scheme'] . '://' . $parts['host'] . $rel;
    }

    return rtrim($base, '/') . '/' . $rel;
}

function trim_url($url, $len = 80)
{

    if (strlen($url) <= $len) return $url;

    return substr($url, 0, $len) . '...';
}

function get_sizes_async($urls)
{

    $mh = curl_multi_init();
    $chs = [];
    $sizes = [];

    foreach ($urls as $u) {

        $ch = curl_init();

        curl_setopt_array($ch, [

            CURLOPT_URL => $u,
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true

        ]);

        curl_multi_add_handle($mh, $ch);

        $chs[$u] = $ch;
    }

    $running = null;

    do {

        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    foreach ($chs as $url => $ch) {

        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

        $sizes[$url] = $size > 0 ? $size : 0;

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }

    curl_multi_close($mh);

    return $sizes;
}

function detect_type($url)
{
    // 1. Normalize: Lowercase and strip ?query or #fragments
    $clean_path = strtolower(strtok($url, '?#'));

    // 2. Define Media Signatures
    // We check for these specifically with a leading dot to avoid partial matches
    $signatures = [
        'Image' => ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp', '.svg', '.ico'],
        'Video' => ['.mp4', '.webm', '.mov', '.mkv', '.avi'],
        'Audio' => ['.mp3', '.wav', '.ogg', '.aac']
    ];

    // 3. Search for the signature ANYWHERE in the URL
    // This catches: /file.jpg.3f4eb79...jpg
    foreach ($signatures as $type => $extensions) {
        foreach ($extensions as $ext) {
            if (strpos($clean_path, $ext) !== false) {
                return $type;
            }
        }
    }

    // 4. Special Case: Data URIs (Base64)
    if (strpos($url, 'data:image/') === 0) return "Image";

    return "URL";
}

// 3. Fix the Scraper Loop Scope
// Ensure $links is defined and check if $links[1] exists before looping
if (isset($links) && isset($links[1])) {
    foreach ($links[1] as $l) {
        $l = absolute_url($url, $l);

        $type = detect_type($l);

        // IMPORTANT: Don't let a "URL" detection overwrite a previously 
        // found "Image" or "Video" from the regex phase
        if (!isset($media[$l]) || ($media[$l] === "URL" && $type !== "URL")) {
            $media[$l] = $type;
        }
    }
}

$results = [];
$title = "";

if (isset($_POST['scrape'])) {
    $url = $_POST['url'];
    $html = fetch_page($url);

    preg_match('/<title>(.*?)<\/title>/i', $html, $t);
    $title = $t[1] ?? "Scraped Gallery";

    $media = [];


    /* 1. Greedy Image Detection (Tag-Based) */
    // This regex grabs the full <img ... /> tag content
    preg_match_all('/<img\s+[^>]*>/i', $html, $img_tags);

    foreach (($img_tags[0] ?? []) as $tag) {
        $found_url = '';

        // Priority list: data-src (lazy-load), then original src
        // This regex looks for attributes and picks the one that looks like a real image
        if (preg_match('/(?:data-src|data-original|src|srcset)=["\']([^"\'\s]+)["\']/i', $tag, $attr_matches)) {
            $candidate_url = $attr_matches[1];

            // If it's a srcset, take the first link
            if (strpos($candidate_url, ',') !== false) {
                $parts = explode(',', $candidate_url);
                $candidate_url = trim(explode(' ', trim($parts[0]))[0]);
            }

            $candidate_url = absolute_url($url, $candidate_url);

            // EXTRA PRECISION: Ignore generic spacers or tracking pixels
            if (strpos($candidate_url, 'spacer.png') === false && strpos($candidate_url, 'pixel.gif') === false) {
                if (detect_type($candidate_url) === "Image") {
                    $media[$candidate_url] = "Image";
                }
            }
        }
    }

    // 2. Detect videos from <video> and <source> tags
    preg_match_all('/<(video|source)[^>]+src=["\']([^"\']+)["\']/i', $html, $vid_tags);
    $vid_sources = $vid_tags[2] ?? [];
    foreach ($vid_sources as $vid_url) {
        $vid_url = absolute_url($url, $vid_url);
        $media[$vid_url] = "Video";
    }

    // 3. Detect remaining media from <a> hrefs
    // Using the robust detector to catch those weird hashed filenames in links
    preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $links_match);
    $all_links = $links_match[1] ?? [];

    foreach ($all_links as $l) {
        $l = absolute_url($url, $l);

        // Use our robust detector for anything found in a link
        $detected_type = detect_type($l);

        // Logic: Add if new, or upgrade "URL" to "Image/Video" if the detector finds a match
        if (!isset($media[$l]) || ($media[$l] === "URL" && $detected_type !== "URL")) {
            $media[$l] = $detected_type;
        }
    }

    // 4. Fetch sizes and format results
    $sizes = get_sizes_async(array_keys($media));

    foreach ($media as $u => $type) {
        $results[] = [
            "url" => $u,
            "type" => $type,
            "size" => $sizes[$u] ?? 0
        ];
    }

    /* sort largest first */
    usort($results, function ($a, $b) {
        return $b['size'] <=> $a['size'];
    });
}

$gallery_images = [];
$gallery_desc = "";

if (isset($_POST['create_gallery'])) {

    $title = $_POST['gallery_title'];

    foreach ($_POST['select'] as $i => $url) {

        $type = $_POST['type'][$i];

        if ($type == "Image") {

            $gallery_images[] = $url;
        } else {

            $gallery_desc .= '<a href="' . $url . '" target="_blank">' . $url . '</a><br>';
        }
    }
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Universal Media Scraper</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .preview {

            max-width: 120px;
            max-height: 120px;

        }

        .gallery img {

            max-width: 200px;
            margin: 10px;

        }
    </style>

</head>

<body class="container mt-4">

    <h3>Universal Media Scraper</h3>

    <form method="post">

        <div class="input-group mb-3">

            <input type="text" name="url" class="form-control" placeholder="Enter URL">

            <button name="scrape" class="btn btn-primary">Scrape</button>

        </div>

    </form>

    <?php if (!empty($results)) { ?>

        <form method="post" id="mediaForm">

            <button type="button" id="btnCreateGallery" name="create_gallery" class="btn btn-success">
                Create Gallery
            </button><br><br>

            <input type="hidden" name="gallery_title" id="gallery_title" value="<?php echo htmlspecialchars($title); ?>">

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Select</th>
                        <th>Preview</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $k => $r) { ?>
                        <tr class="media-row">
                            <td>
                                <input type="checkbox" class="media-select" name="select[<?php echo $k; ?>]" value="<?php echo $r['url']; ?>">
                                <input type="hidden" class="media-type" name="type[<?php echo $k; ?>]" value="<?php echo $r['type']; ?>">
                            </td>
                            <td>
                                <?php if ($r['type'] == "Image") { ?>
                                    <img src="proxy_images.php?url=<?php echo urlencode($r['url']); ?>" class="preview">
                                <?php } else {
                                    echo "-";
                                } ?>
                            </td>
                            <td><?php echo $r['type']; ?></td>
                            <td>
                                <?php echo ($r['size'] > 0) ? round($r['size'] / 1024, 1) . " KB" : "-"; ?>
                            </td>
                            <td>
                                <a href="<?php echo $r['url']; ?>" target="_blank">
                                    <?php echo trim_url($r['url']); ?>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </form>

        <script>
            let progressInterval;

            function trackProgress() {
                progressInterval = setInterval(() => {
                    fetch('get_progress.php') // Create this tiny file (see below)
                        .then(res => res.json())
                        .then(data => {
                            let percentage = (data.progress / data.total) * 100;
                            const pb = document.getElementById('progressBar');
                            pb.style.width = percentage + '%';
                            pb.innerText = Math.round(percentage) + '% (' + data.progress + '/' + data.total + ')';
                        });
                }, 500);
            }

            document.getElementById('btnCreateGallery').addEventListener('click', function() {
                const title = prompt("Gallery Name:");
                if (!title) return;

                // ... (Collect selectedMedia array from previous response) ...

                trackProgress(); // Start tracking

                fetch('gallery_json_receiver.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        title: title,
                        media: selectedMedia
                    })
                }).then(res => res.json()).then(data => {
                    clearInterval(progressInterval);
                    window.location.href = 'display_gallery.php?id=' + data.gallery_id;
                });
            });

            // Replace the 'btnCreateGallery' click event in your Scraper page
            document.getElementById('btnCreateGallery').addEventListener('click', function() {
                const title = prompt("Please enter a name for your new gallery:", "<?php echo htmlspecialchars($title); ?>");
                if (!title) return; // Cancel if no title

                const selectedMedia = [];
                const rows = document.querySelectorAll('.media-row');

                rows.forEach(row => {
                    const checkbox = row.querySelector('.media-select');
                    const typeInput = row.querySelector('.media-type');
                    if (checkbox.checked) {
                        selectedMedia.push({
                            mediatype: typeInput.value.toLowerCase(),
                            mediaElem: checkbox.value
                        });
                    }
                });

                if (selectedMedia.length === 0) {
                    alert("Please select at least one item.");
                    return;
                }

                // Prepare UI
                const btn = document.getElementById('btnCreateGallery');
                const progressBar = document.getElementById('progressBar'); // Ensure this ID exists in your HTML
                btn.disabled = true;
                btn.innerText = "Downloading...";

                // Simulate Progress (Since PHP download is a single request)
                let fakeProgress = 0;
                const progressInterval = setInterval(() => {
                    if (fakeProgress < 90) {
                        fakeProgress += 5;
                        updateBar(fakeProgress);
                    }
                }, 400);

                function updateBar(p) {
                    progressBar.style.width = p + '%';
                    progressBar.innerText = Math.round(p) + '%';
                }

                // Send the Payload
                fetch('create_gallery_from_JSON.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            title: title,
                            media: selectedMedia
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        clearInterval(progressInterval);
                        updateBar(100);
                        if (data.success) {
                            alert(data.message);
                            window.location.href = 'display_gallery.php?id=' + data.gallery_id;
                        } else {
                            alert("Error: " + data.error);
                            btn.disabled = false;
                        }
                    })
                    .catch(err => {
                        clearInterval(progressInterval);
                        console.error(err);
                        alert("Upload failed.");
                        btn.disabled = false;
                    });
            });
        </script>

    <?php } ?>

    <?php if (!empty($gallery_images)) { ?>

        <hr>

        <h2><?php echo htmlspecialchars($title); ?></h2>

        <div class="gallery">

            <?php foreach ($gallery_images as $g) { ?>

                <img src="<?php echo $g; ?>">

            <?php } ?>

        </div>

        <div>

            <h4>Gallery Description</h4>

            <?php echo $gallery_desc; ?>

        </div>

    <?php } ?>

</body>

</html>