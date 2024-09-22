<?php

// server should keep session data for AT LEAST 7 days
ini_set('session.gc_maxlifetime', 604800);

// each client should remember their session id for EXACTLY 7 days
session_set_cookie_params(604800);

session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
}
