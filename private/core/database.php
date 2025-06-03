<?php
/**
 * BroChat Database Functions
 * Application-specific database operations for blog, chat, and audio stream
 */

// Prevent direct access
if (!defined('BROCHAT_LOADED')) {
    die('Direct access not permitted');
}

// =============================================================================
// DATABASE CONNECTION & SETUP
// =============================================================================

class BroChatDB {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $this->pdo = new PDO('sqlite:' . BROCHAT_DB);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // SQLite optimizations
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('PRAGMA synchronous = NORMAL');
            $this->pdo->exec('PRAGMA cache_size = 10000');
            $this->pdo->exec('PRAGMA temp_store = MEMORY');
            
        } catch (PDOException $e) {
            error_log('BroChat database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

function get_db() {
    return BroChatDB::getInstance()->getConnection();
}

// =============================================================================
// BLOG POST FUNCTIONS
// =============================================================================

/**
 * Create a new blog post
 */
function create_blog_post($user_id, $content, $photos = []) {
    return db_transaction(function() use ($user_id, $content, $photos) {
        // Generate slug
        $slug = generate_blog_slug($content);
        
        // Ensure unique slug
        $base_slug = $slug;
        $counter = 1;
        while (get_blog_post_by_slug($slug)) {
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }
        
        // Extract hashtags and mentions
        $hashtags = extract_hashtags($content);
        $mentions = extract_mentions($content);
        
        // Create post
        $post_id = db_insert('blog_posts', [
            'user_id' => $user_id,
            'content' => $content,
            'slug' => $slug,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        // Store hashtags
        foreach ($hashtags as $tag) {
            // Create tag if it doesn't exist
            $tag_id = get_or_create_tag($tag);
            
            // Link post to tag
            db_insert('blog_post_tags', [
                'post_id' => $post_id,
                'tag_id' => $tag_id
            ]);
        }
        
        // Store mentions
        foreach ($mentions as $username) {
            $mentioned_user = get_user_by_username($username);
            if ($mentioned_user) {
                db_insert('blog_mentions', [
                    'post_id' => $post_id,
                    'mentioned_user_id' => $mentioned_user['id'],
                    'mentioning_user_id' => $user_id
                ]);
            }
        }
        
        // Process photos if provided
        if (!empty($photos)) {
            process_blog_photos($photos, $post_id);
        }
        
        return $post_id;
    });
}

/**
 * Get blog post by ID
 */
function get_blog_post($post_id) {
    $post = db_fetch(
        'SELECT bp.*, u.username, u.display_name 
         FROM blog_posts bp 
         JOIN users u ON bp.user_id = u.id 
         WHERE bp.id = ?',
        [$post_id]
    );
    
    if ($post) {
        $post['photos'] = get_blog_post_photos($post_id);
        $post['tags'] = get_blog_post_tags($post_id);
        $post['parsed_content'] = parse_blog_markdown($post['content']);
    }
    
    return $post;
}

/**
 * Get blog post by slug
 */
function get_blog_post_by_slug($slug) {
    $post = db_fetch(
        'SELECT bp.*, u.username, u.display_name 
         FROM blog_posts bp 
         JOIN users u ON bp.user_id = u.id 
         WHERE bp.slug = ?',
        [$slug]
    );
    
    if ($post) {
        $post['photos'] = get_blog_post_photos($post['id']);
        $post['tags'] = get_blog_post_tags($post['id']);
        $post['parsed_content'] = parse_blog_markdown($post['content']);
    }
    
    return $post;
}

/**
 * Get recent blog posts
 */
function get_recent_blog_posts($limit = 20, $offset = 0) {
    $posts = db_fetch_all(
        'SELECT bp.*, u.username, u.display_name 
         FROM blog_posts bp 
         JOIN users u ON bp.user_id = u.id 
         ORDER BY bp.created_at DESC 
         LIMIT ? OFFSET ?',
        [$limit, $offset]
    );
    
    // Add photos and tags to each post
    foreach ($posts as &$post) {
        $post['photos'] = get_blog_post_photos($post['id']);
        $post['tags'] = get_blog_post_tags($post['id']);
        $post['parsed_content'] = parse_blog_markdown($post['content']);
    }
    
    return $posts;
}

/**
 * Get blog posts by tag
 */
function get_blog_posts_by_tag($tag, $limit = 20, $offset = 0) {
    $posts = db_fetch_all(
        'SELECT bp.*, u.username, u.display_name 
         FROM blog_posts bp 
         JOIN users u ON bp.user_id = u.id
         JOIN blog_post_tags bpt ON bp.id = bpt.post_id
         JOIN tags t ON bpt.tag_id = t.id
         WHERE t.name = ?
         ORDER BY bp.created_at DESC 
         LIMIT ? OFFSET ?',
        [$tag, $limit, $offset]
    );
    
    foreach ($posts as &$post) {
        $post['photos'] = get_blog_post_photos($post['id']);
        $post['tags'] = get_blog_post_tags($post['id']);
        $post['parsed_content'] = parse_blog_markdown($post['content']);
    }
    
    return $posts;
}

/**
 * Get blog posts by user
 */
function get_blog_posts_by_user($user_id, $limit = 20, $offset = 0) {
    $posts = db_fetch_all(
        'SELECT bp.*, u.username, u.display_name 
         FROM blog_posts bp 
         JOIN users u ON bp.user_id = u.id 
         WHERE bp.user_id = ?
         ORDER BY bp.created_at DESC 
         LIMIT ? OFFSET ?',
        [$user_id, $limit, $offset]
    );
    
    foreach ($posts as &$post) {
        $post['photos'] = get_blog_post_photos($post['id']);
        $post['tags'] = get_blog_post_tags($post['id']);
        $post['parsed_content'] = parse_blog_markdown($post['content']);
    }
    
    return $posts;
}

/**
 * Get photos for a blog post
 */
function get_blog_post_photos($post_id) {
    return db_fetch_all(
        'SELECT * FROM blog_photos 
         WHERE post_id = ? 
         ORDER BY position ASC',
        [$post_id]
    );
}

/**
 * Get tags for a blog post
 */
function get_blog_post_tags($post_id) {
    return db_fetch_all(
        'SELECT t.* FROM tags t
         JOIN blog_post_tags bpt ON t.id = bpt.tag_id
         WHERE bpt.post_id = ?
         ORDER BY t.name ASC',
        [$post_id]
    );
}

/**
 * Get or create a tag
 */
function get_or_create_tag($tag_name) {
    $tag = db_fetch('SELECT id FROM tags WHERE name = ?', [$tag_name]);
    
    if ($tag) {
        return $tag['id'];
    }
    
    return db_insert('tags', [
        'name' => $tag_name,
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get popular tags
 */
function get_popular_tags($limit = 20) {
    return db_fetch_all(
        'SELECT t.name, COUNT(bpt.post_id) as post_count
         FROM tags t
         JOIN blog_post_tags bpt ON t.id = bpt.tag_id
         GROUP BY t.id, t.name
         ORDER BY post_count DESC, t.name ASC
         LIMIT ?',
        [$limit]
    );
}

/**
 * Delete blog post
 */
function delete_blog_post($post_id, $user_id) {
    return db_transaction(function() use ($post_id, $user_id) {
        // Verify ownership
        $post = db_fetch('SELECT user_id FROM blog_posts WHERE id = ?', [$post_id]);
        if (!$post || $post['user_id'] != $user_id) {
            return false;
        }
        
        // Get photos to delete files
        $photos = get_blog_post_photos($post_id);
        
        // Delete database records
        db_delete('blog_photos', 'post_id = ?', [$post_id]);
        db_delete('blog_post_tags', 'post_id = ?', [$post_id]);
        db_delete('blog_mentions', 'post_id = ?', [$post_id]);
        db_delete('blog_posts', 'id = ?', [$post_id]);
        
        // Delete physical photo files
        foreach ($photos as $photo) {
            $photo_path = BROCHAT_ROOT . '/uploads/photos/' . $photo['filename'];
            $preview_path = BROCHAT_ROOT . '/uploads/previews/' . $photo['preview_filename'];
            
            if (file_exists($photo_path)) {
                unlink($photo_path);
            }
            if (file_exists($preview_path)) {
                unlink($preview_path);
            }
        }
        
        return true;
    });
}

// =============================================================================
// CHAT FUNCTIONS
// =============================================================================

/**
 * Create a new chat message
 */
function create_chat_message($user_id, $message, $message_type = 'message') {
    // Format the message
    $username = get_user_by_id($user_id)['username'];
    $formatted_message = format_chat_message($message, $username);
    
    $message_id = db_insert('chat_messages', [
        'user_id' => $user_id,
        'message' => $message,
        'formatted_message' => $formatted_message,
        'message_type' => $message_type,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    return $message_id;
}

/**
 * Get recent chat messages
 */
function get_recent_chat_messages($limit = 50) {
    return db_fetch_all(
        'SELECT cm.*, u.username, u.display_name
         FROM chat_messages cm
         JOIN users u ON cm.user_id = u.id
         WHERE cm.deleted_at IS NULL
         ORDER BY cm.timestamp DESC
         LIMIT ?',
        [$limit]
    );
}

/**
 * Get chat messages since timestamp
 */
function get_chat_messages_since($timestamp, $limit = 100) {
    return db_fetch_all(
        'SELECT cm.*, u.username, u.display_name
         FROM chat_messages cm
         JOIN users u ON cm.user_id = u.id
         WHERE cm.timestamp > ? AND cm.deleted_at IS NULL
         ORDER BY cm.timestamp ASC
         LIMIT ?',
        [$timestamp, $limit]
    );
}

/**
 * Delete chat message (soft delete)
 */
function delete_chat_message($message_id, $user_id) {
    // Check if user owns the message or is admin/moderator
    $message = db_fetch('SELECT user_id FROM chat_messages WHERE id = ?', [$message_id]);
    if (!$message) {
        return false;
    }
    
    $current_user = get_user_by_id($user_id);
    if ($message['user_id'] != $user_id && !in_array($current_user['role'], ['admin', 'moderator'])) {
        return false;
    }
    
    return db_update('chat_messages', 
        ['deleted_at' => date('Y-m-d H:i:s')],
        'id = ?',
        [$message_id]
    ) > 0;
}

/**
 * Get chat statistics
 */
function get_chat_stats() {
    $total_messages = db_fetch('SELECT COUNT(*) as count FROM chat_messages WHERE deleted_at IS NULL');
    $today_messages = db_fetch(
        'SELECT COUNT(*) as count FROM chat_messages 
         WHERE DATE(timestamp) = DATE("now") AND deleted_at IS NULL'
    );
    $active_users = db_fetch(
        'SELECT COUNT(DISTINCT user_id) as count FROM chat_messages 
         WHERE timestamp > datetime("now", "-1 hour") AND deleted_at IS NULL'
    );
    
    return [
        'total_messages' => $total_messages['count'] ?? 0,
        'today_messages' => $today_messages['count'] ?? 0,
        'active_users_hour' => $active_users['count'] ?? 0
    ];
}

// =============================================================================
// USER MANAGEMENT FUNCTIONS
// =============================================================================

/**
 * Create new user account
 */
function create_user($username, $email, $password, $display_name = null) {
    // Validate inputs
    if (get_user_by_username($username)) {
        throw new Exception('Username already exists');
    }
    
    if (get_user_by_email($email)) {
        throw new Exception('Email already exists');
    }
    
    if (!is_password_strong($password)) {
        throw new Exception('Password does not meet requirements');
    }
    
    $user_id = db_insert('users', [
        'username' => $username,
        'email' => $email,
        'password_hash' => hash_password($password),
        'display_name' => $display_name ?: $username,
        'role' => 'user',
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    return $user_id;
}

/**
 * Get user by ID
 */
function get_user_by_id($user_id) {
    return db_fetch('SELECT * FROM users WHERE id = ?', [$user_id]);
}

/**
 * Get user by username
 */
function get_user_by_username($username) {
    return db_fetch('SELECT * FROM users WHERE username = ?', [$username]);
}

/**
 * Get user by email
 */
function get_user_by_email($email) {
    return db_fetch('SELECT * FROM users WHERE email = ?', [$email]);
}

/**
 * Update user profile
 */
function update_user_profile($user_id, $data) {
    $allowed_fields = ['display_name', 'email', 'bio'];
    $update_data = array_intersect_key($data, array_flip($allowed_fields));
    $update_data['updated_at'] = date('Y-m-d H:i:s');
    
    return db_update('users', $update_data, 'id = ?', [$user_id]) > 0;
}

/**
 * Update user password
 */
function update_user_password($user_id, $old_password, $new_password) {
    $user = get_user_by_id($user_id);
    if (!$user || !password_verify($old_password, $user['password_hash'])) {
        return false;
    }
    
    if (!is_password_strong($new_password)) {
        throw new Exception('New password does not meet requirements');
    }
    
    return db_update('users', 
        ['password_hash' => hash_password($new_password), 'updated_at' => date('Y-m-d H:i:s')],
        'id = ?',
        [$user_id]
    ) > 0;
}

/**
 * Get user activity summary
 */
function get_user_activity($user_id) {
    $blog_posts = db_fetch('SELECT COUNT(*) as count FROM blog_posts WHERE user_id = ?', [$user_id]);
    $chat_messages = db_fetch('SELECT COUNT(*) as count FROM chat_messages WHERE user_id = ? AND deleted_at IS NULL', [$user_id]);
    $mentions = db_fetch('SELECT COUNT(*) as count FROM blog_mentions WHERE mentioned_user_id = ?', [$user_id]);
    
    return [
        'blog_posts' => $blog_posts['count'] ?? 0,
        'chat_messages' => $chat_messages['count'] ?? 0,
        'mentions' => $mentions['count'] ?? 0
    ];
}

// =============================================================================
// AUDIO STREAM FUNCTIONS
// =============================================================================

/**
 * Log stream connection
 */
function log_stream_connection($session_id = null, $user_id = null) {
    if (!$session_id) {
        $session_id = session_id();
    }
    
    return db_insert('stream_listeners', [
        'session_id' => $session_id,
        'user_id' => $user_id,
        'ip_address' => get_client_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'connected_at' => date('Y-m-d H:i:s'),
        'last_seen' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Update stream listener heartbeat
 */
function update_stream_heartbeat($session_id = null) {
    if (!$session_id) {
        $session_id = session_id();
    }
    
    return db_update('stream_listeners',
        ['last_seen' => date('Y-m-d H:i:s')],
        'session_id = ?',
        [$session_id]
    ) > 0;
}

/**
 * Get current stream listeners
 */
function get_current_stream_listeners() {
    // Consider listeners active if they've been seen in the last 2 minutes
    $cutoff = date('Y-m-d H:i:s', strtotime('-2 minutes'));
    
    return db_fetch_all(
        'SELECT sl.*, u.username 
         FROM stream_listeners sl
         LEFT JOIN users u ON sl.user_id = u.id
         WHERE sl.last_seen > ?
         ORDER BY sl.connected_at DESC',
        [$cutoff]
    );
}

/**
 * Get stream statistics
 */
function get_stream_stats() {
    $current_listeners = count(get_current_stream_listeners());
    
    $peak_today = db_fetch(
        'SELECT COUNT(DISTINCT session_id) as count 
         FROM stream_listeners 
         WHERE DATE(connected_at) = DATE("now")'
    );
    
    $total_connections = db_fetch('SELECT COUNT(*) as count FROM stream_listeners');
    
    return [
        'current_listeners' => $current_listeners,
        'peak_today' => $peak_today['count'] ?? 0,
        'total_connections' => $total_connections['count'] ?? 0
    ];
}

/**
 * Clean up old stream listener records
 */
function cleanup_stream_listeners($hours_old = 24) {
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$hours_old} hours"));
    return db_delete('stream_listeners', 'last_seen < ?', [$cutoff]);
}

// =============================================================================
// SEARCH FUNCTIONS
// =============================================================================

/**
 * Search blog posts
 */
function search_blog_posts($query, $limit = 20, $offset = 0) {
    $search_terms = explode(' ', trim($query));
    $where_conditions = [];
    $params = [];
    
    foreach ($search_terms as $term) {
        $term = trim($term);
        if (!empty($term)) {
            $where_conditions[] = 'bp.content LIKE ?';
            $params[] = '%' . $term . '%';
        }
    }
    
    if (empty($where_conditions)) {
        return [];
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    $params[] = $limit;
    $params[] = $offset;
    
    $posts = db_fetch_all(
        "SELECT bp.*, u.username, u.display_name 
         FROM blog_posts bp 
         JOIN users u ON bp.user_id = u.id 
         WHERE $where_clause
         ORDER BY bp.created_at DESC 
         LIMIT ? OFFSET ?",
        $params
    );
    
    foreach ($posts as &$post) {
        $post['photos'] = get_blog_post_photos($post['id']);
        $post['tags'] = get_blog_post_tags($post['id']);
        $post['parsed_content'] = parse_blog_markdown($post['content']);
    }
    
    return $posts;
}

/**
 * Search users
 */
function search_users($query, $limit = 20) {
    return db_fetch_all(
        'SELECT id, username, display_name 
         FROM users 
         WHERE (username LIKE ? OR display_name LIKE ?) 
         AND status = "active"
         ORDER BY username ASC 
         LIMIT ?',
        ['%' . $query . '%', '%' . $query . '%', $limit]
    );
}

// =============================================================================
// ANALYTICS & REPORTING
// =============================================================================

/**
 * Get application statistics
 */
function get_app_stats() {
    $user_count = db_fetch('SELECT COUNT(*) as count FROM users WHERE status = "active"');
    $post_count = db_fetch('SELECT COUNT(*) as count FROM blog_posts');
    $message_count = db_fetch('SELECT COUNT(*) as count FROM chat_messages WHERE deleted_at IS NULL');
    $tag_count = db_fetch('SELECT COUNT(*) as count FROM tags');
    
    return [
        'users' => $user_count['count'] ?? 0,
        'blog_posts' => $post_count['count'] ?? 0,
        'chat_messages' => $message_count['count'] ?? 0,
        'tags' => $tag_count['count'] ?? 0,
        'stream_stats' => get_stream_stats()
    ];
}

/**
 * Get daily activity stats
 */
function get_daily_activity($days = 7) {
    $stats = [];
    
    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        
        $posts = db_fetch(
            'SELECT COUNT(*) as count FROM blog_posts WHERE DATE(created_at) = ?',
            [$date]
        );
        
        $messages = db_fetch(
            'SELECT COUNT(*) as count FROM chat_messages WHERE DATE(timestamp) = ? AND deleted_at IS NULL',
            [$date]
        );
        
        $listeners = db_fetch(
            'SELECT COUNT(DISTINCT session_id) as count FROM stream_listeners WHERE DATE(connected_at) = ?',
            [$date]
        );
        
        $stats[] = [
            'date' => $date,
            'blog_posts' => $posts['count'] ?? 0,
            'chat_messages' => $messages['count'] ?? 0,
            'stream_listeners' => $listeners['count'] ?? 0
        ];
    }
    
    return array_reverse($stats);
}

// =============================================================================
// UTILITY DATABASE FUNCTIONS
// =============================================================================

/**
 * Database maintenance tasks
 */
function run_database_maintenance() {
    $tasks_completed = [];
    
    // Clean up old stream listeners
    $cleaned_listeners = cleanup_stream_listeners(24);
    $tasks_completed[] = "Cleaned $cleaned_listeners old stream listener records";
    
    // Clean up old session data
    $cutoff = date('Y-m-d H:i:s', strtotime('-7 days'));
    $cleaned_sessions = db_delete('user_activity_log', 'created_at < ?', [$cutoff]);
    $tasks_completed[] = "Cleaned $cleaned_sessions old activity log entries";
    
    // Vacuum database
    get_db()->exec('VACUUM');
    $tasks_completed[] = "Database vacuum completed";
    
    // Update statistics
    get_db()->exec('ANALYZE');
    $tasks_completed[] = "Database statistics updated";
    
    return $tasks_completed;
}

/**
 * Backup database
 */
function backup_database($backup_dir = null) {
    if (!$backup_dir) {
        $backup_dir = BROCHAT_LOGS . '/backups';
    }
    
    ensure_directory($backup_dir);
    
    $backup_file = $backup_dir . '/brochat_backup_' . date('Y-m-d_H-i-s') . '.db';
    
    if (copy(BROCHAT_DB, $backup_file)) {
        return $backup_file;
    }
    
    return false;
}

/**
 * Get database size information
 */
function get_database_info() {
    $db_size = filesize(BROCHAT_DB);
    
    $table_info = db_fetch_all(
        "SELECT name, 
                (SELECT COUNT(*) FROM pragma_table_info(name)) as columns,
                (SELECT COUNT(*) FROM \" || name || \") as rows
         FROM sqlite_master 
         WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
    );
    
    return [
        'file_size' => $db_size,
        'file_size_formatted' => format_file_size($db_size),
        'tables' => $table_info
    ];
}
?>
