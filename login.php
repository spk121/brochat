<?php
session_start();
require_once 'config.php';

// Function to get client IP (basic implementation)
function getClientIP() {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// Function to log events
function logEvent($db, $event_type, $username, $ip, $details) {
    $stmt = $db->prepare('
        INSERT INTO logs (event_type, username, ip_address, timestamp, details)
        VALUES (:event_type, :username, :ip, :time, :details)
    ');
    $stmt->execute([
        'event_type' => $event_type,
        'username' => $username,
        'ip' => $ip,
        'time' => time(),
        'details' => $details
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    $current_time = time();
    $ip = getClientIP();

    try {
        // Connect to SQLite database
        $db = new PDO('sqlite:/var/www/data/database.sqlite');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if (!isset($_SESSION['csrf_token']) || 
            !isset($_SESSION['csrf_token_time']) || 
            $csrf_token !== $_SESSION['csrf_token'] || 
            $current_time - $_SESSION['csrf_token_time'] > CSRF_TOKEN_TIMEOUT) {
            logEvent($db, 'login_failure', null, $ip, 'Invalid or expired CSRF token');
            session_unset();
            session_destroy();
            header('Location: index.html?error=Invalid or expired CSRF token');
            exit;
        }

        // Check IP-based rate limit
        $lockout_time = $current_time - LOCKOUT_DURATION;

        $stmt = $db->prepare('
            SELECT SUM(attempt_count) as total_attempts
            FROM login_attempts
            WHERE ip_address = :ip AND attempt_time > :lockout_time
        ');
        $stmt->execute(['ip' => $ip, 'lockout_time' => $lockout_time]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $ip_attempts = (int)($result['total_attempts'] ?? 0);

        if ($ip_attempts >= RATE_LIMIT_ATTEMPTS) {
            logEvent($db, 'login_failure', null, $ip, 'Too many login attempts from this IP');
            header('Location: index.html?error=Too many login attempts from this IP. Please try again later.');
            exit;
        }

        // Validate input
        $username = strtolower(trim($_POST['username'] ?? '')); // Normalize to lowercase
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            // Log IP-based failed attempt
            $stmt = $db->prepare('
                INSERT INTO login_attempts (ip_address, attempt_time, attempt_count)
                VALUES (:ip, :time, 1)
            ');
            $stmt->execute(['ip' => $ip, 'time' => $current_time]);
            logEvent($db, 'login_failure', $username, $ip, 'Empty username or password');
            header('Location: index.html?error=Username and password are required');
            exit;
        }

        // Get user ID, password, and role
        $stmt = $db->prepare('SELECT id, password, role FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check username-based rate limit if user exists
        if ($user) {
            $user_id = $user['id'];
            $stmt = $db->prepare('
                SELECT SUM(attempt_count) as total_attempts
                FROM user_login_attempts
                WHERE user_id = :user_id AND attempt_time > :lockout_time
            ');
            $stmt->execute(['user_id' => $user_id, 'lockout_time' => $lockout_time]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $user_attempts = (int)($result['total_attempts'] ?? 0);

            if ($user_attempts >= RATE_LIMIT_ATTEMPTS) {
                // Log IP-based attempt even if user is locked out
                $stmt = $db->prepare('
                    INSERT INTO login_attempts (ip_address, attempt_time, attempt_count)
                    VALUES (:ip, :time, 1)
                ');
                $stmt->execute(['ip' => $ip, 'time' => $current_time]);
                logEvent($db, 'login_failure', $username, $ip, 'Too many login attempts for this account');
                header('Location: index.html?error=Too many login attempts for this account. Please try again later.');
                exit;
            }
        }

        // Verify user and password
        if ($user && password_verify($password, $user['password'])) {
            // Reset both IP and user login attempts on successful login
            $stmt = $db->prepare('
                DELETE FROM login_attempts
                WHERE ip_address = :ip AND attempt_time > :lockout_time
            ');
            $stmt->execute(['ip' => $ip, 'lockout_time' => $lockout_time]);

            if ($user) {
                $stmt = $db->prepare('
                    DELETE FROM user_login_attempts
                    WHERE user_id = :user_id AND attempt_time > :lockout_time
                ');
                $stmt->execute(['user_id' => $user['id'], 'lockout_time' => $lockout_time]);
            }

            // Regenerate CSRF token after successful login
            $_SESSION['csrf_token'] = generateCsrfToken();
            $_SESSION['csrf_token_time'] = $current_time;

            // Store username and role in session
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $user['role'];

            logEvent($db, 'login_success', $username, $ip, 'User logged in');
            header('Location: welcome.php');
            exit;
        } else {
            // Log IP-based failed attempt
            $stmt = $db->prepare('
                INSERT INTO login_attempts (ip_address, attempt_time, attempt_count)
                VALUES (:ip, :time, 1)
            ');
            $stmt->execute(['ip' => $ip, 'time' => $current_time]);

            // Log user-based failed attempt if user exists
            if ($user) {
                $stmt = $db->prepare('
                    INSERT INTO user_login_attempts (user_id, attempt_time, attempt_count)
                    VALUES (:user_id, :time, 1)
                ');
                $stmt->execute(['user_id' => $user['id'], 'time' => $current_time]);
            }

            logEvent($db, 'login_failure', $username, $ip, 'Invalid credentials');
            header('Location: index.html?error=Invalid username or password');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error in login.php: " . $e->getMessage(), 3, '/var/log/php_errors.log');
        header('Location: index.html?error=Database error');
        exit;
    }
} else {
    header('Location: index.html');
    exit;
}
?>
