<?php
require_once __DIR__ . '/../src/ip.php';        # for getClientIP()
require_once __DIR__ . '/../src/constants.php'; # LOCKOUT_DURATION and RATE_LIMIT_ATTEMPTS
require_once __DIR__ . '/../src/db.php';        # $db

DEFINE('ONE_DAY_IN_SECONDS', 86400); // 24 hours in seconds
DEFINE('TEN_MINUTES_IN_SECONDS', 600);

// Declare an enum for Rate Limit check results
enum RateLimitResult {
    case OK;
    case EXCEEDED;
    case BANNED;
}

// Returns true if the IP is currently banned, otherwise false.
function isIpBanned(?PDO $db, $ip): bool {
    $db = $db ?? Database::getConnection();
    $current_time = time();

    $stmt = $db->prepare("
        SELECT ban_start, ban_duration 
        FROM banned_ips 
        WHERE ip_address = :ip
    ");
    $stmt->execute(['ip' => $ip]);
    $ban = $stmt->fetch(PDO::FETCH_ASSOC);

    return $ban && ($current_time < ($ban['ban_start'] + $ban['ban_duration']));
}

// Check IP-based rate limit
function checkIpRateLimit(?PDO $db, $ip): RateLimitResult {
    $db = $db ?? Database::getConnection();
    $ip = $ip ? strtolower(trim($ip)) : getClientIP();
    if (empty($ip)) {
        return RateLimitResult::OK; // No IP provided, no rate limit
    }

    if (isIpBanned($db, $ip)) {
        return RateLimitResult::BANNED; // IP is currently in a ban state.
    }

    $current_time = time();
    $lockout_time = $current_time - LOCKOUT_DURATION;

    $stmt = $db->prepare('
        SELECT SUM(attempt_count) as total_attempts
        FROM login_attempts
        WHERE ip_address = :ip AND attempt_time > :lockout_time
    ');
    $stmt->execute(['ip' => $ip, 'lockout_time' => $lockout_time]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result == false) {
        return RateLimitResult::OK;
    }
    $ip_attempts = (int)($result['total_attempts'] ?? 0);

    if ($ip_attempts >= RATE_LIMIT_ATTEMPTS) {
        return RateLimitResult::EXCEEDED;
    }
    return RateLimitResult::OK;
}

// Increment IP-based rate limit
function addIpRateLimitAndBanEvents(?PDO $db, $ip) {
    $db = $db ?? Database::getConnection();
    $ip = $ip ? strtolower(trim($ip)) : getClientIP();

    if (empty($ip) || isIpBanned($db, $ip)) {
        return;
    }

    $current_time = time();

    $stmt = $db->prepare("
        INSERT INTO login_attempts (ip_address, attempt_time, attempt_count)
        VALUES (:ip, :attempt_time, 1)
        ON CONFLICT(ip_address, attempt_time) 
        DO UPDATE SET attempt_count = attempt_count + 1
    ");
    $stmt->execute(['ip' => $ip, 'attempt_time' => $current_time]);

    // Check if ban should be applied
    if (checkIpRateLimit($db, $ip) === RateLimitResult::EXCEEDED) {
        $ban_duration = TEN_MINUTES_IN_SECONDS; // 10-minute ban (adjust as needed)
        $max_ban_duration = ONE_DAY_IN_SECONDS; // Maximum ban duration of 1 day
        // We insert or update the banned IP in the database.
        // The ban duration is double each time the IP is banned, up to a maximum
        // of 1 day.
        $stmt = $db->prepare("
            INSERT INTO banned_ips (ip_address, ban_start, ban_duration)
            VALUES (:ip, :ban_start, :ban_duration)
            ON CONFLICT(ip_address) 
            DO UPDATE SET
            ban_start = :ban_start,
            ban_duration = LEAST(ban_duration * 2, :max_ban_duration)
        ");
        $stmt->execute(['ip' => $ip, 'ban_start' => $current_time, 'ban_duration' => $ban_duration]);
    }    
}

function checkUsernameRateLimit(?PDO $db, $username) {
    $db = $db ?? Database::getConnection();
    $username = $username ? strtolower(trim($username)) : '';
    if (empty($username)) {
        return RateLimitResult::OK; // No username provided, no rate limit
    }
    $current_time = time();
    $lockout_time = $current_time - LOCKOUT_DURATION;

    $stmt = $db->prepare("
        SELECT SUM(attempt_count) AS total_attempts
        FROM user_login_attempts
        WHERE username = :username AND attempt_time > :lockout_time
    ");
    $stmt->execute(['username' => $username, 'lockout_time' => $lockout_time]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result == false) {
        return RateLimitResult::OK;
    }
    $user_attempts = (int)($result['total_attempts'] ?? 0);

    if ($user_attempts >= RATE_LIMIT_ATTEMPTS) {
        return RateLimitResult::EXCEEDED;
    }
    return RateLimitResult::OK;
}

function addUsernameRateLimitEvent(?PDO $db, $username) {
    $db = $db ?? Database::getConnection();
    $username = $username ? strtolower(trim($username)) : '';
    if (empty($username)) {
        return;
    }
    $current_time = time();

    $stmt = $db->prepare('
        INSERT INTO username_login_attempts (username, attempt_time)
        VALUES (:username, :attempt_time)
        ON CONFLICT(username, attempt_time) 
        DO UPDATE SET attempt_count = attempt_count + 1

    ');
    $stmt->execute(['username' => $username, 'attempt_time' => $current_time]);
}

// Delete old login attempts and banned IPs.
function cleanupRateLimitEntries(?PDO $db) {
    $db = $db ?? Database::getConnection();
    $current_time = time();
    $threshold_time = $current_time - LOCKOUT_DURATION;

    $db->beginTransaction();
    $stmt1 = $db->prepare("DELETE FROM login_attempts WHERE attempt_time < :threshold");
    $stmt2 = $db->prepare("DELETE FROM user_login_attempts WHERE attempt_time < :threshold");
    $stmt3 = $db->prepare("
        DELETE FROM banned_ips WHERE (ban_start + ban_duration + ONE_DAY_IN_SECONDS) < :current_time
    ");
    $stmt1->execute(['threshold' => $threshold_time]);
    $stmt2->execute(['threshold' => $threshold_time]);
    $stmt3->execute(['current_time' => $current_time]);
    $db->commit();
}

?>