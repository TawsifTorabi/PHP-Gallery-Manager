<?php

// ===============================
// SESSION CONFIG (7 days)
// ===============================
ini_set('session.gc_maxlifetime', 604800);

$host = explode(':', $_SERVER['HTTP_HOST'])[0];

session_set_cookie_params([
    'lifetime' => 604800,
    'path' => '/',
    'domain' => $host,   // dynamic
    'httponly' => true,
    'samesite' => 'Lax'
]);

// session_start();

if (session_status() === PHP_SESSION_NONE) {

    session_start();
}

require_once __DIR__ . '/db.php';

// ===============================
// LOAD SESSION FROM DATABASE
// ===============================
function loadSessionFromDB($conn)
{
    $session_id = session_id();

    $stmt = $conn->prepare("SELECT payload, last_activity FROM app_sessions WHERE session_id = ?");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Expiry check (7 days)
        if ($row['last_activity'] < (time() - 604800)) {
            destroySession($conn);
            return false;
        }

        $data = json_decode($row['payload'], true);

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $_SESSION[$key] = $value;
            }
        }

        updateSessionActivity($conn);
        return true;
    }

    return false;
}

// ===============================
// UPDATE ACTIVITY
// ===============================
function updateSessionActivity($conn)
{
    $session_id = session_id();
    $time = time();

    $stmt = $conn->prepare("UPDATE app_sessions SET last_activity = ? WHERE session_id = ?");
    $stmt->bind_param("is", $time, $session_id);
    $stmt->execute();
}

// ===============================
// DESTROY SESSION (GLOBAL LOGOUT)
// ===============================
function destroySession($conn)
{
    $session_id = session_id();

    $stmt = $conn->prepare("DELETE FROM app_sessions WHERE session_id = ?");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();

    $_SESSION = [];
    session_destroy();
}

// ===============================
// AUTO LOAD SESSION
// ===============================
loadSessionFromDB($conn);

// ===============================
// PROTECT PAGE
// ===============================
if (!isset($_SESSION['user_id'])) {
    // header('Location: index.php');
    // exit();
}







// // server should keep session data for AT LEAST 7 days
// ini_set('session.gc_maxlifetime', 604800);

// // each client should remember their session id for EXACTLY 7 days
// session_set_cookie_params(604800);

// session_start();

// // Ensure the user is logged in
// if (!isset($_SESSION['user_id'])) {
//     header('Location: index.php');
// }
