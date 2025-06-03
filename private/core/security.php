<?php
/**
 * BroChat Security Functions
 * Application-specific security measures for the punk rock community platform
 */

// Prevent direct access
if (!defined('BROCHAT_LOADED')) {
    die('Direct access not permitted');
}

// =============================================================================
// CORE SECURITY CLASS
// =============================================================================

class BroChatSecurity {
    // Punk rock community specific validation rules
    private static $validation_rules = [
        'username' => [
            'min_length' => 3,
            'max_length' => 20,
            'pattern' => '/^[a-zA-Z0-9_.-]+$/',
            'forbidden' => ['admin', 'root', 'moderator', 'brochat', 'punk', 'rock']
        ],
        'band_name' => [
            'min_length' => 2,
            'max_length' => 50,
            'pattern' => '/^[a-zA-Z0-9\s\-_.&!]+$/'
        ],
        'blog_content' => [
            'max_length' => 1000, // 1K UTF-8 limit
            'max_hashtags' => 10,
            'max_mentions' => 5
        ],
        'chat_message' => [
            'max_length' => 500,
            'max_emojis' => 20
        ]
    ];
    
    /**
     * Validate input against BroChat rules
     */
    public static function validate($input, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule_set) {
            $value = $input[$field] ?? null;
            $field_errors = [];
            
            foreach ($rule_set as $rule) {
                if (is_string($rule)) {
                    $field_errors = array_merge($field_errors, self::apply_rule($value, $rule, $field));
                } elseif (is_array($rule)) {
                    $rule_name = $rule[0];
                    $params = array_slice($rule, 1);
                    $field_errors = array_merge($field_errors, self::apply_rule($value, $rule_name, $field, $params));
                }
            }
            
            if (!empty($field_errors)) {
                $errors[$field] = $field_errors;
            }
        }
        
        return $errors;
    }
    
    /**
     * Apply validation rule
     */
    private static function apply_rule($value, $rule, $field, $params = []) {
        switch ($rule) {
            case 'required':
                return empty($value) ? ["$field is required"] : [];
                
            case 'punk_username':
                return self::validate_punk_username($value, $field);
                
            case 'band_name':
                return self::validate_band_name($value, $field);
                
            case 'blog_content':
                return self::validate_blog_content($value, $field);
                
            case 'chat_message':
                return self::validate_chat_message($value, $field);
                
            case 'no_spam':
                return self::check_spam_content($value, $field);
                
            case 'safe_html':
                return self::validate_safe_html($value, $field);
                
            default:
                // Fallback to basic validation
                return self::apply_basic_rule($value, $rule, $field, $params);
        }
    }
    
    /**
     * Validate punk rock username
     */
    private static function validate_punk_username($value, $field) {
        $errors = [];
        $rules = self::$validation_rules['username'];
        
        if (strlen($value) < $rules['min_length']) {
            $errors[] = "$field must be at least {$rules['min_length']} characters";
        }
        
        if (strlen($value) > $rules['max_length']) {
            $errors[] = "$field must not exceed {$rules['max_length']} characters";
        }
        
        if (!preg_match($rules['pattern'], $value)) {
            $errors[] = "$field can only contain letters, numbers, dots, dashes, and underscores";
        }
        
        if (in_array(strtolower($value), $rules['forbidden'])) {
            $errors[] = "$field is not available";
        }
        
        // Check for existing username
        if (get_user_by_username($value)) {
            $errors[] = "$field is already taken";
        }
        
        return $errors;
    }
    
    /**
     * Validate band name
     */
    private static function validate_band_name($value, $field) {
        $errors = [];
        $rules = self::$validation_rules['band_name'];
        
        if (strlen($value) < $rules['min_length']) {
            $errors[] = "$field must be at least {$rules['min_length']} characters";
        }
        
        if (strlen($value) > $rules['max_length']) {
            $errors[] = "$field must not exceed {$rules['max_length']} characters";
        }
        
        if (!preg_match($rules['pattern'], $value)) {
            $errors[] = "$field contains invalid characters";
        }
        
        return $errors;
    }
    
    /**
     * Validate blog content
     */
    private static function validate_blog_content($value, $field) {
        $errors = [];
        $rules = self::$validation_rules['blog_content'];
        
        // UTF-8 length check
        if (mb_strlen($value, 'UTF-8') > $rules['max_length']) {
            $errors[] = "$field must not exceed {$rules['max_length']} characters";
        }
        
        // Check hashtag count
        $hashtags = extract_hashtags($value);
        if (count($hashtags) > $rules['max_hashtags']) {
            $errors[] = "$field can contain at most {$rules['max_hashtags']} hashtags";
        }
        
        // Check mention count
        $mentions = extract_mentions($value);
        if (count($mentions) > $rules['max_mentions']) {
            $errors[] = "$field can contain at most {$rules['max_mentions']} mentions";
        }
        
        return $errors;
    }
    
    /**
     * Validate chat message
     */
    private static function validate_chat_message($value, $field) {
        $errors = [];
        $rules = self::$validation_rules['chat_message'];
        
        if (mb_strlen($value, 'UTF-8') > $rules['max_length']) {
            $errors[] = "$field must not exceed {$rules['max_length']} characters";
        }
        
        // Count emojis (simple check for now)
        $emoji_count = preg_match_all('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}]/u', $value);
        if ($emoji_count > $rules['max_emojis']) {
            $errors[] = "$field contains too many emojis (max {$rules['max_emojis']})";
        }
        
        return $errors;
    }
    
    /**
     * Check for spam content
     */
    private static function check_spam_content($value, $field) {
        $errors = [];
        
        // Repeated characters
        if (preg_match('/(.)\1{10,}/', $value)) {
            $errors[] = "$field contains excessive repeated characters";
        }
        
        // Multiple URLs
        if (preg_match_all('/https?:\/\/[^\s]+/', $value) > 3) {
            $errors[] = "$field contains too many URLs";
        }
        
        // Common spam phrases
        $spam_patterns = [
            '/\b(buy|sell|cheap|free|win|click|download|money)\b/i',
            '/\b(viagra|casino|poker|lottery|prize)\b/i',
            '/\b(urgent|limited|offer|deal|discount)\b/i'
        ];
        
        foreach ($spam_patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $errors[] = "$field appears to contain spam content";
                break;
            }
        }
        
        return $errors;
    }
    
    /**
     * Basic validation rules
     */
    private static function apply_basic_rule($value, $rule, $field, $params = []) {
        switch ($rule) {
            case 'email':
                return !filter_var($value, FILTER_VALIDATE_EMAIL) ? ["$field must be a valid email"] : [];
                
            case 'min_length':
                $min = $params[0] ?? 1;
                return strlen($value) < $min ? ["$field must be at least $min characters"] : [];
                
            case 'max_length':
                $max = $params[0] ?? 255;
                return strlen($value) > $max ? ["$field must not exceed $max characters"] : [];
                
            case 'numeric':
                return !is_numeric($value) ? ["$field must be numeric"] : [];
                
            case 'strong_password':
                return !is_password_strong($value) ? ["$field must be a strong password"] : [];
                
            default:
                return [];
        }
    }
}

// =============================================================================
// INPUT SANITIZATION FOR BROCHAT
// =============================================================================

/**
 * Sanitize input with BroChat context
 */
function sanitize_brochat_input($input, $type = 'general') {
    switch ($type) {
        case 'username':
            return preg_replace('/[^a-zA-Z0-9_.-]/', '', trim($input));
            
        case 'band_name':
            return preg_replace('/[^a-zA-Z0-9\s\-_.&!]/', '', trim($input));
            
        case 'blog_content':
            return sanitize_blog_content($input);
            
        case 'chat_message':
            return sanitize_chat_content($input);
            
        case 'tag':
            return preg_replace('/[^a-zA-Z0-9_-]/', '', trim($input));
            
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Sanitize blog content while preserving markdown
 */
function sanitize_blog_content($content) {
    // Limit to 1K UTF-8 characters
    if (mb_strlen($content, 'UTF-8') > 1000) {
        $content = mb_substr($content, 0, 1000, 'UTF-8');
    }
    
    // Remove dangerous HTML but keep basic formatting
    $content = strip_tags($content, '<em><strong><code>');
    
    // Remove any remaining script attempts
    $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $content);
    $content = preg_replace('/javascript:/i', '', $content);
    $content = preg_replace('/on\w+\s*=/i', '', $content);
    
    return trim($content);
}

/**
 * Sanitize chat message
 */
function sanitize_chat_content($message) {
    // Limit length
    if (mb_strlen($message, 'UTF-8') > 500) {
        $message = mb_substr($message, 0, 500, 'UTF-8');
    }
    
    // Remove all HTML tags
    $message = strip_tags($message);
    
    // Remove control characters except newlines and tabs
    $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $message);
    
    return trim($message);
}

// =============================================================================
// PHOTO UPLOAD SECURITY
// =============================================================================

class BroChatPhotoSecurity {
    private static $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private static $max_size = 5242880; // 5MB
    private static $max_dimension = 2048; // 2048px max width/height
    
    /**
     * Validate photo upload for blog posts
     */
    public static function validate_photo_upload($file) {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['No file uploaded or upload failed'];
        }
        
        // Check file size
        if ($file['size'] > self::$max_size) {
            $errors[] = 'File size exceeds limit (' . format_file_size(self::$max_size) . ')';
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::$allowed_types)) {
            $errors[] = 'Invalid file type. Allowed: ' . implode(', ', self::$allowed_types);
        }
        
        // Verify MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = [
            'image/jpeg', 'image/jpg', 'image/png', 
            'image/gif', 'image/webp'
        ];
        
        if (!in_array($mime_type, $allowed_mimes)) {
            $errors[] = 'Invalid image format detected';
        }
        
        // Verify it's actually an image
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            $errors[] = 'File is not a valid image';
        } else {
            // Check dimensions
            list($width, $height) = $image_info;
            if ($width > self::$max_dimension || $height > self::$max_dimension) {
                $errors[] = "Image dimensions too large (max {self::$max_dimension}px)";
            }
        }
        
        // Scan for embedded malicious content
        if (self::scan_image_for_threats($file['tmp_name'])) {
            $errors[] = 'Image contains potentially dangerous content';
        }
        
        return $errors;
    }
    
    /**
     * Scan image for potential threats
     */
    private static function scan_image_for_threats($filepath) {
        // Read first 1KB of file to check for suspicious content
        $content = file_get_contents($filepath, false, null, 0, 1024);
        
        $suspicious_patterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/eval\s*\(/i',
            '/base64_decode/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec/i',
            '/passthru/i'
        ];
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate secure filename for uploaded photo
     */
    public static function secure_photo_filename($original_name) {
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        
        // Generate unique filename
        $filename = 'photo_' . uniqid() . '_' . time() . '.' . $extension;
        
        return $filename;
    }
}

// =============================================================================
// RATE LIMITING FOR BROCHAT FEATURES
// =============================================================================

class BroChatRateLimit {
    private static $limits = [
        'login' => ['max' => 5, 'window' => 900],        // 5 attempts per 15 minutes
        'blog_post' => ['max' => 10, 'window' => 3600],  // 10 posts per hour
        'chat_message' => ['max' => 60, 'window' => 60], // 60 messages per minute
        'photo_upload' => ['max' => 20, 'window' => 3600], // 20 photos per hour
        'stream_connect' => ['max' => 100, 'window' => 3600], // 100 stream connections per hour
        'password_reset' => ['max' => 3, 'window' => 3600], // 3 password resets per hour
        'mention' => ['max' => 50, 'window' => 3600]     // 50 mentions per hour
    ];
    
    /**
     * Check rate limit for BroChat action
     */
    public static function check($action, $identifier = null) {
        if ($identifier === null) {
            // Use user ID if logged in, otherwise IP
            if (is_logged_in()) {
                $identifier = 'user_' . current_user()['id'];
            } else {
                $identifier = 'ip_' . get_client_ip();
            }
        }
        
        $limit = self::$limits[$action] ?? ['max' => 10, 'window' => 60];
        $key = $action . '_' . $identifier;
        
        // Clean old attempts
        self::cleanup_old_attempts($key, $limit['window']);
        
        // Count current attempts
        $count = self::get_attempt_count($key);
        
        if ($count >= $limit['max']) {
            self::log_rate_limit_violation($action, $identifier, $count);
            return false;
        }
        
        // Record this attempt
        self::record_attempt($key, $action);
        
        return true;
    }
    
    /**
     * Get remaining attempts
     */
    public static function remaining($action, $identifier = null) {
        if ($identifier === null) {
            if (is_logged_in()) {
                $identifier = 'user_' . current_user()['id'];
            } else {
                $identifier = 'ip_' . get_client_ip();
            }
        }
        
        $limit = self::$limits[$action] ?? ['max' => 10, 'window' => 60];
        $key = $action . '_' . $identifier;
        
        self::cleanup_old_attempts($key, $limit['window']);
        $count = self::get_attempt_count($key);
        
        return max(0, $limit['max'] - $count);
    }
    
    /**
     * Record rate limit attempt
     */
    private static function record_attempt($key, $action) {
        db_insert('rate_limit_attempts', [
            'key' => $key,
            'action' => $action,
            'ip_address' => get_client_ip(),
            'user_id' => is_logged_in() ? current_user()['id'] : null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get attempt count
     */
    private static function get_attempt_count($key) {
        $result = db_fetch(
            'SELECT COUNT(*) as count FROM rate_limit_attempts WHERE key = ?',
            [$key]
        );
        return $result['count'] ?? 0;
    }
    
    /**
     * Clean up old attempts
     */
    private static function cleanup_old_attempts($key, $window) {
        $cutoff = date('Y-m-d H:i:s', time() - $window);
        db_delete('rate_limit_attempts', 'key = ? AND created_at < ?', [$key, $cutoff]);
    }
    
    /**
     * Log rate limit violation
     */
    private static function log_rate_limit_violation($action, $identifier, $count) {
        error_log("BroChat rate limit exceeded: $action by $identifier ($count attempts)");
        
        db_insert('security_events', [
            'event_type' => 'rate_limit_exceeded',
            'ip_address' => get_client_ip(),
            'user_id' => is_logged_in() ? current_user()['id'] : null,
            'details' => json_encode(['action' => $action, 'attempts' => $count]),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}

// =============================================================================
// SECURITY HEADERS & CONFIGURATION
// =============================================================================

/**
 * Set BroChat security headers
 */
function set_brochat_security_headers() {
    // Basic security headers
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // HTTPS enforcement
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
    
    // Permissions policy (restrictive for punk rock simplicity)
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    
    // Content Security Policy for BroChat
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline'; " . // Allow inline for simple punk rock styling
           "style-src 'self' 'unsafe-inline'; " .
           "img-src 'self' data: blob:; " .
           "media-src 'self'; " .
           "connect-src 'self' ws: wss:; " . // Allow WebSocket connections
           "font-src 'self'; " .
           "frame-ancestors 'none'; " .
           "base-uri 'self'; " .
           "form-action 'self'";
    
    header("Content-Security-Policy: $csp");
}

/**
 * Configure PHP for BroChat security
 */
function configure_brochat_php_security() {
    // Hide PHP version
    ini_set('expose_php', 0);
    
    // Session security
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']));
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    
    // File upload limits
    ini_set('upload_max_filesize', '5M');
    ini_set('post_max_size', '25M'); // Allow 4 photos + content
    ini_set('max_file_uploads', 4);   // Max 4 photos per blog post
    
    // Memory and execution limits
    ini_set('memory_limit', '64M');
    ini_set('max_execution_time', 30);
}

// =============================================================================
// CONTENT FILTERING & SPAM DETECTION
// =============================================================================

/**
 * Advanced spam detection for punk rock community
 */
function detect_punk_spam($content, $type = 'general') {
    $spam_score = 0;
    $flags = [];
    
    // Check for excessive repetition
    if (preg_match('/(.{3,})\1{3,}/', $content)) {
        $spam_score += 30;
        $flags[] = 'excessive_repetition';
    }
    
    // Check for multiple URLs
    $url_count = preg_match_all('/https?:\/\/[^\s]+/', $content);
    if ($url_count > 2) {
        $spam_score += 20 * ($url_count - 2);
        $flags[] = 'multiple_urls';
    }
    
    // Check for non-punk commercial content
    $commercial_patterns = [
        '/\b(buy|sell|cheap|discount|sale|offer|deal|price)\b/i',
        '/\b(casino|poker|lottery|gambling|bet)\b/i',
        '/\b(loan|credit|mortgage|investment|money)\b/i',
        '/\b(pharmaceutical|viagra|cialis|pills)\b/i'
    ];
    
    foreach ($commercial_patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            $spam_score += 25;
            $flags[] = 'commercial_content';
            break;
        }
    }
    
    // Check for excessive emojis (keep it punk, not emoji spam)
    $emoji_count = preg_match_all('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}]/u', $content);
    if ($emoji_count > 10) {
        $spam_score += 15;
        $flags[] = 'excessive_emojis';
    }
    
    // Check for excessive capitalization (shouting)
    $caps_ratio = strlen(preg_replace('/[^A-Z]/', '', $content)) / max(1, strlen(preg_replace('/[^A-Za-z]/', '', $content)));
    if ($caps_ratio > 0.7 && strlen($content) > 20) {
        $spam_score += 20;
        $flags[] = 'excessive_caps';
    }
    
    // Chat-specific checks
    if ($type === 'chat') {
        // Check for flooding patterns
        if (preg_match('/^(.)\1{20,}$/', trim($content))) {
            $spam_score += 50;
            $flags[] = 'character_flooding';
        }
    }
    
    // Blog-specific checks
    if ($type === 'blog') {
        // Check for excessive hashtags
        $hashtag_count = preg_match_all('/#\w+/', $content);
        if ($hashtag_count > 10) {
            $spam_score += 15;
            $flags[] = 'hashtag_spam';
        }
        
        // Check for excessive mentions
        $mention_count = preg_match_all('/@\w+/', $content);
        if ($mention_count > 5) {
            $spam_score += 10;
            $flags[] = 'mention_spam';
        }
    }
    
    return [
        'spam_score' => $spam_score,
        'is_spam' => $spam_score >= 50,
        'flags' => $flags
    ];
}

/**
 * Filter content for punk rock community standards
 */
function filter_punk_content($content, $context = 'general') {
    // Allowed punk rock profanity (we're not prudes)
    $punk_allowed = ['damn', 'hell', 'crap', 'piss', 'shit']; // Keep it real but not excessive
    
    // Actually problematic content that goes against punk values
    $prohibited_patterns = [
        '/\b(nazi|fascist|white power|hitler)\b/i', // Anti-fascist punk values
        '/\b(nigger|chink|spic|kike)\b/i',          // No racial slurs
        '/\b(kill yourself|kys)\b/i',               // No encouraging self-harm
        '/\b(dox|doxx|doxxing)\b/i'                 // No doxxing
    ];
    
    $flags = [];
    $severity = 'none';
    
    foreach ($prohibited_patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            $flags[] = 'prohibited_content';
            $severity = 'high';
            break;
        }
    }
    
    return [
        'allowed' => empty($flags),
        'flags' => $flags,
        'severity' => $severity,
        'filtered_content' => $content // For now, return as-is, could implement filtering
    ];
}

// =============================================================================
// IP BLOCKING & SECURITY MONITORING
// =============================================================================

/**
 * Check if IP is blocked
 */
function is_ip_blocked($ip = null) {
    if ($ip === null) {
        $ip = get_client_ip();
    }
    
    $blocked = db_fetch(
        'SELECT * FROM blocked_ips 
         WHERE ip_address = ? 
         AND (expires_at IS NULL OR expires_at > ?)',
        [$ip, date('Y-m-d H:i:s')]
    );
    
    return $blocked !== false;
}

/**
 * Block IP address
 */
function block_ip($ip, $reason, $duration_hours = 24) {
    $expires_at = $duration_hours ? date('Y-m-d H:i:s', time() + ($duration_hours * 3600)) : null;
    
    return db_insert('blocked_ips', [
        'ip_address' => $ip,
        'reason' => $reason,
        'blocked_by' => is_logged_in() ? current_user()['id'] : null,
        'expires_at' => $expires_at,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Log security event
 */
function log_security_event($event_type, $details = null, $severity = 'medium') {
    db_insert('security_events', [
        'event_type' => $event_type,
        'severity' => $severity,
        'ip_address' => get_client_ip(),
        'user_id' => is_logged_in() ? current_user()['id'] : null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'details' => $details ? json_encode($details) : null,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Monitor suspicious activity
 */
function monitor_suspicious_activity() {
    $ip = get_client_ip();
    $user_id = is_logged_in() ? current_user()['id'] : null;
    
    // Check for rapid requests
    $recent_requests = db_fetch(
        'SELECT COUNT(*) as count FROM page_views 
         WHERE ip_address = ? AND viewed_at > ?',
        [$ip, date('Y-m-d H:i:s', strtotime('-1 minute'))]
    );
    
    if ($recent_requests['count'] > 30) {
        log_security_event('rapid_requests', ['requests_per_minute' => $recent_requests['count']], 'high');
        
        // Auto-block if extremely rapid
        if ($recent_requests['count'] > 100) {
            block_ip($ip, 'Automated blocking: Excessive requests', 1);
        }
    }
    
    // Check for failed login patterns
    if (!$user_id) {
        $failed_logins = db_fetch(
            'SELECT COUNT(*) as count FROM login_attempts 
             WHERE ip_address = ? AND success = 0 AND attempted_at > ?',
            [$ip, date('Y-m-d H:i:s', strtotime('-15 minutes'))]
        );
        
        if ($failed_logins['count'] > 10) {
            log_security_event('brute_force_attempt', ['failed_attempts' => $failed_logins['count']], 'high');
            block_ip($ip, 'Brute force login attempts', 2);
        }
    }
}

// =============================================================================
// CONVENIENCE FUNCTIONS
// =============================================================================

/**
 * Validate BroChat input
 */
function validate_brochat_input($input, $rules) {
    return BroChatSecurity::validate($input, $rules);
}

/**
 * Check BroChat rate limit
 */
function brochat_rate_limit_check($action, $identifier = null) {
    return BroChatRateLimit::check($action, $identifier);
}

/**
 * Require rate limit check
 */
function require_brochat_rate_limit($action, $identifier = null) {
    if (!brochat_rate_limit_check($action, $identifier)) {
        $remaining = BroChatRateLimit::remaining($action, $identifier);
        http_response_code(429);
        flash_error("Rate limit exceeded for $action. Try again later.");
        die("Rate limit exceeded. Remaining attempts: $remaining");
    }
}

/**
 * Validate photo upload
 */
function validate_brochat_photo($file) {
    return BroChatPhotoSecurity::validate_photo_upload($file);
}

/**
 * Generate secure photo filename
 */
function secure_photo_filename($original_name) {
    return BroChatPhotoSecurity::secure_photo_filename($original_name);
}

/**
 * Check content for spam
 */
function is_content_spam($content, $type = 'general') {
    $result = detect_punk_spam($content, $type);
    return $result['is_spam'];
}

/**
 * Filter content for community standards
 */
function filter_content($content, $context = 'general') {
    return filter_punk_content($content, $context);
}

/**
 * Initialize BroChat security
 */
function init_brochat_security() {
    // Set security headers
    set_brochat_security_headers();
    
    // Configure PHP security
    configure_brochat_php_security();
    
    // Check if IP is blocked
    if (is_ip_blocked()) {
        http_response_code(403);
        die('Access denied: IP address blocked');
    }
    
    // Monitor suspicious activity
    if (rand(1, 10) === 1) { // 10% chance to avoid overhead
        monitor_suspicious_activity();
    }
}

/**
 * Security maintenance tasks
 */
function run_security_maintenance() {
    $tasks = [];
    
    // Clean up old rate limit attempts
    $cutoff = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $cleaned_rate_limits = db_delete('rate_limit_attempts', 'created_at < ?', [$cutoff]);
    $tasks[] = "Cleaned $cleaned_rate_limits old rate limit records";
    
    // Clean up expired IP blocks
    $cleaned_blocks = db_delete('blocked_ips', 'expires_at IS NOT NULL AND expires_at < ?', [date('Y-m-d H:i:s')]);
    $tasks[] = "Removed $cleaned_blocks expired IP blocks";
    
    // Clean up old security events
    $old_events_cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
    $cleaned_events = db_delete('security_events', 'created_at < ?', [$old_events_cutoff]);
    $tasks[] = "Cleaned $cleaned_events old security events";
    
    // Clean up old login attempts
    $cleaned_attempts = db_delete('login_attempts', 'attempted_at < ?', [$cutoff]);
    $tasks[] = "Cleaned $cleaned_attempts old login attempts";
    
    return $tasks;
}

/**
 * Get security status report
 */
function get_security_status() {
    $blocked_ips = db_fetch('SELECT COUNT(*) as count FROM blocked_ips WHERE expires_at IS NULL OR expires_at > ?', [date('Y-m-d H:i:s')]);
    $recent_events = db_fetch('SELECT COUNT(*) as count FROM security_events WHERE created_at > ?', [date('Y-m-d H:i:s', strtotime('-24 hours'))]);
    $rate_limit_violations = db_fetch('SELECT COUNT(*) as count FROM security_events WHERE event_type = "rate_limit_exceeded" AND created_at > ?', [date('Y-m-d H:i:s', strtotime('-24 hours'))]);
    
    return [
        'blocked_ips' => $blocked_ips['count'] ?? 0,
        'recent_security_events' => $recent_events['count'] ?? 0,
        'rate_limit_violations_24h' => $rate_limit_violations['count'] ?? 0,
        'status' => 'operational' // Could be enhanced with threat level logic
    ];
}
?>
