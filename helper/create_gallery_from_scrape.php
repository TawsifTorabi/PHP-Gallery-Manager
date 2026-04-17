<?php
session_start();
require __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

set_time_limit(600);
ini_set('memory_limit', '512M');

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$title = $data['title'] ?? 'Scraped Gallery';
$media = $data['media'] ?? [];

/* --------------------------
   1. CREATE GALLERY FIRST
---------------------------*/
$stmt = $conn->prepare("
    INSERT INTO galleries (title, created_by, hero_images)
    VALUES (?, ?, ?)
");

$emptyHero = '';

$stmt->bind_param("sis", $title, $_SESSION['user_id'], $emptyHero);
$stmt->execute();

$gallery_id = $stmt->insert_id;

/* --------------------------
   2. PROCESS EACH SCRAPED FILE
---------------------------*/

$upload_dir = __DIR__ . "/../uploads/";
if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

$results = [];

foreach ($media as $item) {

    $url = $item['mediaElem'];
    $type = $item['mediatype'];

    try {

        // Skip non-media
        if ($type !== 'image' && $type !== 'video') continue;

        // Download file
        $tmp = tempnam(sys_get_temp_dir(), 'scrape_');

        file_put_contents($tmp, file_get_contents($url));

        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (!$ext) $ext = ($type === 'image') ? 'jpg' : 'mp4';

        $unique_base = uniqid() . '-' . time();
        $final_name = $unique_base . '.' . $ext;
        $final_path = $upload_dir . $final_name;

        rename($tmp, $final_path);

        $file_type = mime_content_type($final_path);
        $media_type = (strpos($file_type, 'image') !== false) ? 'image' : 'video';

        /* --------------------------
           IMAGE HASH
        ---------------------------*/
        $imagehash = '';
        $dimension = null;

        if ($media_type === 'image') {

            require_once __DIR__ . '/../imageHash.php';
            $imagehash = getImageHash($final_path);

            $size = @getimagesize($final_path);
            if ($size) {
                $dimension = $size[0] . "x" . $size[1];
            }

            $status = "ready";
        } else {

            $cmd = "ffprobe -v error -select_streams v:0 "
                . "-show_entries stream=width,height "
                . "-of csv=s=x:p=0 "
                . escapeshellarg($final_path);

            $dimension = trim(shell_exec($cmd));
            $status = "pending";
        }

        /* --------------------------
           INSERT INTO IMAGES TABLE
        ---------------------------*/
        $stmt = $conn->prepare("
            INSERT INTO images 
            (gallery_id, file_name, dimension, file_type, imageHash_hamming, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "isssss",
            $gallery_id,
            $final_name,
            $dimension,
            $media_type,
            $imagehash,
            $status
        );

        $stmt->execute();

        $results[] = [
            "url" => $url,
            "status" => "uploaded"
        ];
    } catch (Exception $e) {
        $results[] = [
            "url" => $url,
            "status" => "failed"
        ];
    }
}

/* --------------------------
   RESPONSE
---------------------------*/
echo json_encode([
    "status" => "complete",
    "gallery_id" => $gallery_id,
    "processed" => count($results),
    "results" => $results
]);
