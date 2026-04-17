<?php
function getImageHash($filePath)
{
    if (!file_exists($filePath) || filesize($filePath) == 0) {
        return null;
    }

    $data = @file_get_contents($filePath);
    if (!$data) {
        return null;
    }

    $img = @imagecreatefromstring($data);

    if (!$img) {
        return null; // invalid image (SVG, HTML, corrupt, etc.)
    }

    $img = @imagescale($img, 16, 16);

    if (!$img) {
        return null;
    }

    @imagefilter($img, IMG_FILTER_GRAYSCALE);

    $pixels = [];
    $sum = 0;

    for ($y = 0; $y < 16; $y++) {
        for ($x = 0; $x < 16; $x++) {

            $rgb = imagecolorat($img, $x, $y);

            // correct grayscale extraction
            $gray = ($rgb >> 16) & 0xFF;

            $pixels[] = $gray;
            $sum += $gray;
        }
    }

    $avg = $sum / 256;

    $hash = '';
    foreach ($pixels as $p) {
        $hash .= ($p >= $avg) ? '1' : '0';
    }

    imagedestroy($img);

    return $hash;
}