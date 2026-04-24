<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

/**
 * Load session data from DB and sync with PHP session
 */
function loadSessionFromDB($conn) {
    $session_id = session_id();

    $stmt = $conn->prepare("SELECT payload, last_activity FROM app_sessions WHERE session_id = ?");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Optional: session expiry (1 day)
        if ($row['last_activity'] < (time() - 86400)) {
            destroySession($conn);
            return false;
        }

        $data = json_decode($row['payload'], true);

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $_SESSION[$key] = $value;
            }
        }

        // Update last activity
        updateSessionActivity($conn);

        return true;
    }

    return false;
}

/**
 * Update last activity timestamp
 */
function updateSessionActivity($conn) {
    $session_id = session_id();
    $time = time();

    $stmt = $conn->prepare("UPDATE app_sessions SET last_activity = ? WHERE session_id = ?");
    $stmt->bind_param("is", $time, $session_id);
    $stmt->execute();
}

/**
 * Destroy session (logout everywhere)
 */
function destroySession($conn) {
    $session_id = session_id();

    $stmt = $conn->prepare("DELETE FROM app_sessions WHERE session_id = ?");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();

    $_SESSION = [];
    session_destroy();
}

/**
 * Require login (protect pages)
 */
function requireLogin($conn) {
    if (!isset($_SESSION['user_id'])) {
        loadSessionFromDB($conn);
    }

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Optional: Get current user
 */
function currentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null
    ];
}

/**
 * Optional: Logout helper
 */
function logout($conn) {
    destroySession($conn);
    header("Location: login.php");
    exit();
}


/* ===========================
   AUTO LOAD SESSION ON INCLUDE
   =========================== */

loadSessionFromDB($conn);