<?php

// This script identifies files in the 'uploads' directory that do not have a corresponding 'file_name' entry in the 'images' database table and moves them to a 'lost' directory for review.
// Always ensure you have backups before running cleanup scripts on production data.
// Usage: Run this script manually via CLI or set up a cron job. It will not run automatically.
// Note: This script assumes that the 'file_name' field in the 'images' table contains only the filename (not the full path) and that all uploaded files are stored in the 'uploads' directory.

require 'db.php';

// 1. Setup Folders
$sourceDir = __DIR__ . '/uploads/';
$lostDir = __DIR__ . '/lost/';

if (!is_dir($lostDir)) {
    mkdir($lostDir, 0777, true);
    echo "[*] Created 'lost' directory.\n";
}

echo "[*] Scanning for orphaned files in: $sourceDir\n";

// 2. Fetch all valid filenames from the database into an array
$validFiles = [];
$query = "SELECT file_name FROM images WHERE file_name IS NOT NULL";
$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    $validFiles[] = $row['file_name'];
}

echo "[i] Database contains " . count($validFiles) . " active records.\n";

// 3. Iterate through physical files
$files = scandir($sourceDir);
$movedCount = 0;

foreach ($files as $file) {
    // Skip Linux hidden directories and the directory itself
    if ($file === '.' || $file === '..') continue;

    $filePath = $sourceDir . $file;

    // Only process files (skip subfolders)
    if (is_file($filePath)) {
        
        // Check if this filename exists in our database array
        if (!in_array($file, $validFiles)) {
            
            echo "[!] Found Orphan: $file -> Moving to /lost/\n";
            
            $destination = $lostDir . $file;
            
            // Move the file
            if (rename($filePath, $destination)) {
                $movedCount++;
            } else {
                echo "[-] Failed to move $file (Check permissions)\n";
            }
        }
    }
}

echo "------------------------------------------\n";
echo "[+] Cleanup Finished. Moved $movedCount files to the 'lost' folder.\n";
echo "------------------------------------------\n";






// Note: This Below script is meant to be run manually for testing purposes. It identifies files in the 'uploads' directory that do not have a corresponding 'file_name' entry in the 'images' database table and moves them to a 'lost' directory for review.
// Always ensure you have backups before running cleanup scripts on production data.

// require 'db.php';

// // 1. Setup Folders
// $sourceDir = __DIR__ . '/uploads/';
// $lostDir   = __DIR__ . '/lost/';

// if (!is_dir($lostDir)) {
//     mkdir($lostDir, 0777, true);
//     echo "[*] Created 'lost' directory.\n";
// }

// echo "[*] TEST MODE: Scanning for orphaned files (Limit: 5)...\n";

// // 2. Fetch all valid filenames from the database
// $validFiles = [];
// $query = "SELECT file_name FROM images WHERE file_name IS NOT NULL";
// $result = $conn->query($query);

// while ($row = $result->fetch_assoc()) {
//     $validFiles[] = $row['file_name'];
// }

// // 3. Iterate through physical files with a Limit
// $files = scandir($sourceDir);
// $movedCount = 0;
// $limit = 5;

// foreach ($files as $file) {
//     // Stop if we reached the test limit
//     if ($movedCount >= $limit) {
//         echo "[!] Test limit of $limit reached. Stopping.\n";
//         break;
//     }

//     // Skip Linux directories
//     if ($file === '.' || $file === '..') continue;

//     $filePath = $sourceDir . $file;

//     if (is_file($filePath)) {
//         // If file is NOT in the database, it is an orphan
//         if (!in_array($file, $validFiles)) {
            
//             echo "[!] Found Orphan: $file -> Moving to /lost/\n";
            
//             $destination = $lostDir . $file;
            
//             if (rename($filePath, $destination)) {
//                 $movedCount++;
//             } else {
//                 echo "[-] Failed to move $file. Check Docker permissions.\n";
//             }
//         }
//     }
// }

// echo "------------------------------------------\n";
// echo "[+] Test Finished. Moved $movedCount files.\n";
// echo "[i] Check your /lost/ folder to verify these were actually orphans.\n";
// echo "------------------------------------------\n";