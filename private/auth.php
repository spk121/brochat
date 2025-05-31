<?php
// auth.php
session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/csrf.php'; // Ensure CSRF functions are available

// Secure session settings
// These are actually set in the php.ini file, but, we can set them here for reference.
// ini_set('session.cookie_httponly', 1);
// ini_set('session.cookie_secure', 1); // Enable only if using HTTPS

$current_time = time();

// Handle CSRF token expiration (without destroying the entire session)
if (isset($_SESSION['csrf_token_time']) && ($current_time - $_SESSION['csrf_token_time'] > CSRF_TOKEN_TIMEOUT)) {
    $_SESSION['csrf_token'] = generateCsrfToken();
    $_SESSION['csrf_token_time'] = $current_time;
}

// Handle session inactivity timeout
if (isset($_SESSION['last_activity']) && ($current_time - $_SESSION['last_activity'] > SESSION_INACTIVITY_TIMEOUT)) {
    session_unset(); // Clear session variables
    session_destroy(); // Destroy the session
    session_start(); // Start a new session
}

// Update session activity timestamp
$_SESSION['last_activity'] = $current_time;

// Ensure CSRF token is set
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? generateCsrfToken();
$_SESSION['csrf_token_time'] = $_SESSION['csrf_token_time'] ?? $current_time;
?>


