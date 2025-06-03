<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../private/db.php';
require_once __DIR__ . '/../private/ip.php';
require_once __DIR__ . '/../private/rate_limit.php';

// Validate HTTP request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message'] = ["error" => "Invalid request method."];
    header('Location: index.php');
    exit;
}

// Retrieve the singleton database connection
$db = Database::getConnection();
$ip = getClientIP();

// CSRF Validation
if (validateCsrfFromPost() !== CsrfValidationResult::VALID) {
    logEvent($db, 'login_failure', null, $ip, 'Invalid or expired CSRF token');
    session_unset();
    session_destroy();
    $_SESSION['flash_message'] = ["error" => "Invalid or expired login attempt."];
    header('Location: index.php');
    exit;
}

// Rate Limit Checks
$check_result = checkIpRateLimit($db, $ip);
if ($check_result === RateLimitResult::BANNED) {
    logEvent($db, 'login_failure', null, $ip, 'IP is banned');
    $_SESSION['flash_message'] = ["error" => "Your IP is temporarily banned due to too many failed login attempts."];
    header('Location: index.php');
    exit;
}
if ($check_result === RateLimitResult::EXCEEDED) {
    logEvent($db, 'login_failure', null, $ip, 'Too many login attempts from this IP');
    $_SESSION['flash_message'] = ["error" => "Too many login attempts. Try again later."];
    header('Location: index.php');
    exit;
}

// Validate username/password input
$username = strtolower(trim($_POST['username'] ?? ''));
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    logEvent($db, 'login_failure', $username, $ip, 'Empty login fields');
    addIpRateLimitEvent($db, $ip);
    $_SESSION['flash_message'] = ["error" => "Invalid credentials."];
    header('Location: index.php');
    exit;
}

// Check Username Rate Limit
if (checkUsernameRateLimit($db, $username) === RateLimitResult::EXCEEDED) {
    logEvent($db, 'login_failure', $username, $ip, 'Too many login attempts for this account');
    $_SESSION['flash_message'] = ["error" => "Account locked due to repeated login failures."];
    header('Location: index.php');
    exit;
}

// Fetch user credentials
$stmt = $db->prepare("SELECT id, password, role FROM users WHERE username = :username");
$stmt->execute(['username' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verify password and log in
if ($user && password_verify($password, $user['password'])) {
    session_regenerate_id(true); // Prevent session fixation

    $_SESSION['csrf_token'] = generateCsrfToken();
    $_SESSION['csrf_token_time'] = time();
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $user['role'];

    logEvent($db, 'login_success', $username, $ip, 'User authenticated');
    $_SESSION['flash_message'] = ["success" => "Login successful! Welcome back."];
    header('Location: welcome.php');
    exit;
} else {
    addIpRateLimitEvent($db, $ip);
    addUsernameRateLimitEvent($db, $username);
    logEvent($db, 'login_failure', $username, $ip, 'Invalid password');
    $_SESSION['flash_message'] = ["error" => "Invalid username or password."];
    header('Location: index.php');
    exit;
}
?>
