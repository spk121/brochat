<?php
/**
 * Function to get client IP (supports proxies)
 * @return string Client IP address
 */
function getClientIP(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
?>

