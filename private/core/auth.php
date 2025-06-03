<?php
/**
 * BroChat Authentication & Authorization
 * Handles user authentication, role-based access, and punk rock community features
 */

// Prevent direct access
if (!defined('BROCHAT_LOADED')) {
    die('Direct access not permitted');
}

// =============================================================================
// CORE AUTHENTICATION CLASS
// =============================================================================

class BroChatAuth {
    private static $current_user = null;
    private static $permissions_cache = [];
    
    /**
     * Authenticate user login
     */
    public static function login($username, $password, $remember_me = false) {
        // Rate limiting
        if (!rate_limit_check('login', $username)) {
            self::log_security_event('login_rate_limited', ['username' => $username]);
            return ['success' => false, 'error' => 'Too many login attempts. Please try again later.'];
        }
        
        $user = get_user_by_username($username);
        
        if (!$user) {
            self::log_failed_attempt($username, 'user_not_found');
            return ['success' => false, 'error' => 'Invalid username or password'];
        }
        
        // Check account status
        if ($user['status'] !== 'active') {
            self::log_failed_attempt($username, 'account_inactive');
            return ['success' => false, 'error' => 'Account is not active'];
        }
        
        if ($user['banned_until'] && strtotime($user['banned_until']) > time()) {
            self::log_failed_attempt($username, 'account_banned');
            return ['success' => false, 'error' => 'Account is temporarily banned'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            self::increment_failed_attempts($user['id']);
            self::log_failed_attempt($username, 'invalid_password');
            return ['success' => false, 'error' => 'Invalid username or password'];
        }
        
        // Check if account is locked due to failed attempts
        if (self::is_account_locked($user)) {
            self::log_failed_attempt($username, 'account_locked');
            return ['success' => false, 'error' => 'Account temporarily locked due to failed login attempts'];
        }
        
        // Successful login
        self::create_session($user, $remember_me);
        self::reset_failed_attempts($user['id']);
        self::update_last_login($user['id']);
        
        return ['success' => true, 'user' => $user];
    }
    
    /**
     * Logout current user
     */
    public static function logout() {
        if (self::is_authenticated()) {
            $user_id = self::user()['id'];
            self::log_activity($user_id, 'logout');
            self::destroy_session();
        }
    }
    
    /**
     * Check if user is authenticated
     */
    public static function is_authenticated() {
        return self::user() !== null;
    }
    
    /**
     * Get current authenticated user
     */
    public static function user() {
        if (self::$current_user === null && isset($_SESSION['user_id'])) {
            self::$current_user = get_user_by_id($_SESSION['user_id']);
            
            // Verify session is still valid
            if (!self::$current_user || !self::is_session_valid()) {
                self::logout();
                return null;
            }
        }
        return self::$current_user;
    }
    
    /**
     * Create user session
     */
    private static function create_session($user, $remember_me = false) {
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = get_client_ip();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Remember me functionality
        if ($remember_me) {
            self::create_remember_token($user['id']);
        }
        
        self::log_activity($user['id'], 'login', [
            'ip_address' => get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    
    /**
     * Destroy user session
     */
    private static function destroy_session() {
        // Clear remember token
        if (isset($_COOKIE['brochat_remember'])) {
            self::clear_remember_token($_COOKIE['brochat_remember']);
            setcookie('brochat_remember', '', time() - 3600, '/', '', true, true);
        }
        
        self::$current_user = null;
        $_SESSION = [];
        session_destroy();
        session_start(); // Restart for flash messages
    }
    
    /**
     * Validate current session
     */
    private static function is_session_valid() {
        // Check session timeout (24 hours)
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > 86400) {
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Handle remember me tokens
     */
    private static function create_remember_token($user_id) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        db_insert('remember_tokens', [
            'user_id' => $user_id,
            'token' => hash('sha256', $token),
            'expires_at' => $expires,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        setcookie('brochat_remember', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
    }
    
    /**
     * Check remember me token
     */
    public static function check_remember_token() {
        if (!self::is_authenticated() && isset($_COOKIE['brochat_remember'])) {
            $token = $_COOKIE['brochat_remember'];
            $hashed_token = hash('sha256', $token);
            
            $token_data = db_fetch(
                'SELECT rt.*, u.* FROM remember_tokens rt
                 JOIN users u ON rt.user_id = u.id
                 WHERE rt.token = ? AND rt.expires_at > ? AND u.status = "active"',
                [$hashed_token, date('Y-m-d H:i:s')]
            );
            
            if ($token_data) {
                self::create_session($token_data, true);
                return true;
            } else {
                // Invalid token, clear it
                setcookie('brochat_remember', '', time() - 3600, '/', '', true, true);
            }
        }
        return false;
    }
    
    /**
     * Clear remember token
     */
    private static function clear_remember_token($token) {
        $hashed_token = hash('sha256', $token);
        db_delete('remember_tokens', 'token = ?', [$hashed_token]);
    }
}

// =============================================================================
// PUNK ROCK COMMUNITY ROLES & PERMISSIONS
// =============================================================================

class BroChatRoles {
    // Community roles with punk rock attitude
    const ROLES = [
        'fan' => [
            'name' => 'Fan',
            'description' => 'Basic community member',
            'permissions' => ['read_blog', 'chat', 'listen_stream']
        ],
        'regular' => [
            'name' => 'Regular',
            'description' => 'Trusted community member',
            'permissions' => ['read_blog', 'write_blog', 'chat', 'listen_stream', 'upload_photos']
        ],
        'roadie' => [
            'name' => 'Roadie', 
            'description' => 'Community helper with moderation powers',
            'permissions' => ['read_blog', 'write_blog', 'chat', 'listen_stream', 'upload_photos', 'moderate_chat', 'moderate_blog']
        ],
        'dj' => [
            'name' => 'DJ',
            'description' => 'Can manage the audio stream',
            'permissions' => ['read_blog', 'write_blog', 'chat', 'listen_stream', 'upload_photos', 'manage_stream']
        ],
        'admin' => [
            'name' => 'Admin',
            'description' => 'Full system access',
            'permissions' => ['*'] // All permissions
        ]
    ];
    
    /**
     * Check if user has specific permission
     */
    public static function has_permission($permission, $user = null) {
        if (!$user) {
            $user = BroChatAuth::user();
        }
        
        if (!$user) {
            return false;
        }
        
        $role = $user['role'] ?? 'fan';
        
        // Cache permissions for performance
        $cache_key = $role . '_' . $permission;
        if (isset(BroChatAuth::$permissions_cache[$cache_key])) {
            return BroChatAuth::$permissions_cache[$cache_key];
        }
        
        $role_config = self::ROLES[$role] ?? self::ROLES['fan'];
        $permissions = $role_config['permissions'];
        
        // Admin has all permissions
        if (in_array('*', $permissions)) {
            BroChatAuth::$permissions_cache[$cache_key] = true;
            return true;
        }
        
        $has_permission = in_array($permission, $permissions);
        BroChatAuth::$permissions_cache[$cache_key] = $has_permission;
        
        return $has_permission;
    }
    
    /**
     * Check if user has role
     */
    public static function has_role($role, $user = null) {
        if (!$user) {
            $user = BroChatAuth::user();
        }
        
        if (!$user) {
            return false;
        }
        
        return $user['role'] === $role;
    }
    
    /**
     * Get user's role display name
     */
    public static function get_role_display_name($role) {
        return self::ROLES[$role]['name'] ?? 'Unknown';
    }
    
    /**
     * Check if user can moderate another user
     */
    public static function can_moderate_user($moderator_user_id, $target_user_id) {
        $moderator = get_user_by_id($moderator_user_id);
        $target = get_user_by_id($target_user_id);
        
        if (!$moderator || !$target) {
            return false;
        }
        
        // Can't moderate yourself
        if ($moderator_user_id === $target_user_id) {
            return false;
        }
        
        // Admins can moderate anyone except other admins
        if ($moderator['role'] === 'admin' && $target['role'] !== 'admin') {
            return true;
        }
        
        // Roadies can moderate fans and regulars
        if ($moderator['role'] === 'roadie' && in_array($target['role'], ['fan', 'regular'])) {
            return true;
        }
        
        return false;
    }
}

// =============================================================================
// ACCOUNT SECURITY & BRUTE FORCE PROTECTION
// =============================================================================

class BroChatAuth {
    /**
     * Increment failed login attempts
     */
    private static function increment_failed_attempts($user_id) {
        db_query(
            'UPDATE users SET failed_login_attempts = failed_login_attempts + 1, 
             last_failed_login = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $user_id]
        );
    }
    
    /**
     * Reset failed login attempts
     */
    private static function reset_failed_attempts($user_id) {
        db_update('users', 
            ['failed_login_attempts' => 0, 'last_failed_login' => null],
            'id = ?',
            [$user_id]
        );
    }
    
    /**
     * Check if account is locked
     */
    private static function is_account_locked($user) {
        $max_attempts = 5;
        $lockout_duration = 900; // 15 minutes
        
        if ($user['failed_login_attempts'] >= $max_attempts) {
            if ($user['last_failed_login']) {
                $time_since_last = time() - strtotime($user['last_failed_login']);
                return $time_since_last < $lockout_duration;
            }
            return true;
        }
        return false;
    }
    
    /**
     * Update last login timestamp
     */
    private static function update_last_login($user_id) {
        db_update('users', 
            ['last_login' => date('Y-m-d H:i:s')],
            'id = ?',
            [$user_id]
        );
    }
    
    /**
     * Log failed login attempt
     */
    private static function log_failed_attempt($username, $reason) {
        db_insert('login_attempts', [
            'username' => $username,
            'ip_address' => get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'success' => 0,
            'failure_reason' => $reason,
            'attempted_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Log user activity
     */
    public static function log_activity($user_id, $action, $details = null) {
        db_insert('user_activity_log', [
            'user_id' => $user_id,
            'action' => $action,
            'ip_address' => get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'details' => $details ? json_encode($details) : null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Log security events
     */
    private static function log_security_event($event_type, $details = null) {
        db_insert('security_events', [
            'event_type' => $event_type,
            'ip_address' => get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'details' => $details ? json_encode($details) : null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}

// =============================================================================
// COMMUNITY MODERATION FUNCTIONS
// =============================================================================

/**
 * Mute user in chat
 */
function mute_user($user_id, $duration_minutes = 60, $reason = null) {
    $current_user = current_user();
    if (!BroChatRoles::has_permission('moderate_chat')) {
        return false;
    }
    
    if (!BroChatRoles::can_moderate_user($current_user['id'], $user_id)) {
        return false;
    }
    
    $expires_at = date('Y-m-d H:i:s', time() + ($duration_minutes * 60));
    
    $mute_id = db_insert('user_mutes', [
        'user_id' => $user_id,
        'muted_by' => $current_user['id'],
        'reason' => $reason,
        'expires_at' => $expires_at,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Log the action
    BroChatAuth::log_activity($current_user['id'], 'user_muted', [
        'target_user_id' => $user_id,
        'duration_minutes' => $duration_minutes,
        'reason' => $reason
    ]);
    
    return $mute_id;
}

/**
 * Check if user is muted
 */
function is_user_muted($user_id) {
    $mute = db_fetch(
        'SELECT * FROM user_mutes 
         WHERE user_id = ? AND expires_at > ? 
         ORDER BY created_at DESC LIMIT 1',
        [$user_id, date('Y-m-d H:i:s')]
    );
    
    return $mute !== false;
}

/**
 * Ban user temporarily
 */
function ban_user($user_id, $duration_hours = 24, $reason = null) {
    $current_user = current_user();
    if (!BroChatRoles::has_permission('moderate_blog') && !BroChatRoles::has_role('admin')) {
        return false;
    }
    
    if (!BroChatRoles::can_moderate_user($current_user['id'], $user_id)) {
        return false;
    }
    
    $banned_until = date('Y-m-d H:i:s', time() + ($duration_hours * 3600));
    
    $success = db_update('users', 
        ['banned_until' => $banned_until, 'ban_reason' => $reason],
        'id = ?',
        [$user_id]
    ) > 0;
    
    if ($success) {
        // Log the ban
        BroChatAuth::log_activity($current_user['id'], 'user_banned', [
            'target_user_id' => $user_id,
            'duration_hours' => $duration_hours,
            'reason' => $reason
        ]);
        
        // Record in moderation log
        db_insert('moderation_log', [
            'moderator_id' => $current_user['id'],
            'target_user_id' => $user_id,
            'action' => 'ban',
            'reason' => $reason,
            'duration' => $duration_hours . ' hours',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    return $success;
}

/**
 * Promote user to higher role
 */
function promote_user($user_id, $new_role) {
    $current_user = current_user();
    if (!BroChatRoles::has_role('admin')) {
        return false;
    }
    
    if (!array_key_exists($new_role, BroChatRoles::ROLES)) {
        return false;
    }
    
    $target_user = get_user_by_id($user_id);
    if (!$target_user) {
        return false;
    }
    
    $old_role = $target_user['role'];
    
    $success = db_update('users', 
        ['role' => $new_role, 'updated_at' => date('Y-m-d H:i:s')],
        'id = ?',
        [$user_id]
    ) > 0;
    
    if ($success) {
        BroChatAuth::log_activity($current_user['id'], 'user_promoted', [
            'target_user_id' => $user_id,
            'old_role' => $old_role,
            'new_role' => $new_role
        ]);
    }
    
    return $success;
}

// =============================================================================
// CONTENT ACCESS CONTROL
// =============================================================================

/**
 * Check if user can post to blog
 */
function can_write_blog($user = null) {
    return BroChatRoles::has_permission('write_blog', $user);
}

/**
 * Check if user can moderate blog posts
 */
function can_moderate_blog($user = null) {
    return BroChatRoles::has_permission('moderate_blog', $user);
}

/**
 * Check if user can upload photos
 */
function can_upload_photos($user = null) {
    return BroChatRoles::has_permission('upload_photos', $user);
}

/**
 * Check if user can delete blog post
 */
function can_delete_blog_post($post_id, $user = null) {
    if (!$user) {
        $user = current_user();
    }
    
    if (!$user) {
        return false;
    }
    
    $post = db_fetch('SELECT user_id FROM blog_posts WHERE id = ?', [$post_id]);
    if (!$post) {
        return false;
    }
    
    // Users can delete their own posts
    if ($post['user_id'] == $user['id']) {
        return true;
    }
    
    // Moderators can delete any post
    return can_moderate_blog($user);
}

/**
 * Check if user can delete chat message
 */
function can_delete_chat_message($message_id, $user = null) {
    if (!$user) {
        $user = current_user();
    }
    
    if (!$user) {
        return false;
    }
    
    $message = db_fetch('SELECT user_id FROM chat_messages WHERE id = ?', [$message_id]);
    if (!$message) {
        return false;
    }
    
    // Users can delete their own messages
    if ($message['user_id'] == $user['id']) {
        return true;
    }
    
    // Moderators can delete any message
    return BroChatRoles::has_permission('moderate_chat', $user);
}

/**
 * Check if user can manage audio stream
 */
function can_manage_stream($user = null) {
    return BroChatRoles::has_permission('manage_stream', $user);
}

// =============================================================================
// PASSWORD MANAGEMENT
// =============================================================================

/**
 * Check if password meets punk rock standards (strong but not overly complex)
 */
function is_password_strong($password) {
    // Minimum 8 characters, at least one letter and one number
    $min_length = strlen($password) >= 8;
    $has_letter = preg_match('/[a-zA-Z]/', $password);
    $has_number = preg_match('/[0-9]/', $password);
    
    return $min_length && $has_letter && $has_number;
}

/**
 * Generate password reset token
 */
function generate_password_reset_token($user_id) {
    // Clean up old tokens first
    db_delete('password_resets', 'user_id = ? OR expires_at < ?', [$user_id, date('Y-m-d H:i:s')]);
    
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    db_insert('password_resets', [
        'user_id' => $user_id,
        'token' => hash('sha256', $token),
        'expires_at' => $expires,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    return $token;
}

/**
 * Verify password reset token
 */
function verify_password_reset_token($token) {
    $hashed_token = hash('sha256', $token);
    return db_fetch(
        'SELECT pr.*, u.username FROM password_resets pr
         JOIN users u ON pr.user_id = u.id
         WHERE pr.token = ? AND pr.expires_at > ? AND pr.used_at IS NULL',
        [$hashed_token, date('Y-m-d H:i:s')]
    );
}

/**
 * Reset user password
 */
function reset_password($token, $new_password) {
    $reset = verify_password_reset_token($token);
    if (!$reset) {
        return false;
    }
    
    if (!is_password_strong($new_password)) {
        throw new InvalidArgumentException('Password does not meet requirements');
    }
    
    return db_transaction(function() use ($reset, $new_password) {
        // Update password
        db_update('users', 
            ['password_hash' => hash_password($new_password), 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$reset['user_id']]
        );
        
        // Mark token as used
        db_update('password_resets',
            ['used_at' => date('Y-m-d H:i:s')],
            'user_id = ?',
            [$reset['user_id']]
        );
        
        // Log password reset
        BroChatAuth::log_activity($reset['user_id'], 'password_reset');
        
        return true;
    });
}

// =============================================================================
// CONVENIENCE FUNCTIONS
// =============================================================================

/**
 * Login user
 */
function auth_login($username, $password, $remember_me = false) {
    return BroChatAuth::login($username, $password, $remember_me);
}

/**
 * Logout user
 */
function auth_logout() {
    BroChatAuth::logout();
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return BroChatAuth::is_authenticated();
}

/**
 * Get current user
 */
function current_user() {
    return BroChatAuth::user();
}

/**
 * Require user to be logged in
 */
function require_login($redirect_to = '/login.php') {
    if (!is_logged_in()) {
        flash_info('Please log in to access this page');
        redirect($redirect_to);
    }
}

/**
 * Require specific permission
 */
function require_permission($permission, $error_message = null) {
    if (!BroChatRoles::has_permission($permission)) {
        if (!$error_message) {
            $error_message = 'You do not have permission to access this feature';
        }
        
        http_response_code(403);
        flash_error($error_message);
        redirect('/');
    }
}

/**
 * Require specific role
 */
function require_role($role, $error_message = null) {
    if (!BroChatRoles::has_role($role)) {
        if (!$error_message) {
            $role_name = BroChatRoles::get_role_display_name($role);
            $error_message = "This feature requires $role_name access";
        }
        
        http_response_code(403);
        flash_error($error_message);
        redirect('/');
    }
}

/**
 * Check if current user can chat
 */
function can_chat() {
    $user = current_user();
    if (!$user) {
        return false;
    }
    
    // Check if user is muted
    if (is_user_muted($user['id'])) {
        return false;
    }
    
    // Check if user is banned
    if ($user['banned_until'] && strtotime($user['banned_until']) > time()) {
        return false;
    }
    
    return BroChatRoles::has_permission('chat');
}

/**
 * Get user's punk rock profile
 */
function get_user_punk_profile($user_id) {
    $user = get_user_by_id($user_id);
    if (!$user) {
        return null;
    }
    
    $activity = get_user_activity($user_id);
    
    return [
        'user' => $user,
        'role_display' => BroChatRoles::get_role_display_name($user['role']),
        'activity' => $activity,
        'is_muted' => is_user_muted($user_id),
        'is_banned' => $user['banned_until'] && strtotime($user['banned_until']) > time(),
        'member_since' => date('M Y', strtotime($user['created_at']))
    ];
}

/**
 * Get moderation actions for user
 */
function get_user_moderation_history($user_id, $limit = 10) {
    return db_fetch_all(
        'SELECT ml.*, m.username as moderator_username
         FROM moderation_log ml
         JOIN users m ON ml.moderator_id = m.id
         WHERE ml.target_user_id = ?
         ORDER BY ml.created_at DESC
         LIMIT ?',
        [$user_id, $limit]
    );
}

/**
 * Initialize authentication (check remember tokens, etc.)
 */
function auth_init() {
    // Check remember me token if not logged in
    if (!is_logged_in()) {
        BroChatAuth::check_remember_token();
    }
    
    // Update online status if logged in
    if (is_logged_in()) {
        mark_user_online();
    }
}
?>
