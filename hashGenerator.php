<?php

//This is a image hash generator script to generate hash and store them to database table
//This hash required to use in a simple way to identify duplicate images existing in the database.

$host = 'localhost'; // Database host
$dbname = 'image_gallery'; // Database name
$user = 'root'; // Database username
$pass = ''; // Database password

try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Query to get records with empty `imageHash_hamming`
    $query = "SELECT id, file_name, file_type FROM images WHERE imageHash_hamming = ''";
    $stmt = $pdo->query($query);


    $totalRecords = $stmt->rowCount();
    $processedRecords = 0;

    echo "Found $totalRecords records to process.\n";

    foreach ($stmt as $row) {
        $id = $row['id'];
        $fileName = $row['file_name'];
        $fileType = $row['file_type'];

        // Skip non-image files
        if ($fileType !== 'image') {
            continue;
        }

        $filePath = "uploads/$fileName";

        // Check if file exists
        if (!file_exists($filePath)) {
            echo "File not found: $filePath\n";
            continue;
        }

        // Generate the hash for the image
        $imageHash = getImageHash($filePath);

        // Update the database
        $updateQuery = "UPDATE images SET imageHash_hamming = :imageHash WHERE id = :id";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute([':imageHash' => $imageHash, ':id' => $id]);

        $processedRecords++;
        echo "Processed $processedRecords / $totalRecords (ID: $id)\n";

        // Provide an option to stop processing
        if (file_exists('stop.txt')) {
            echo "Stop signal detected. Exiting...\n";
            break;
        }
    }

    echo "Hashing completed. Processed $processedRecords records.\n";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}

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
