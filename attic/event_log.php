<?php
/**
 * Functions for the Event Log in the database.
 */

require_once __DIR__ . '/../private/db.php';
require_once __DIR__ . '/../private/ip.php';

/**
 * Function to log security events.
 *
 * @param PDO|null $db Database connection; uses singleton if not provided.
 * @param string $event_type Type of event being logged.
 * @param string|null $username Optional username (can be null).
 * @param string|null $ip Optional IP address; defaults to client IP.
 * @param string $details Additional event details.
 */
function logEvent(?PDO $db, string $event_type, ?string $username, ?string $ip, string $details) {
    // Use singleton database connection if $db is not provided
    if (!$db) {
        $db = Database::getConnection();
    }

    // Fetch client IP if $ip is not provided
    if (!$ip) {
        $ip = getClientIP();
    }

    // Prepare and execute log entry
    $stmt = $db->prepare("
        INSERT INTO logs (event_type, username, ip_address, timestamp, details)
        VALUES (:event_type, :username, :ip, :time, :details)
    ");
    
    $stmt->execute([
        'event_type' => $event_type,
        'username' => $username ?? 'UNKNOWN', // Handle undefined username
        'ip' => $ip,
        'time' => time(),
        'details' => $details
    ]);
}
?>

