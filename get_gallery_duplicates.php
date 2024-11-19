<?php
include 'session.php';
require 'db.php';
date_default_timezone_set('Asia/Dhaka');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to calculate Hamming Distance
function hammingDistance($hash1, $hash2) {
    $distance = 0;
    for ($i = 0; $i < strlen($hash1); $i++) {
        if ($hash1[$i] !== $hash2[$i]) {
            $distance++;
        }
    }
    return $distance;
}

// Get the gallery_id as input
$gallery_id = $_GET['gallery_id'] ?? 1; // Change as needed for dynamic input

// Fetch images from the database for the given gallery_id
$query = "SELECT id, file_name, imageHash_hamming FROM images WHERE gallery_id = ? AND imageHash_hamming != ''";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $gallery_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if there are any images for the given gallery_id
if ($result->num_rows === 0) {
    die(json_encode(["error" => "No images found for gallery_id $gallery_id."]));
}

// Store image data in an array for comparison
$images = [];
while ($row = $result->fetch_assoc()) {
    $images[] = $row;
}

$total = count($images);
$duplicates = []; // Store duplicate pairs
$checkedPairs = []; // Track checked pairs to avoid duplications

// Compare each image hash with all subsequent image hashes
for ($i = 0; $i < $total; $i++) {
    for ($j = $i + 1; $j < $total; $j++) {
        // Ensure pair is not already checked
        $pairKey = min($images[$i]['id'], $images[$j]['id']) . '-' . max($images[$i]['id'], $images[$j]['id']);
        if (isset($checkedPairs[$pairKey])) {
            continue; // Skip already processed pair
        }

        // Calculate Hamming Distance
        $distance = hammingDistance($images[$i]['imageHash_hamming'], $images[$j]['imageHash_hamming']);
        
        // If the Hamming Distance is below the threshold, mark as duplicate
        if ($distance <= 15) { // Threshold, adjust if needed

            // Check if the pair is flagged as non-duplicate
            $flagQuery = "SELECT id FROM image_duplicate_flag 
                          WHERE ((media_1 = ? AND media_2 = ?) OR (media_1 = ? AND media_2 = ?)) 
                          AND gallery_id = ? AND matched = 0";
            $flagStmt = $conn->prepare($flagQuery);
            $flagStmt->bind_param(
                "iiiii", 
                $images[$i]['id'], $images[$j]['id'], 
                $images[$j]['id'], $images[$i]['id'], 
                $gallery_id
            );
            $flagStmt->execute();
            $flagResult = $flagStmt->get_result();

            // Skip the flagged pair
            if ($flagResult->num_rows > 0) {
                continue;
            }

            // Add the duplicate pair
            $duplicates[] = [
                'image1_id' => $images[$i]['id'],
                'image1_file' => $images[$i]['file_name'],
                'image2_id' => $images[$j]['id'],
                'image2_file' => $images[$j]['file_name'],
                'distance' => $distance
            ];

            // Mark this pair as checked
            $checkedPairs[$pairKey] = true;
        }
    }
}

// Return the duplicates as a JSON response
header('Content-Type: application/json');
echo json_encode([
    'gallery_id' => $gallery_id,
    'total_images' => $total,
    'duplicates' => $duplicates
]);

// Close the connection
$conn->close();
?>
