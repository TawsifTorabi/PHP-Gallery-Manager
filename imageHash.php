<?php
// Function to generate an image hash
function getImageHash($filePath) {
    $img = imagecreatefromstring(file_get_contents($filePath));
    $img = imagescale($img, 16, 16); // Resize to 16x16
    imagefilter($img, IMG_FILTER_GRAYSCALE); // Convert to grayscale

    $average = 0;
    $pixels = [];
    for ($y = 0; $y < 16; $y++) {
        for ($x = 0; $x < 16; $x++) {
            $gray = (imagecolorat($img, $x, $y) >> 16) & 0xFF; // Get grayscale value
            $pixels[] = $gray;
            $average += $gray;
        }
    }

    $average /= 256; // Average pixel value (16x16 = 256 pixels)
    $hash = '';
    foreach ($pixels as $pixel) {
        $hash .= ($pixel >= $average) ? '1' : '0';
    }

    return $hash;
}