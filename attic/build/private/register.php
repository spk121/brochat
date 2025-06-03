<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../private/db.php';
require_once __DIR__ . '/../private/ip.php';
require_once __DIR__ . '/../private/rate_limit.php';
require_once __DIR__ . '/../private/csrf.php';

// Validate HTTP request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message'] = ["error" => "Invalid request method."];
    header('Location: register.php');
    exit;
}

// Retrieve database connection
$db = Database::getConnection();
$ip = getClientIP();

// CSRF Validation
if (validateCsrfFromPost() !== CsrfValidationResult::VALID) {
    logEvent($db, 'registration_failure', null, $ip, 'Invalid or expired CSRF token');
    session_unset();
    session_destroy();
    $_SESSION['flash_message'] = ["error" => "Invalid or expired registration attempt."];
    header('Location: register.php');
    exit;
}

// Check if IP is banned
if (isIpBanned($db, $ip)) {
    logEvent($db, 'registration_failure', null, $ip, 'Banned IP attempted registration');
    $_SESSION['flash_message'] = ["error" => "You are banned from creating an account."];
    header('Location: register.php');
    exit;
}

// Validate input data
$username = strtolower(trim($_POST['username'] ?? ''));
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$invitation_code = strtolower(trim($_POST['invitation_code'] ?? ''));

// Maintain a list of **restricted usernames**
$restricted_substrings = ['admin', 'root', 'sysadmin', 'moderator', 'support', 'webmaster', 'staff', 'helpdesk'];

// Block usernames containing **restricted substrings**
foreach ($restricted_substrings as $substring) {
    if (stripos($username, $substring) !== false) { // Case-insensitive check
        logEvent($db, 'registration_failure', $username, $ip, "Attempted to register misleading username '{$username}'");

        // Apply temporary **IP ban** (e.g., 1-hour ban)
        $ban_duration = 3600; // 1 hour
        $stmt = $db->prepare("
            INSERT INTO banned_ips (ip_address, ban_start, ban_duration)
            VALUES (:ip, :ban_start, :ban_duration)
            ON CONFLICT(ip_address) 
            DO UPDATE SET ban_start = :ban_start, ban_duration = LEAST(ban_duration * 2, 86400) -- Max ban = 24 hours
        ");
        $stmt->execute(['ip' => $ip, 'ban_start' => time(), 'ban_duration' => $ban_duration]);

        $_SESSION['flash_message'] = ["error" => "This username is restricted. Your IP has been temporarily banned."];
        header('Location: register.php');
        exit;
    }
}

// Username validation (ASCII letters, digits, '-', '_', 3 to 50 chars)
if (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username)) {
    logEvent($db, 'registration_failure', $username, $ip, 'Invalid username format');
    $_SESSION['flash_message'] = ["error" => "Invalid username format. Allowed: a-z, 0-9, '-', '_'. Length: 3-50 chars."];
    header('Location: register.php');
    exit;
}

// Password validation (ASCII only, must be at least 8 chars with a mix of letters and non-letters)
if (!preg_match('/^(?=.*[a-zA-Z])(?=.*[^a-zA-Z])[!-~]{8,}$/', $password)) {
    logEvent($db, 'registration_failure', $username, $ip, 'Password failed complexity requirements');
    $_SESSION['flash_message'] = ["error" => "Password must be at least 8 characters, contain at least one letter and one non-letter."];
    header('Location: register.php');
    exit;
}

if ($password !== $password_confirm) {
    logEvent($db, 'registration_failure', $username, $ip, 'Passwords did not match');
    $_SESSION['flash_message'] = ["error" => "Passwords do not match."];
    header('Location: register.php');
    exit;
}

// Validate invitation code (check expiration and usage limits)
$stmt = $db->prepare("
    SELECT id, usage_count, max_uses, expiration_date 
    FROM invitation_codes 
    WHERE LOWER(code) = :code
");
$stmt->execute(['code' => $invitation_code]);
$invite = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invite || time() > $invite['expiration_date']) {
    logEvent($db, 'registration_failure', $username, $ip, "Invitation code '{$invitation_code}' is invalid or expired");
    $_SESSION['flash_message'] = ["error" => "Invalid or expired invitation code."];
    header('Location: register.php');
    exit;
}

if ($invite['usage_count'] >= $invite['max_uses']) {
    logEvent($db, 'registration_failure', $username, $ip, "Invitation code '{$invitation_code}' has exceeded max uses");
    $_SESSION['flash_message'] = ["error" => "This invitation code has been used too many times."];
    header('Location: register.php');
    exit;
}

// Check if username is already taken
$stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
$stmt->execute(['username' => $username]);
if ($stmt->fetch()) {
    logEvent($db, 'registration_failure', $username, $ip, "Username '{$username}' is already in use");
    $_SESSION['flash_message'] = ["error" => "Username is already taken."];
    header('Location: register.php');
    exit;
}

// Hash password & create user account
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, 'USER')");
$stmt->execute(['username' => $username, 'password' => $hashed_password]);

// Increment invitation code usage
$stmt = $db->prepare("UPDATE invitation_codes SET usage_count = usage_count + 1 WHERE id = :id");
$stmt->execute(['id' => $invite['id']]);

// Set session variables & redirect to welcome page
session_regenerate_id(true);
$_SESSION['csrf_token'] = generateCsrfToken();
$_SESSION['csrf_token_time'] = time();
$_SESSION['username'] = $username;
$_SESSION['role'] = 'USER';

logEvent($db, 'registration_success', $username, $ip, 'User account created');
$_SESSION['flash_message'] = ["success" => "Account created successfully!"];
header('Location: welcome.php');
exit;
?>
