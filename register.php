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

try {
    $db = new PDO('sqlite:/var/www/data/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check IP-based rate limit
    $ip = getClientIP();
    $current_time = time();
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
        logEvent($db, 'register_failure', null, $ip, 'Too many registration attempts from this IP');
        header('Location: register.php?error=Too many registration attempts from this IP. Please try again later.');
        exit;
    }

    // Generate CSRF token if not set
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        $_SESSION['csrf_token'] = generateCsrfToken();
        $_SESSION['csrf_token_time'] = $current_time;
    } elseif ($current_time - $_SESSION['csrf_token_time'] > CSRF_TOKEN_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['csrf_token'] = generateCsrfToken();
        $_SESSION['csrf_token_time'] = $current_time;
        header('Location: register.php?error=Session expired. Please try again.');
        exit;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        $csrf_token = $_POST['csrf_token'] ?? '';
        if ($csrf_token !== $_SESSION['csrf_token']) {
            // Log failed attempt
            $stmt = $db->prepare('
                INSERT INTO login_attempts (ip_address, attempt_time, attempt_count)
                VALUES (:ip, :time, 1)
            ');
            $stmt->execute(['ip' => $ip, 'time' => $current_time]);
            logEvent($db, 'register_failure', null, $ip, 'Invalid CSRF token');
            header('Location: register.php?error=Invalid CSRF token');
            exit;
        }

        $username = strtolower(trim($_POST['username'] ?? '')); // Normalize to lowercase
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $invite_code = strtolower(trim($_POST['invite_code'] ?? '')); // Normalize to lowercase

        // Validate inputs
        $errors = [];
        if (empty($username) || strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = 'Username must be 3-50 characters long';
        }
        if (empty($password) || strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        } elseif (!preg_match('/^(?=.*[^a-zA-Z])[a-zA-Z0-9@#$%^&*()_+\-=\[\]{};':"\\|,.<>/?]*$/', $password)) {
            $errors[] = 'Password must contain at least one number or symbol and only use letters, numbers, or symbols (no spaces)';
        }
        if ($password !== $password_confirm) {
            $errors[] = 'Passwords do not match';
        }
        if (!preg_match('/^[a-z]{3}[0-9]{3}$/', $invite_code)) {
            $errors[] = 'Invalid invitation code format';
        }

        // Check if username is taken
        $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Username is already taken';
        }

        // Check invitation code
        $stmt = $db->prepare('
            SELECT id, expiration_date, usage_count, max_uses
            FROM invitation_codes
            WHERE code = :code
        ');
        $stmt->execute(['code' => $invite_code]);
        $invite = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$invite) {
            $errors[] = 'Invalid invitation code';
        } elseif ($invite['expiration_date'] < $current_time) {
            $errors[] = 'Invitation code has expired';
        } elseif ($invite['usage_count'] >= $invite['max_uses']) {
            $errors[] = 'Invitation code has reached its usage limit';
        }

        if (!empty($errors)) {
            // Log failed attempt
            $stmt = $db->prepare('
                INSERT INTO login_attempts (ip_address, attempt_time, attempt_count)
                VALUES (:ip, :time, 1)
            ');
            $stmt->execute(['ip' => $ip, 'time' => $current_time]);
            logEvent($db, 'register_failure', $username, $ip, implode('; ', $errors));
            header('Location: register.php?error=' . urlencode(implode('; ', $errors)));
            exit;
        }

        // Create account
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('
            INSERT INTO users (username, password, role)
            VALUES (:username, :password, "USER")
        ');
        $stmt->execute(['username' => $username, 'password' => $password_hash]);

        // Increment invitation code usage
        $stmt = $db->prepare('
            UPDATE invitation_codes
            SET usage_count = usage_count + 1
            WHERE id = :id
        ');
        $stmt->execute(['id' => $invite['id']]);

        // Reset IP-based attempts on success
        $stmt = $db->prepare('
            DELETE FROM login_attempts
            WHERE ip_address = :ip AND attempt_time > :lockout_time
        ');
        $stmt->execute(['ip' => $ip, 'lockout_time' => $lockout_time]);

        // Regenerate CSRF token
        $_SESSION['csrf_token'] = generateCsrfToken();
        $_SESSION['csrf_token_time'] = $current_time;

        logEvent($db, 'register_success', $username, $ip, 'Account created with invite code ' . $invite_code);
        header('Location: index.html?success=Account created successfully. Please log in.');
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error in register.php: " . $e->getMessage(), 3, '/var/log/php_errors.log');
    header('Location: register.php?error=Database error');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>Create Account</h2>
        <p><a href="index.html">Back to Login</a></p>
        <form action="register.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <p class="help-text">Password must be at least 8 characters, include at least one number or symbol, and use only letters, numbers, or symbols (no spaces).</p>
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm Password:</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            <div class="form-group">
                <label for="invite_code">Invitation Code:</label>
                <input type="text" id="invite_code" name="invite_code" required>
                <p class="help-text">Enter a valid invitation code (e.g., abc123). Codes are limited-use and may expire.</p>
            </div>
            <button type="submit">Create Account</button>
        </form>
        <?php
        if (isset($_GET['error'])) {
            echo '<p class="error">' . htmlspecialchars($_GET['error']) . '</p>';
        }
        if (isset($_GET['success'])) {
            echo '<p class="success">' . htmlspecialchars($_GET['success']) . '</p>';
        }
        ?>
    </div>
</body>
</html>
