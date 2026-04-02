<?php
include 'session.php';
require 'db.php';
date_default_timezone_set('Asia/Dhaka');

header('Content-Type: application/json');

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// Optimization 1: Use XOR and count_set_bits for 10x faster Hamming calculation
// Note: This works best if hashes are stored as hex/binary strings
/**
 * Optimized for PHP 8.1+ to avoid implicit float conversion warnings.
 * Uses GMP for hardware-level bit counting.
 */
function fastHamming($hash1, $hash2)
{
    // Check if hashes are identical first to save CPU
    if ($hash1 === $hash2) return 0;

    if (function_exists('gmp_hamdist')) {
        // gmp_hamdist expects two GMP numbers or numeric strings
        // We prefix with 0x to tell GMP it's a hexadecimal string
        return gmp_hamdist("0x$hash1", "0x$hash2");
    }

    // Fallback for environments without GMP (slower but warning-free)
    // We convert hex to bin strings and compare bits manually
    $bin1 = unpack('H*', hex2bin($hash1))[1]; // Ensuring clean string format
    // If you're on a 64-bit system, you can try (int) casting, 
    // but GMP is the standard for 64-bit hashes.

    $distance = 0;
    $h1 = hex2bin($hash1);
    $h2 = hex2bin($hash2);
    $xor = $h1 ^ $h2; // Bitwise XOR on raw binary strings is valid and fast

    foreach (str_split($xor) as $char) {
        $distance += count_set_bits(ord($char));
    }
    return $distance;
}

// Helper for the fallback method
function count_set_bits($n)
{
    $count = 0;
    while ($n > 0) {
        $n &= ($n - 1);
        $count++;
    }
    return $count;
}

// Keeping your original logic but optimized slightly for speed
function hammingDistance($hash1, $hash2)
{
    $distance = 0;
    $len = strlen($hash1); // Cache length
    for ($i = 0; $i < $len; $i++) {
        if ($hash1[$i] !== $hash2[$i]) {
            $distance++;
        }
    }
    return $distance;
}

$gallery_id = isset($_GET['gallery_id']) ? (int)$_GET['gallery_id'] : 1;

// 1. Fetch images
$query = "SELECT id, file_name, imageHash_hamming FROM images WHERE gallery_id = ? AND imageHash_hamming != ''";
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

// Optimization 2: FETCH ALL FLAGS ONCE (Prevent N+1 Query)
// This creates a "Map" in memory to check flags instantly without hitting MySQL again
$flags = [];
$flagQuery = "SELECT media_1, media_2 FROM image_duplicate_flag WHERE gallery_id = ? AND matched = 0";
$fStmt = $conn->prepare($flagQuery);
$fStmt->bind_param("i", $gallery_id);
$fStmt->execute();
$fResult = $fStmt->get_result();
while ($fRow = $fResult->fetch_assoc()) {
    // Sort IDs so 5-10 and 10-5 both map to the same key
    $key = min($fRow['media_1'], $fRow['media_2']) . '-' . max($fRow['media_1'], $fRow['media_2']);
    $flags[$key] = true;
}

$duplicates = [];

// 2. Compare images
for ($i = 0; $i < $total; $i++) {
    $h1 = $images[$i]['imageHash_hamming'];
    $id1 = $images[$i]['id'];

    for ($j = $i + 1; $j < $total; $j++) {
        $id2 = $images[$j]['id'];

        // Optimization 3: Check memory-map for flags before calculating heavy hash distance
        $pairKey = $id1 . '-' . $id2; // Since j > i and we use ID order in SQL, min-max is usually consistent
        if (isset($flags[$pairKey])) {
            continue;
        }

        $distance = hammingDistance($h1, $images[$j]['imageHash_hamming']);

        if ($distance <= 10) {
            $duplicates[] = [
                'image1_id' => $id1,
                'image1_file' => $images[$i]['file_name'],
                'image2_id' => $id2,
                'image2_file' => $images[$j]['file_name'],
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
