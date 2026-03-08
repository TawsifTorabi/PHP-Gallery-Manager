<?php

require "site_profiles.php";

$url = $_POST['url'] ?? '';

if (!$url) {
    die("No URL provided");
}

$host = parse_url($url, PHP_URL_HOST);

$profile = $profiles["default"];

foreach ($profiles as $domain => $p) {
    if (strpos($host, $domain) !== false) {
        $profile = $p;
        break;
    }
}

$html = file_get_contents($url);

libxml_use_internal_errors(true);

$dom = new DOMDocument();
$dom->loadHTML($html);

$xpath = new DOMXPath($dom);

$content = $xpath->query($profile['content_selector'])->item(0);

$results = [];



foreach ($profile['media_selectors'] as $selector) {

    $nodes = $xpath->query($selector);

    foreach ($nodes as $node) {

        $src = $node->getAttribute('src');

        if (!$src) continue;

        $media_url = resolve_url($src, $url);

        $headers = @get_headers($media_url, 1);

        if (!$headers) continue;

        $size = $headers['Content-Length'] ?? 0;

        if ($size < 50000) continue; // 50KB

        $type = $headers['Content-Type'] ?? 'unknown';

        $dimensions = @getimagesize($media_url);

        if ($dimensions) {
            if ($dimensions[0] < 100 || $dimensions[1] < 100) continue;
        }

        $results[] = [
            "url" => $media_url,
            "type" => $type,
            "size" => $size
        ];
    }
}


$links = [];

$link_nodes = $xpath->query($profile['link_selector']);

foreach ($link_nodes as $link) {

    $href = $link->getAttribute('href');

    if (!$href) continue;

    $links[] = resolve_url($href, $url);
}


function resolve_url($relative, $base)
{
    if (parse_url($relative, PHP_URL_SCHEME) != '') return $relative;

    if ($relative[0] == '#') return $base;

    $parsed = parse_url($base);

    $scheme = $parsed['scheme'];
    $host = $parsed['host'];

    return $scheme . "://" . $host . "/" . ltrim($relative, "/");
}