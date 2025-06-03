<?php
/**
 * Security Helper Functions
 * Provides XSS protection for strict CSP compliance
 */

// Escape JavaScript strings to prevent XSS
function escape_js($string) {
    return json_encode($string, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}

// Enhanced HTML escaping (alias for existing function)
function escape_html($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Generate strict Content Security Policy header
function generate_csp_header() {
    $csp = "default-src 'self'; " .
           "script-src 'self'; " .
           "style-src 'self'; " .
           "img-src 'self' data:; " .
           "media-src 'self'; " .
           "connect-src 'self'; " .
           "font-src 'self'; " .
           "object-src 'none'; " .
           "base-uri 'self'; " .
           "form-action 'self'; " .
           "frame-ancestors 'none';";
    
    return $csp;
}

// Set security headers
function set_security_headers() {
    // Strict CSP header
    header("Content-Security-Policy: " . generate_csp_header());
    
    // Additional security headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    
    // HSTS (only if using HTTPS)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }
}

// Sanitize output for different contexts
function sanitize_for_attribute($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitize_for_css($string) {
    // Remove any characters that could break out of CSS context
    return preg_replace('/[^a-zA-Z0-9\-_\s#.]/', '', $string);
}

function sanitize_for_url($string) {
    return filter_var($string, FILTER_SANITIZE_URL);
}

// Validate file upload paths (for photo viewing)
function validate_upload_path($path) {
    // Only allow specific patterns for uploaded files
    $allowed_patterns = [
        '/^\/uploads\/photos\/[a-zA-Z0-9_\-\.]+$/',
        '/^\/uploads\/previews\/[a-zA-Z0-9_\-\.]+$/'
    ];
    
    foreach ($allowed_patterns as $pattern) {
        if (preg_match($pattern, $path)) {
            return true;
        }
    }
    
    return false;
}

// Enhanced CSRF token generation and validation
function generate_csrf_token() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}

// Rate limiting helpers
function check_rate_limit($action, $identifier, $max_attempts = 10, $time_window = 300) {
    // This would integrate with your existing rate limiting system
    // For now, return true (no limit exceeded)
    return true;
}

// Input validation helpers
function validate_content_length($content, $max_length = 1000) {
    return mb_strlen($content, 'UTF-8') <= $max_length;
}

function validate_username($username) {
    return preg_match('/^[a-zA-Z0-9_\-]{3,20}$/', $username);
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Clean and validate uploaded file names
function sanitize_filename($filename) {
    // Remove any path traversal attempts
    $filename = basename($filename);
    
    // Only allow alphanumeric, dots, hyphens, and underscores
    $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $filename);
    
    // Ensure it's not empty and has an extension
    if (empty($filename) || strpos($filename, '.') === false) {
        return false;
    }
    
    return $filename;
}

// Logging for security events
function log_security_event($event_type, $details = [], $severity = 'medium') {
    // This would integrate with your existing security logging system
    error_log("Security Event [{$severity}]: {$event_type} - " . json_encode($details));
}
?>
