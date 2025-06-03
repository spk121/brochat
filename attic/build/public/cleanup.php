<?php
// cleanup.php
require_once 'config.php';

try {
    $db = new PDO('sqlite:/var/www/data/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $current_time = time();
    $lockout_time = $current_time - LOCKOUT_DURATION;
    $log_retention = $current_time - (30 * 24 * 60 * 60); // 30 days
    $db->exec('DELETE FROM login_attempts WHERE attempt_time < ' . $lockout_time);
    $db->exec('DELETE FROM user_login_attempts WHERE attempt_time < ' . $lockout_time);
    $db->exec('DELETE FROM invitation_codes WHERE expiration_date < ' . $current_time);
    $db->exec('DELETE FROM logs WHERE timestamp < ' . $log_retention);

    // Log cleanup event
    $stmt = $db->prepare('
        INSERT INTO logs (event_type, username, ip_address, timestamp, details)
        VALUES (:event_type, :username, :ip, :time, :details)
    ');
    $stmt->execute([
        'event_type' => 'cleanup',
        'username' => null,
        'ip' => '127.0.0.1', // Assuming run via cron
        'time' => $current_time,
        'details' => 'Cleaned up old login attempts, expired invitation codes, and logs'
    ]);

    echo "Old login attempts, expired invitation codes, and logs cleaned up.";
} catch (PDOException $e) {
    error_log("Error in cleanup.php: " . $e->getMessage(), 3, '/var/log/php_errors.log');
    echo "Error: " . $e->getMessage();
}
?>
