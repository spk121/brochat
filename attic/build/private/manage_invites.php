<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/db.php';
require_once __DIR__ . '/../private/ip.php';
require_once __DIR__ . '/../private/csrf.php';

// Restrict access to ADMIN users
if ($_SESSION['role'] !== 'ADMIN') {
    $_SESSION['flash_message'] = ["error" => "Access denied. Admin privileges required."];
    header('Location: index.php');
    exit;
}

$db = Database::getConnection();
$current_time = time();

// Handle expiration of selected invitation code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['expire_code'], $_POST['csrf_token'])) {
    if (validateCsrfFromPost() !== CsrfValidationResult::VALID) {
        $_SESSION['flash_message'] = ["error" => "Invalid CSRF token."];
        header('Location: manage_invites.php');
        exit;
    }

    $code_to_expire = strtolower(trim($_POST['expire_code']));
    $stmt = $db->prepare("UPDATE invitation_codes SET expiration_date = :now WHERE LOWER(code) = :code");
    $stmt->execute(['now' => $current_time, 'code' => $code_to_expire]);

    logEvent($db, 'admin_invite_expired', $_SESSION['username'], getClientIP(), "Invitation code '{$code_to_expire}' manually expired");
    $_SESSION['flash_message'] = ["success" => "Invitation code '{$code_to_expire}' has been expired."];
    header('Location: manage_invites.php');
    exit;
}

// Handle creation of new invitation code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_expiration_days'], $_POST['new_max_uses'], $_POST['csrf_token'])) {
    if (validateCsrfFromPost() !== CsrfValidationResult::VALID) {
        $_SESSION['flash_message'] = ["error" => "Invalid CSRF token."];
        header('Location: manage_invites.php');
        exit;
    }

    $expiration_days = max(1, intval($_POST['new_expiration_days'])); // Ensure >= 1 day
    $max_uses = max(1, intval($_POST['new_max_uses'])); // Ensure >= 1 use
    $expiration_date = $current_time + ($expiration_days * 86400); // Convert days to seconds
    $new_code = generateRandomInviteCode(); // Generate random valid code

    // Insert new invitation code into database
    $stmt = $db->prepare("
        INSERT INTO invitation_codes (code, expiration_date, usage_count, max_uses)
        VALUES (:code, :expiration_date, 0, :max_uses)
    ");
    $stmt->execute(['code' => $new_code, 'expiration_date' => $expiration_date, 'max_uses' => $max_uses]);

    logEvent($db, 'admin_invite_created', $_SESSION['username'], getClientIP(), "Generated new invite code '{$new_code}', expires in {$expiration_days} days, max {$max_uses} uses.");
    $_SESSION['flash_message'] = ["success" => "New invitation code '{$new_code}' created."];
    header('Location: manage_invites.php');
    exit;
}

// Fetch active invitation codes
$stmt = $db->prepare("SELECT code, expiration_date, usage_count, max_uses FROM invitation_codes WHERE expiration_date > :now ORDER BY expiration_date ASC");
$stmt->execute(['now' => $current_time]);
$active_invites = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Generate a random invitation code matching the required pattern: 3 letters (a-z) + 3 digits.
 */
function generateRandomInviteCode(): string {
    $letters = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 3);
    $numbers = sprintf("%03d", random_int(0, 999)); // Ensures 3-digit format
    return $letters . $numbers;
}
?>
