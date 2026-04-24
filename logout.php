<?php

include 'session.php'; // must include db + session_start()

// Get current session ID
$session_id = session_id();

// Remove from DB (shared session store)
if (isset($conn)) {
    $stmt = $conn->prepare("DELETE FROM app_sessions WHERE session_id = ?");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
}

// Clear PHP session
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// Redirect
header("Location: login.php");
exit();
?>





// include 'session.php';
// session_unset();
// session_destroy();

// header("Location: login.php");
// exit();
?>
