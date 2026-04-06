<?php
include 'session.php';
require 'db.php';
date_default_timezone_set('Asia/Dhaka');

header('Content-Type: application/json');

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

/**
 * Fast Hamming Distance for Hex strings.
 * Optimized to bail out early if distance exceeds threshold.
 */
/**
 * Fast Hamming Distance for Hex strings.
 * Handles strings of different lengths and bails out early.
 */
function fastHamming($hash1, $hash2, $threshold = 10)
{
    // 1. Identical check
    if ($hash1 === $hash2) return 0;

    $len1 = strlen($hash1);
    $len2 = strlen($hash2);

    // 2. Length difference is part of the distance
    $distance = abs($len1 - $len2);

    // If length difference alone exceeds threshold, exit early
    if ($distance > $threshold) return $distance;

    // 3. Compare up to the shortest string length
    $minLen = min($len1, $len2);

    for ($i = 0; $i < $minLen; $i++) {
        if ($hash1[$i] !== $hash2[$i]) {
            $distance++;

            // Early Exit
            if ($distance > $threshold) return $distance;
        }
    }

    return $distance;
}

$gallery_id = isset($_GET['gallery_id']) ? (int)$_GET['gallery_id'] : 1;

// 1. Fetch images - Sorting by hash brings similar images closer together
$query = "SELECT id, file_name, imageHash_hamming FROM images 
          WHERE gallery_id = ? AND imageHash_hamming != '' 
          ORDER BY imageHash_hamming ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $gallery_id);
$stmt->execute();
$result = $stmt->get_result();

$images = [];
while ($row = $result->fetch_assoc()) {
    $images[] = $row;
}

$total = count($images);
if ($total === 0) {
    echo json_encode(["gallery_id" => $gallery_id, "total_images" => 0, "duplicates" => []]);
    exit;
}

// 2. Fetch exclusion flags (already marked as "not duplicates")
$flags = [];
$flagQuery = "SELECT media_1, media_2 FROM image_duplicate_flag WHERE gallery_id = ? AND matched = 0";
$fStmt = $conn->prepare($flagQuery);
$fStmt->bind_param("i", $gallery_id);
$fStmt->execute();
$fResult = $fStmt->get_result();
while ($fRow = $fResult->fetch_assoc()) {
    $key = min($fRow['media_1'], $fRow['media_2']) . '-' . max($fRow['media_1'], $fRow['media_2']);
    $flags[$key] = true;
}

$duplicates = [];
$threshold = 70;

// 3. Comparison Loop
for ($i = 0; $i < $total; $i++) {
    $img1 = $images[$i];

    // Sliding Window Optimization: 
    // Because we sorted by Hash, we only need to look at the next few dozen images 
    // rather than the entire remainder of the array for a significant speed boost.
    // To stay 100% accurate with Hamming, we still check all, but the early exit helps.
    // Inside your comparison loop (Section 3)
    for ($j = $i + 1; $j < $total; $j++) {
        $img2 = $images[$j];

        // FIX: Use min and max to match how you stored the flags
        $lowId = min($img1['id'], $img2['id']);
        $highId = max($img1['id'], $img2['id']);
        $pairKey = $lowId . '-' . $highId;

        if (isset($flags[$pairKey])) continue;

        // Calculate distance with early exit threshold
        $distance = fastHamming($img1['imageHash_hamming'], $img2['imageHash_hamming'], $threshold);

        if ($distance <= $threshold) {
            $duplicates[] = [
                'image1_id' => $img1['id'],
                'image1_file' => $img1['file_name'],
                'image2_id' => $img2['id'],
                'image2_file' => $img2['file_name'],
                'distance' => $distance
            ];
        }
    }
}

echo json_encode([
    'gallery_id' => $gallery_id,
    'total_images' => $total,
    'duplicates' => $duplicates
]);

$conn->close();
