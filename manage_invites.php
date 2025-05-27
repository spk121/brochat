<?php
session_start();
require_once 'config.php';

// Function to get client IP
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

// Check CSRF token expiration and login status
$current_time = time();
if (!isset($_SESSION['csrf_token']) || 
    !isset($_SESSION['csrf_token_time']) || 
    $current_time - $_SESSION['csrf_token_time'] > CSRF_TOKEN_TIMEOUT) {
    session_unset();
    session_destroy();
    header('Location: index.html?error=Session expired. Please log in again.');
    exit;
}

// Check if user is logged in and has ADMIN role
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    header('Location: index.html?error=Unauthorized access');
    exit;
}

// Function to generate a 3-letter + 3-digit invitation code
function generateInviteCode() {
    $letters = 'abcdefghijklmnopqrstuvwxyz';
    $digits = '0123456789';
    $code = '';
    for ($i = 0; $i < 3; $i++) {
        $code .= $letters[random_int(0, 25)];
    }
    for ($i = 0; $i < 3; $i++) {
        $code .= $digits[random_int(0, 9)];
    }
    return $code;
}

try {
    $db = new PDO('sqlite:/var/www/data/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle form submission to generate new invite code
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        $csrf_token = $_POST['csrf_token'] ?? '';
        if ($csrf_token !== $_SESSION['csrf_token']) {
            logEvent($db, 'invite_code_failure', $_SESSION['username'], getClientIP(), 'Invalid CSRF token');
            header('Location: manage_invites.php?error=Invalid CSRF token');
            exit;
        }

        // Generate unique invite code
        $max_attempts = 10; // Prevent infinite loops
        $code = null;
        for ($i = 0; $i < $max_attempts; $i++) {
            $code = generateInviteCode();
            $stmt = $db->prepare('SELECT COUNT(*) FROM invitation_codes WHERE code = :code');
            $stmt->execute(['code' => strtolower($code)]); // Normalize to lowercase
            if ($stmt->fetchColumn() == 0) {
                break; // Code is unique
            }
            $code = null;
        }

        if ($code) {
            $expiration = $current_time + INVITE_CODE_EXPIRATION;
            $stmt = $db->prepare('
                INSERT INTO invitation_codes (code, expiration_date, usage_count, max_uses)
                VALUES (:code, :expiration, 0, :max_uses)
            ');
            $stmt->execute(['code' => strtolower($code), 'expiration' => $expiration, 'max_uses' => INVITE_CODE_MAX_USES]);
            logEvent($db, 'invite_code_generated', $_SESSION['username'], getClientIP(), 'Generated code: ' . $code);
            header('Location: manage_invites.php?success=Invitation code generated');
            exit;
        } else {
            logEvent($db, 'invite_code_failure', $_SESSION['username'], getClientIP(), 'Failed to generate unique code');
            header('Location: manage_invites.php?error=Failed to generate unique code');
            exit;
        }
    }

    // Fetch all invitation codes
    $stmt = $db->query('SELECT code, expiration_date, usage_count, max_uses FROM invitation_codes ORDER BY expiration_date DESC');
    $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Regenerate CSRF token for next form submission
    $_SESSION['csrf_token'] = generateCsrfToken();
    $_SESSION['csrf_token_time'] = $current_time;
} catch (PDOException $e) {
    error_log("Database error in manage_invites.php: " . $e->getMessage(), 3, '/var/log/php_errors.log');
    header('Location: manage_invites.php?error=Database error');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Invitation Codes</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>Manage Invitation Codes</h2>
        <p><a href="welcome.php">Back to Welcome</a> | <a href="logout.php">Log Out</a></p>
        
        <!-- Form to generate new invite code -->
        <form action="manage_invites.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <button type="submit">Generate New Invitation Code</button>
        </form>

        <!-- Display existing codes -->
        <?php if (empty($codes)): ?>
            <p>No invitation codes available.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Expiration Date</th>
                        <th>Usage Count</th>
                        <th>Max Uses</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($codes as $code): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($code['code']); ?></td>
                            <td><?php echo date('Y-m-d H:i:s', $code['expiration_date']); ?></td>
                            <td><?php echo htmlspecialchars($code['usage_count']); ?></td>
                            <td><?php echo htmlspecialchars($code['max_uses']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Display success or error messages -->
        <?php
        if (isset($_GET['success'])) {
            echo '<p class="success">' . htmlspecialchars($_GET['success']) . '</p>';
        }
        if (isset($_GET['error'])) {
            echo '<p class="error">' . htmlspecialchars($_GET['error']) . '</p>';
        }
        ?>
    </div>
</body>
</html>
