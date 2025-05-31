<?php
session_start();
require_once 'config.php';

// Check CSRF token expiration
$current_time = time();
if (!isset($_SESSION['csrf_token']) || 
    !isset($_SESSION['csrf_token_time']) || 
    $current_time - $_SESSION['csrf_token_time'] > CSRF_TOKEN_TIMEOUT) {
    session_unset();
    session_destroy();
    header('Location: index.html?error=Session expired. Please log in again.');
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    header('Location: index.html?error=Please log in first');
    exit;
}

try {
    $db = new PDO('sqlite:/var/www/data/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Log page access
    $stmt = $db->prepare('
        INSERT INTO logs (event_type, username, ip_address, timestamp, details)
        VALUES (:event_type, :username, :ip, :time, :details)
    ');
    $stmt->execute([
        'event_type' => 'welcome_access',
        'username' => $_SESSION['username'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'time' => $current_time,
        'details' => 'User accessed welcome page'
    ]);
} catch (PDOException $e) {
    error_log("Database error in welcome.php: " . $e->getMessage(), 3, '/var/log/php_errors.log');
    header('Location: index.html?error=Database error');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
        <p>Your role: <?php echo htmlspecialchars($_SESSION['role']); ?></p>
        <p><a href="logout.php">Log Out</a></p>
    </div>
</body>
</html>
