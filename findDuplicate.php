<?php
// Database connection
$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'image_gallery';

$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stopFile = "stop_duplicates.txt"; // File to monitor stopping signal

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

// Fetch all images with non-empty imageHash_hamming
$query = "SELECT id, file_name, imageHash_hamming, gallery_id FROM images WHERE imageHash_hamming != ''";
$result = $conn->query($query);

if ($result->num_rows === 0) {
    die("No records found with imageHash_hamming values.\n");
}

// Store image data in an array for comparison
$images = [];
while ($row = $result->fetch_assoc()) {
    $images[] = $row;
}

$total = count($images);
$duplicates = []; // Store duplicate pairs

echo "Starting duplicate detection among $total images...\n";

// Compare each image hash with all subsequent image hashes
for ($i = 0; $i < $total; $i++) {
    for ($j = $i + 1; $j < $total; $j++) {
        // Check stopping condition
        if (file_exists($stopFile)) {
            echo "Process stopped by user.\n";
            exit;
        }

        // Calculate Hamming Distance
        $distance = hammingDistance($images[$i]['imageHash_hamming'], $images[$j]['imageHash_hamming']);
        
        // If the Hamming Distance is below the threshold, mark as duplicate
        if ($distance <= 15) { // Threshold, adjust if needed
            $duplicates[] = [
                'image1_id' => $images[$i]['id'],
                'image1_file' => $images[$i]['file_name'],
                'image1_gallery' => $images[$i]['gallery_id'],
                'image2_id' => $images[$j]['id'],
                'image2_file' => $images[$j]['file_name'],
                'image2_gallery' => $images[$j]['gallery_id'],
                'distance' => $distance
            ];
            echo "Duplicate found: Gallery ID {$images[$i]['gallery_id']} - ID {$images[$i]['id']} ({$images[$i]['file_name']}) and Gallery ID {$images[$j]['gallery_id']} - ID {$images[$j]['id']} ({$images[$j]['file_name']}) (Distance: $distance)\n";
        }
    }
}

echo "\nDuplicate detection completed.\n";
echo "Total duplicates found: " . count($duplicates) . "\n";

// Optional: Save duplicates to a file
$duplicatesFile = "duplicates.json";
file_put_contents($duplicatesFile, json_encode($duplicates, JSON_PRETTY_PRINT));
echo "Duplicates saved to $duplicatesFile.\n";

$conn->close();
?>
