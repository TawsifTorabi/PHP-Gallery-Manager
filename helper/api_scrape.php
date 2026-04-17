<?php
header('Content-Type: application/json');

set_time_limit(120);
ini_set('memory_limit', '512M');

/* -----------------------------
   FETCH PAGE
------------------------------*/
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

/* -----------------------------
   ABSOLUTE URL FIXER
------------------------------*/
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

/* -----------------------------
   TYPE DETECTOR
------------------------------*/
function detect_type($url)
{
    $clean = strtolower(strtok($url, '?#'));

    $map = [
        'Image' => ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp', '.svg', '.ico'],
        'Video' => ['.mp4', '.webm', '.mov', '.mkv', '.avi'],
        'Audio' => ['.mp3', '.wav', '.ogg', '.aac']
    ];

    foreach ($map as $type => $exts) {
        foreach ($exts as $ext) {
            if (strpos($clean, $ext) !== false) {
                return $type;
            }
        }
    }

    if (strpos($url, 'data:image/') === 0) return "Image";

    return "URL";
}

/* -----------------------------
   FAST SIZE FETCH (HEAD)
------------------------------*/
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

/* -----------------------------
   MAIN SCRAPER
------------------------------*/
if (!isset($_GET['url']) || empty($_GET['url'])) {
    echo json_encode([
        "success" => false,
        "error" => "URL is required"
    ]);
    exit;
}

$url = $_GET['url'];
$html = fetch_page($url);

if (!$html) {
    echo json_encode([
        "success" => false,
        "error" => "Failed to fetch page"
    ]);
    exit;
}

/* -----------------------------
   TITLE
------------------------------*/
preg_match('/<title>(.*?)<\/title>/i', $html, $t);
$title = $t[1] ?? "Scraped Gallery";

/* -----------------------------
   MEDIA STORAGE
------------------------------*/
$media = [];

/* -----------------------------
   1. IMAGE TAGS
------------------------------*/
preg_match_all('/<img\s+[^>]*>/i', $html, $img_tags);

foreach ($img_tags[0] ?? [] as $tag) {

    if (preg_match('/(?:data-src|data-original|src|srcset)=["\']([^"\']+)["\']/i', $tag, $m)) {

        $candidate = $m[1];

        if (strpos($candidate, ',') !== false) {
            $parts = explode(',', $candidate);
            $candidate = trim(explode(' ', trim($parts[0]))[0]);
        }

        $candidate = absolute_url($url, $candidate);

        if (
            strpos($candidate, 'spacer.png') === false &&
            strpos($candidate, 'pixel.gif') === false
        ) {
            if (detect_type($candidate) === "Image") {
                $media[$candidate] = "Image";
            }
        }
    }
}

/* -----------------------------
   2. VIDEO TAGS
------------------------------*/
preg_match_all('/<video[^>]*>(.*?)<\/video>/is', $html, $videos);

foreach ($videos[1] as $vblock) {

    preg_match_all('/src=["\']([^"\']+)["\']/i', $vblock, $sources);

    foreach ($sources[1] ?? [] as $src) {
        $src = absolute_url($url, $src);
        $media[$src] = "Video";
    }
}

/* -----------------------------
   3. LINKS (fallback)
------------------------------*/
preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $links);

foreach ($links[1] ?? [] as $l) {

    $l = absolute_url($url, $l);
    $type = detect_type($l);

    if (!isset($media[$l]) || ($media[$l] === "URL" && $type !== "URL")) {
        $media[$l] = $type;
    }
}

/* -----------------------------
   4. SIZE FETCH
------------------------------*/
$urls = array_keys($media);
$sizes = get_sizes_async($urls);

/* -----------------------------
   5. BUILD RESPONSE
------------------------------*/
$results = [];

foreach ($media as $u => $type) {
    $results[] = [
        "url" => $u,
        "type" => $type,
        "size" => $sizes[$u] ?? 0
    ];
}

/* sort largest first */
usort($results, fn($a, $b) => $b['size'] <=> $a['size']);

/* -----------------------------
   OUTPUT
------------------------------*/
echo json_encode([
    "success" => true,
    "title" => $title,
    "count" => count($results),
    "results" => $results
]);
