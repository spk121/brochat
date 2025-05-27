<?php
// config.php
define('RATE_LIMIT_ATTEMPTS', 6); // Maximum allowed login attempts
define('LOCKOUT_DURATION', 15 * 60); // Lockout duration in seconds (15 minutes)
define('CSRF_TOKEN_TIMEOUT', 604800); // CSRF token timeout in seconds (1 week)
define('INVITE_CODE_EXPIRATION', 604800); // Invite code expiration in seconds (1 week)
define('INVITE_CODE_MAX_USES', 5); // Default max uses for invitation codes

function generateCsrfToken() {
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $token = '';
    for ($i = 0; $i < 4; $i++) {
        $token .= $letters[random_int(0, 25)];
    }
    return $token;
}
?>
