<?php
/**
 * BroChat Session Management
 * Handles sessions, flash messages, online presence, and community features
 */

// Prevent direct access
if (!defined('BROCHAT_LOADED')) {
    die('Direct access not permitted');
}

// =============================================================================
// SESSION INITIALIZATION & CONFIGURATION
// =============================================================================

class BroChatSession {
    private static $started = false;
    private static $config = [
        'name' => 'BROCHAT_SESSION',
        'lifetime' => 86400, // 24 hours
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax' // Changed from Strict to allow some cross-site functionality
    ];
    
    /**
     * Initialize BroChat session with punk rock community features
     */
    public static function init($config = []) {
        if (self::$started) {
            return;
        }
        
        self::$config = array_merge(self::$config, $config);
        
        // Auto-detect HTTPS
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        
        self::$config['secure'] = $is_https;
        
        // Configure PHP session settings
        ini_set('session.name', self::$config['name']);
        ini_set('session.gc_maxlifetime', self::$config['lifetime']);
        ini_set('session.cookie_lifetime', self::$config['lifetime']);
        ini_set('session.cookie_path', self::$config['path']);
        ini_set('session.cookie_domain', self::$config['domain']);
        ini_set('session.cookie_secure', self::$config['secure']);
        ini_set('session.cookie_httponly', self::$config['httponly']);
        ini_set('session.cookie_samesite', self::$config['samesite']);
        
        // Security settings
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_trans_sid', 0);
        ini_set('session.use_only_cookies', 1);
        
        session_start();
        self::$started = true;
        
        // Initialize session data
        self::init_session_data();
        
        // Security validation
        self::validate_session();
        
        // Regenerate session ID periodically
        self::regenerate_if_needed();
        
        // Clean up old data
        self::cleanup_old_data();
    }
    
    /**
     * Initialize session data structures
     */
    private static function init_session_data() {
        if (!isset($_SESSION['_brochat_init'])) {
            $_SESSION['_brochat_init'] = time();
            $_SESSION['_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['_created'] = time();
        }
        
        $_SESSION['_last_activity'] = time();
        
        // Initialize data containers
        if (!isset($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }
        
        if (!isset($_SESSION['_drafts'])) {
            $_SESSION['_drafts'] = [];
        }
        
        if (!isset($_SESSION['_preferences'])) {
            $_SESSION['_preferences'] = [];
        }
    }
    
    /**
     * Validate session for security
     */
    private static function validate_session() {
        // Check user agent consistency (optional, can cause issues with some users)
        if (isset($_SESSION['_user_agent'])) {
            $current_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if ($_SESSION['_user_agent'] !== $current_agent) {
                error_log('Session user agent mismatch: ' . session_id());
                // Don't destroy session automatically as mobile users switch networks
            }
        }
        
        // Check session timeout
        if (isset($_SESSION['_last_activity'])) {
            $inactive_time = time() - $_SESSION['_last_activity'];
            if ($inactive_time > self::$config['lifetime']) {
                self::destroy();
                return;
            }
        }
    }
    
    /**
     * Regenerate session ID periodically for security
     */
    private static function regenerate_if_needed() {
        $regenerate_interval = 1800; // 30 minutes
        
        if (!isset($_SESSION['_regenerated'])) {
            $_SESSION['_regenerated'] = time();
        } elseif (time() - $_SESSION['_regenerated'] > $regenerate_interval) {
            session_regenerate_id(true);
            $_SESSION['_regenerated'] = time();
        }
    }
    
    /**
     * Clean up old session data
     */
    private static function cleanup_old_data() {
        // Clean up old flash messages (shouldn't happen but safety net)
        if (isset($_SESSION['_flash_cleanup']) && 
            time() - $_SESSION['_flash_cleanup'] > 3600) {
            $_SESSION['_flash'] = [];
            $_SESSION['_flash_cleanup'] = time();
        }
        
        // Clean up old drafts (older than 24 hours)
        if (isset($_SESSION['_drafts'])) {
            foreach ($_SESSION['_drafts'] as $key => $draft) {
                if (isset($draft['timestamp']) && 
                    time() - $draft['timestamp'] > 86400) {
                    unset($_SESSION['_drafts'][$key]);
                }
            }
        }
    }
    
    /**
     * Destroy session completely
     */
    public static function destroy() {
        $_SESSION = [];
        
        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(
                session_name(), 
                '', 
                time() - 3600,
                self::$config['path'],
                self::$config['domain'],
                self::$config['secure'],
                self::$config['httponly']
            );
        }
        
        session_destroy();
        self::$started = false;
    }
}

// =============================================================================
// FLASH MESSAGES (Punk Rock Style)
// =============================================================================

/**
 * Set flash message with punk rock attitude
 */
function flash_set($type, $message) {
    if (!isset($_SESSION['_flash'])) {
        $_SESSION['_flash'] = [];
    }
    
    if (!isset($_SESSION['_flash'][$type])) {
        $_SESSION['_flash'][$type] = [];
    }
    
    $_SESSION['_flash'][$type][] = $message;
}

/**
 * Get and consume flash messages
 */
function flash_get($type = null) {
    if (!isset($_SESSION['_flash'])) {
        return $type ? [] : [];
    }
    
    if ($type) {
        $messages = $_SESSION['_flash'][$type] ?? [];
        unset($_SESSION['_flash'][$type]);
        return $messages;
    }
    
    $all_messages = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $all_messages;
}

/**
 * Check if flash messages exist
 */
function flash_has($type = null) {
    if ($type) {
        return isset($_SESSION['_flash'][$type]) && !empty($_SESSION['_flash'][$type]);
    }
    return isset($_SESSION['_flash']) && !empty($_SESSION['_flash']);
}

/**
 * Peek at flash messages without consuming
 */
function flash_peek($type = null) {
    if ($type) {
        return $_SESSION['_flash'][$type] ?? [];
    }
    return $_SESSION['_flash'] ?? [];
}

// Punk rock themed flash message helpers
function flash_success($message) {
    flash_set('success', $message);
}

function flash_error($message) {
    flash_set('error', $message);
}

function flash_warning($message) {
    flash_set('warning', $message);
}

function flash_info($message) {
    flash_set('info', $message);
}

function flash_punk($message) {
    flash_set('punk', $message);
}

function flash_rock($message) {
    flash_set('rock', $message);
}

// =============================================================================
// DRAFT MANAGEMENT (For Blog Posts and Chat)
// =============================================================================

/**
 * Save draft content
 */
function draft_save($context, $content, $metadata = []) {
    if (!isset($_SESSION['_drafts'])) {
        $_SESSION['_drafts'] = [];
    }
    
    $_SESSION['_drafts'][$context] = [
        'content' => $content,
        'metadata' => $metadata,
        'timestamp' => time(),
        'updated' => date('Y-m-d H:i:s')
    ];
}

/**
 * Get draft content
 */
function draft_get($context) {
    return $_SESSION['_drafts'][$context] ?? null;
}

/**
 * Check if draft exists
 */
function draft_exists($context) {
    return isset($_SESSION['_drafts'][$context]);
}

/**
 * Clear draft
 */
function draft_clear($context) {
    unset($_SESSION['_drafts'][$context]);
}

/**
 * Get all drafts
 */
function draft_get_all() {
    return $_SESSION['_drafts'] ?? [];
}

/**
 * Clear old drafts
 */
function draft_cleanup($max_age_hours = 24) {
    if (!isset($_SESSION['_drafts'])) {
        return 0;
    }
    
    $cutoff = time() - ($max_age_hours * 3600);
    $cleaned = 0;
    
    foreach ($_SESSION['_drafts'] as $context => $draft) {
        if (isset($draft['timestamp']) && $draft['timestamp'] < $cutoff) {
            unset($_SESSION['_drafts'][$context]);
            $cleaned++;
        }
    }
    
    return $cleaned;
}

// =============================================================================
// USER PREFERENCES & SETTINGS
// =============================================================================

/**
 * Set user preference
 */
function pref_set($key, $value) {
    if (!isset($_SESSION['_preferences'])) {
        $_SESSION['_preferences'] = [];
    }
    
    $_SESSION['_preferences'][$key] = $value;
}

/**
 * Get user preference
 */
function pref_get($key, $default = null) {
    return $_SESSION['_preferences'][$key] ?? $default;
}

/**
 * Check if preference exists
 */
function pref_has($key) {
    return isset($_SESSION['_preferences'][$key]);
}

/**
 * Remove preference
 */
function pref_remove($key) {
    unset($_SESSION['_preferences'][$key]);
}

/**
 * Get all preferences
 */
function pref_get_all() {
    return $_SESSION['_preferences'] ?? [];
}

// Common BroChat preferences
function set_chat_color_theme($theme) {
    pref_set('chat_color_theme', $theme);
}

function get_chat_color_theme() {
    return pref_get('chat_color_theme', 'punk_classic');
}

function set_stream_volume($volume) {
    pref_set('stream_volume', max(0, min(100, intval($volume))));
}

function get_stream_volume() {
    return pref_get('stream_volume', 75);
}

function set_notifications_enabled($enabled) {
    pref_set('notifications_enabled', (bool)$enabled);
}

function get_notifications_enabled() {
    return pref_get('notifications_enabled', true);
}

// =============================================================================
// ONLINE PRESENCE & ACTIVITY TRACKING
// =============================================================================

/**
 * Mark user as online
 */
function mark_user_online($user_id = null) {
    if (!$user_id && is_logged_in()) {
        $user_id = current_user()['id'];
    }
    
    if (!$user_id) {
        return false;
    }
    
    // Update or insert presence record
    $existing = db_fetch(
        'SELECT id FROM user_presence WHERE user_id = ? AND session_id = ?',
        [$user_id, session_id()]
    );
    
    if ($existing) {
        db_update('user_presence', [
            'last_seen' => date('Y-m-d H:i:s'),
            'status' => 'online',
            'ip_address' => get_client_ip()
        ], 'id = ?', [$existing['id']]);
    } else {
        db_insert('user_presence', [
            'user_id' => $user_id,
            'session_id' => session_id(),
            'status' => 'online',
            'last_seen' => date('Y-m-d H:i:s'),
            'ip_address' => get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    
    return true;
}

/**
 * Mark user as away
 */
function mark_user_away($user_id = null) {
    if (!$user_id && is_logged_in()) {
        $user_id = current_user()['id'];
    }
    
    if (!$user_id) {
        return false;
    }
    
    db_update('user_presence', [
        'status' => 'away',
        'last_seen' => date('Y-m-d H:i:s')
    ], 'user_id = ? AND session_id = ?', [$user_id, session_id()]);
    
    return true;
}

/**
 * Get online users
 */
function get_online_users($include_away = true) {
    $statuses = ['online'];
    if ($include_away) {
        $statuses[] = 'away';
    }
    
    $status_placeholders = str_repeat('?,', count($statuses) - 1) . '?';
    $params = array_merge($statuses, [date('Y-m-d H:i:s', strtotime('-5 minutes'))]);
    
    return db_fetch_all(
        "SELECT DISTINCT u.id, u.username, u.display_name, u.role, 
                up.status, up.last_seen
         FROM user_presence up
         JOIN users u ON up.user_id = u.id
         WHERE up.status IN ($status_placeholders) 
         AND up.last_seen > ?
         ORDER BY up.last_seen DESC",
        $params
    );
}

/**
 * Get user's online status
 */
function get_user_online_status($user_id) {
    $presence = db_fetch(
        'SELECT status, last_seen FROM user_presence 
         WHERE user_id = ? AND last_seen > ?
         ORDER BY last_seen DESC LIMIT 1',
        [$user_id, date('Y-m-d H:i:s', strtotime('-10 minutes'))]
    );
    
    if (!$presence) {
        return 'offline';
    }
    
    // If last seen more than 5 minutes ago, consider away
    if (strtotime($presence['last_seen']) < strtotime('-5 minutes')) {
        return 'away';
    }
    
    return $presence['status'];
}

/**
 * Clean up old presence records
 */
function cleanup_old_presence($hours_old = 24) {
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$hours_old} hours"));
    return db_delete('user_presence', 'last_seen < ?', [$cutoff]);
}

// =============================================================================
// TYPING INDICATORS (Chat Feature)
// =============================================================================

/**
 * Set typing status
 */
function set_typing_status($is_typing = true, $context = 'chat') {
    if (!is_logged_in()) {
        return false;
    }
    
    $user_id = current_user()['id'];
    $key = "typing_{$context}_{$user_id}";
    
    if ($is_typing) {
        pref_set($key, time());
        
        // Also store in database for other users to see
        db_query(
            'INSERT OR REPLACE INTO typing_indicators (user_id, context, started_at) VALUES (?, ?, ?)',
            [$user_id, $context, date('Y-m-d H:i:s')]
        );
    } else {
        pref_remove($key);
        db_delete('typing_indicators', 'user_id = ? AND context = ?', [$user_id, $context]);
    }
    
    return true;
}

/**
 * Get users who are currently typing
 */
function get_typing_users($context = 'chat') {
    // Consider typing active for 10 seconds
    $cutoff = date('Y-m-d H:i:s', strtotime('-10 seconds'));
    
    return db_fetch_all(
        'SELECT u.username, ti.started_at 
         FROM typing_indicators ti
         JOIN users u ON ti.user_id = u.id
         WHERE ti.context = ? AND ti.started_at > ?
         ORDER BY ti.started_at ASC',
        [$context, $cutoff]
    );
}

/**
 * Clean up old typing indicators
 */
function cleanup_typing_indicators() {
    $cutoff = date('Y-m-d H:i:s', strtotime('-30 seconds'));
    return db_delete('typing_indicators', 'started_at < ?', [$cutoff]);
}

// =============================================================================
// CSRF PROTECTION
// =============================================================================

/**
 * Generate CSRF token
 */
function csrf_token() {
    if (!session_has('_csrf_token')) {
        session_set('_csrf_token', bin2hex(random_bytes(32)));
    }
    return session_get('_csrf_token');
}

/**
 * Generate CSRF form field
 */
function csrf_field() {
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

/**
 * Verify CSRF token
 */
function csrf_verify($token = null) {
    if ($token === null) {
        $token = $_POST['_csrf_token'] ?? $_GET['_csrf_token'] ?? '';
    }
    
    $session_token = session_get('_csrf_token');
    
    if (!$session_token || !$token) {
        return false;
    }
    
    return hash_equals($session_token, $token);
}

/**
 * Require valid CSRF token
 */
function require_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        http_response_code(403);
        flash_error('Security token verification failed. Please try again.');
        die('CSRF token verification failed');
    }
}

// =============================================================================
// COMMUNITY FEATURES
// =============================================================================

/**
 * Track user's punk rock journey
 */
function track_punk_milestone($milestone, $user_id = null) {
    if (!$user_id && is_logged_in()) {
        $user_id = current_user()['id'];
    }
    
    if (!$user_id) {
        return false;
    }
    
    // Check if milestone already achieved
    $existing = db_fetch(
        'SELECT id FROM user_milestones WHERE user_id = ? AND milestone = ?',
        [$user_id, $milestone]
    );
    
    if ($existing) {
        return false;
    }
    
    // Record milestone
    $milestone_id = db_insert('user_milestones', [
        'user_id' => $user_id,
        'milestone' => $milestone,
        'achieved_at' => date('Y-m-d H:i:s')
    ]);
    
    if ($milestone_id) {
        flash_punk("🤘 Milestone achieved: $milestone!");
    }
    
    return $milestone_id;
}

/**
 * Get user's achieved milestones
 */
function get_user_milestones($user_id) {
    return db_fetch_all(
        'SELECT milestone, achieved_at FROM user_milestones 
         WHERE user_id = ? ORDER BY achieved_at DESC',
        [$user_id]
    );
}

/**
 * Set favorite punk band
 */
function set_favorite_band($band_name) {
    if (!is_logged_in()) {
        return false;
    }
    
    pref_set('favorite_punk_band', $band_name);
    return true;
}

/**
 * Get favorite punk band
 */
function get_favorite_band() {
    return pref_get('favorite_punk_band', 'The Ramones');
}

/**
 * Track listening time to stream
 */
function track_listening_time($seconds) {
    if (!is_logged_in()) {
        return false;
    }
    
    $user_id = current_user()['id'];
    $current_time = pref_get('total_listening_time', 0);
    pref_set('total_listening_time', $current_time + $seconds);
    
    // Check for listening milestones
    $total_hours = floor(($current_time + $seconds) / 3600);
    $previous_hours = floor($current_time / 3600);
    
    if ($total_hours > $previous_hours) {
        if (in_array($total_hours, [1, 10, 50, 100, 500])) {
            track_punk_milestone("Listened for {$total_hours} hours", $user_id);
        }
    }
    
    return true;
}

/**
 * Get user's total listening time
 */
function get_listening_time() {
    return pref_get('total_listening_time', 0);
}

// =============================================================================
// SESSION DATA HELPERS
// =============================================================================

/**
 * Get session data
 */
function session_get($key, $default = null) {
    return $_SESSION[$key] ?? $default;
}

/**
 * Set session data
 */
function session_set($key, $value) {
    $_SESSION[$key] = $value;
}

/**
 * Check if session key exists
 */
function session_has($key) {
    return isset($_SESSION[$key]);
}

/**
 * Remove session data
 */
function session_remove($key) {
    unset($_SESSION[$key]);
}

/**
 * Clear all session data (except system keys)
 */
function session_clear() {
    $system_keys = [
        '_brochat_init', '_user_agent', '_created', '_last_activity', 
        '_regenerated', '_csrf_token', 'user_id', 'username', 'role'
    ];
    
    foreach ($_SESSION as $key => $value) {
        if (!in_array($key, $system_keys) && !str_starts_with($key, '_')) {
            unset($_SESSION[$key]);
        }
    }
}

/**
 * Get session ID
 */
function session_id_get() {
    return session_id();
}

/**
 * Regenerate session ID
 */
function session_regenerate() {
    session_regenerate_id(true);
    $_SESSION['_regenerated'] = time();
}

// =============================================================================
// CHAT ROOM FEATURES
// =============================================================================

/**
 * Join chat room (for future multi-room support)
 */
function join_chat_room($room_name = 'main') {
    if (!is_logged_in()) {
        return false;
    }
    
    $current_rooms = pref_get('joined_rooms', []);
    if (!in_array($room_name, $current_rooms)) {
        $current_rooms[] = $room_name;
        pref_set('joined_rooms', $current_rooms);
    }
    
    pref_set('current_room', $room_name);
    return true;
}

/**
 * Leave chat room
 */
function leave_chat_room($room_name) {
    $current_rooms = pref_get('joined_rooms', []);
    $current_rooms = array_filter($current_rooms, fn($room) => $room !== $room_name);
    pref_set('joined_rooms', array_values($current_rooms));
    
    if (pref_get('current_room') === $room_name) {
        pref_set('current_room', 'main');
    }
    
    return true;
}

/**
 * Get current chat room
 */
function get_current_room() {
    return pref_get('current_room', 'main');
}

/**
 * Get joined chat rooms
 */
function get_joined_rooms() {
    return pref_get('joined_rooms', ['main']);
}

// =============================================================================
// ANALYTICS & ACTIVITY TRACKING
// =============================================================================

/**
 * Track page view
 */
function track_page_view($page, $user_id = null) {
    if (!$user_id && is_logged_in()) {
        $user_id = current_user()['id'];
    }
    
    db_insert('page_views', [
        'user_id' => $user_id,
        'page' => $page,
        'ip_address' => get_client_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'session_id' => session_id(),
        'viewed_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Track user action
 */
function track_user_action($action, $details = null, $user_id = null) {
    if (!$user_id && is_logged_in()) {
        $user_id = current_user()['id'];
    }
    
    if (!$user_id) {
        return false;
    }
    
    db_insert('user_actions', [
        'user_id' => $user_id,
        'action' => $action,
        'details' => $details ? json_encode($details) : null,
        'session_id' => session_id(),
        'ip_address' => get_client_ip(),
        'performed_at' => date('Y-m-d H:i:s')
    ]);
    
    return true;
}

/**
 * Get session analytics
 */
function get_session_analytics() {
    return [
        'session_id' => session_id(),
        'started' => date('Y-m-d H:i:s', session_get('_created', time())),
        'last_activity' => date('Y-m-d H:i:s', session_get('_last_activity', time())),
        'page_views' => count_session_page_views(),
        'online_time' => time() - session_get('_created', time()),
        'preferences' => count(pref_get_all()),
        'drafts' => count(draft_get_all())
    ];
}

/**
 * Count page views in current session
 */
function count_session_page_views() {
    $result = db_fetch(
        'SELECT COUNT(*) as count FROM page_views WHERE session_id = ?',
        [session_id()]
    );
    return $result['count'] ?? 0;
}

// =============================================================================
// INITIALIZATION FUNCTIONS
// =============================================================================

/**
 * Initialize BroChat session system
 */
function session_init($config = []) {
    BroChatSession::init($config);
    
    // Initialize authentication
    auth_init();
    
    // Clean up old data periodically
    if (rand(1, 100) <= 5) { // 5% chance
        cleanup_typing_indicators();
        cleanup_old_presence(24);
        draft_cleanup(24);
    }
}

/**
 * Get session information for debugging
 */
function get_session_info() {
    return [
        'id' => session_id(),
        'name' => session_name(),
        'started' => BroChatSession::$started ?? false,
        'data_size' => strlen(serialize($_SESSION)),
        'keys' => array_keys($_SESSION),
        'config' => BroChatSession::$config ?? []
    ];
}

/**
 * Session maintenance (run periodically)
 */
function session_maintenance() {
    $tasks = [];
    
    // Clean up old typing indicators
    $typing_cleaned = cleanup_typing_indicators();
    $tasks[] = "Cleaned $typing_cleaned typing indicators";
    
    // Clean up old presence records
    $presence_cleaned = cleanup_old_presence(24);
    $tasks[] = "Cleaned $presence_cleaned presence records";
    
    // Clean up old page views
    $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
    $pageviews_cleaned = db_delete('page_views', 'viewed_at < ?', [$cutoff]);
    $tasks[] = "Cleaned $pageviews_cleaned old page views";
    
    return $tasks;
}
?>
