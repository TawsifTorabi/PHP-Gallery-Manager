<?php

// ===============================
// CORS
// ===============================
header("Access-Control-Allow-Origin: http://192.168.0.243:8081");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

require_once 'db.php';

$action = $_GET['action'] ?? null;

// ===============================
// HELPERS
// ===============================
function getBearerToken()
{
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
        return $matches[1];
    }

    return null;
}

// ===============================
// CLEAN TOKEN INSERT (SAFE)
// ===============================
function issueToken($conn, $user_id, $ttlSeconds = 604800)
{
    // enforce single active token per user
    $stmt = $conn->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $token = bin2hex(random_bytes(32));
    $expires = time() + $ttlSeconds;
    $created = time();

    $stmt = $conn->prepare("
        INSERT INTO auth_tokens (user_id, token, expires_at, created_at)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param("isii", $user_id, $token, $expires, $created);
    $stmt->execute();

    return [
        "token" => $token,
        "expires_at" => $expires
    ];
}

// ===============================
// LOGIN
// ===============================
if ($action === 'login') {

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        echo json_encode(["success" => false, "message" => "Missing credentials"]);
        exit();
    }

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(["success" => false, "message" => "Invalid credentials"]);
        exit();
    }

    $tokenData = issueToken($conn, $user['id']);

    echo json_encode([
        "success" => true,
        "token" => $tokenData['token'],
        "expires_at" => $tokenData['expires_at'],
        "user_id" => $user['id']
    ]);
    exit();
}

// ===============================
// SESSION LOGIN (SSO BRIDGE)
// ===============================
if ($action === 'session_login') {

    session_start();

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["logged_in" => false]);
        exit();
    }

    $user_id = $_SESSION['user_id'];

    $tokenData = issueToken($conn, $user_id, 360); // short-lived SSO token for 6 Minutes

    echo json_encode([
        "logged_in" => true,
        "token" => $tokenData['token'],
        "user" => ["id" => $user_id],
        "redirect_url" =>
            "http://192.168.0.243:8081/sso-callback.php?token=" . $tokenData['token']
    ]);

    exit();
}

// ===============================
// EXCHANGE TOKEN (SSO → REAL TOKEN)
// ===============================
if ($action === 'exchange_token') {

    $ssoToken = getBearerToken();

    if (!$ssoToken) {
        echo json_encode(["success" => false]);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT user_id, expires_at
        FROM auth_tokens
        WHERE token = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $ssoToken);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if (!$res || $res['expires_at'] < time()) {
        echo json_encode(["success" => false, "message" => "Invalid or expired SSO token"]);
        exit();
    }

    $tokenData = issueToken($conn, $res['user_id']);

    echo json_encode([
        "success" => true,
        "token" => $tokenData['token']
    ]);

    exit();
}

// ===============================
// REFRESH TOKEN
// ===============================
if ($action === 'refresh_token') {

    $oldToken = getBearerToken();

    if (!$oldToken) {
        echo json_encode(["success" => false]);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT user_id, expires_at
        FROM auth_tokens
        WHERE token = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $oldToken);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        echo json_encode(["success" => false]);
        exit();
    }

    // allow refresh only if not fully expired
    if ($user['expires_at'] < time() - 60) {
        echo json_encode(["success" => false, "message" => "Expired"]);
        exit();
    }

    $tokenData = issueToken($conn, $user['user_id']);

    echo json_encode([
        "success" => true,
        "token" => $tokenData['token'],
        "expires_at" => $tokenData['expires_at']
    ]);

    exit();
}

// ===============================
// CHECK LOGIN
// ===============================
if ($action === 'check_login') {

    $token = getBearerToken();

    if (!$token) {
        echo json_encode(["logged_in" => false]);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT user_id, expires_at
        FROM auth_tokens
        WHERE token = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data || $data['expires_at'] < time()) {
        echo json_encode(["logged_in" => false]);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT id, username, email
        FROM users
        WHERE id = ?
    ");
    $stmt->bind_param("i", $data['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        "logged_in" => true,
        "user" => $user
    ]);

    exit();
}

// ===============================
// VERIFY TOKEN
// ===============================
if ($action === 'verify_token') {

    $token = getBearerToken();

    if (!$token) {
        echo json_encode(["valid" => false]);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT user_id, expires_at
        FROM auth_tokens
        WHERE token = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data || $data['expires_at'] < time()) {
        echo json_encode(["valid" => false]);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT id, username, email
        FROM users
        WHERE id = ?
    ");
    $stmt->bind_param("i", $data['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        "valid" => true,
        "user" => $user
    ]);

    exit();
}

// ===============================
// LOGOUT
// ===============================
if ($action === 'logout') {

    $token = getBearerToken();

    if ($token) {
        $stmt = $conn->prepare("DELETE FROM auth_tokens WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
    }

    echo json_encode(["success" => true]);
    exit();
}

// ===============================
// DEFAULT
// ===============================
echo json_encode([
    "success" => false,
    "message" => "Invalid action"
]);