<?php
/**
 * BroChat Core Functions
 * Application-specific utility functions for the multi-user blog, chat, and audio stream
 */

// Prevent direct access
if (!defined('BROCHAT_LOADED')) {
    die('Direct access not permitted');
}

// =============================================================================
// BLOG POST FUNCTIONS
// =============================================================================

/**
 * Parse markdown text with BroChat-specific features (@mentions, #hashtags)
 */
function parse_blog_markdown($text) {
    // First sanitize the input
    $text = sanitize_input($text);
    
    // Limit to 1K UTF-8 characters
    if (mb_strlen($text, 'UTF-8') > 1000) {
        $text = mb_substr($text, 0, 1000, 'UTF-8') . '...';
    }
    
    // Convert basic markdown
    $text = convert_basic_markdown($text);
    
    // Parse @mentions
    $text = parse_mentions($text);
    
    // Parse #hashtags
    $text = parse_hashtags($text);
    
    return $text;
}

/**
 * Convert basic markdown to HTML
 */
function convert_basic_markdown($text) {
    // Headers (limit to h3-h6 for blog posts)
    $text = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $text);
    $text = preg_replace('/^\##### (.+)$/m', '<h5>$1</h5>', $text);
    $text = preg_replace('/^###### (.+)$/m', '<h6>$1</h6>', $text);
    
    // Bold and italic
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);
    
    // Links
    $text = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
    
    // Auto-link URLs
    $text = preg_replace('/\b(https?:\/\/[^\s<>]+)/i', '<a href="$1" target="_blank" rel="noopener">$1</a>', $text);
    
    // Line breaks
    $text = nl2br($text);
    
    return $text;
}

/**
 * Parse @mentions and link to user profiles
 */
function parse_mentions($text) {
    return preg_replace_callback(
        '/@([a-zA-Z0-9_.-]+)/',
        function($matches) {
            $username = escape_html($matches[1]);
            // Check if user exists
            $user = get_user_by_username($username);
            if ($user) {
                return '<a href="/user/' . $username . '" class="mention">@' . $username . '</a>';
            }
            return '<span class="mention-invalid">@' . $username . '</span>';
        },
        $text
    );
}

/**
 * Parse #hashtags and link to tag pages
 */
function parse_hashtags($text) {
    return preg_replace_callback(
        '/#([a-zA-Z0-9_-]+)/',
        function($matches) {
            $tag = escape_html($matches[1]);
            return '<a href="/tag/' . urlencode($tag) . '" class="hashtag">#' . $tag . '</a>';
        },
        $text
    );
}

/**
 * Extract hashtags from text
 */
function extract_hashtags($text) {
    preg_match_all('/#([a-zA-Z0-9_-]+)/', $text, $matches);
    return array_unique($matches[1]);
}

/**
 * Extract mentions from text
 */
function extract_mentions($text) {
    preg_match_all('/@([a-zA-Z0-9_.-]+)/', $text, $matches);
    return array_unique($matches[1]);
}

/**
 * Generate blog post slug from title
 */
function generate_blog_slug($text, $max_length = 50) {
    // Extract potential title from first line
    $lines = explode("\n", $text);
    $title = trim($lines[0]);
    
    // Remove markdown headers
    $title = preg_replace('/^#+\s*/', '', $title);
    
    // If no clear title, use first few words
    if (empty($title)) {
        $words = explode(' ', strip_tags($text));
        $title = implode(' ', array_slice($words, 0, 8));
    }
    
    // Convert to slug
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Limit length
    if (strlen($slug) > $max_length) {
        $slug = substr($slug, 0, $max_length);
        $slug = rtrim($slug, '-');
    }
    
    return $slug ?: 'post-' . date('Y-m-d-H-i-s');
}

// =============================================================================
// PHOTO HANDLING FUNCTIONS
// =============================================================================

/**
 * Process and store blog post photos
 */
function process_blog_photos($files, $post_id) {
    $processed = [];
    $photo_dir = BROCHAT_ROOT . '/uploads/photos';
    $preview_dir = BROCHAT_ROOT . '/uploads/previews';
    
    // Ensure directories exist
    ensure_directory($photo_dir);
    ensure_directory($preview_dir);
    
    // Limit to 4 photos
    $files = array_slice($files, 0, 4);
    
    foreach ($files as $index => $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        // Validate photo
        $errors = validate_file_upload($file, 'image');
        if (!empty($errors)) {
            error_log('Photo upload failed: ' . implode(', ', $errors));
            continue;
        }
        
        // Generate secure filename
        $original_name = secure_filename($file['name']);
        $file_id = uniqid('photo_', true);
        $extension = pathinfo($original_name, PATHINFO_EXTENSION);
        
        $filename = $file_id . '.' . $extension;
        $preview_filename = $file_id . '_preview.jpg';
        
        // Move original file
        $original_path = $photo_dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $original_path)) {
            continue;
        }
        
        // Create preview (compressed JPEG)
        $preview_path = $preview_dir . '/' . $preview_filename;
        if (create_photo_preview($original_path, $preview_path)) {
            // Store in database
            $photo_id = db_insert('blog_photos', [
                'post_id' => $post_id,
                'filename' => $filename,
                'preview_filename' => $preview_filename,
                'original_name' => $original_name,
                'file_size' => filesize($original_path),
                'position' => $index,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $processed[] = [
                'id' => $photo_id,
                'filename' => $filename,
                'preview_filename' => $preview_filename,
                'position' => $index
            ];
        } else {
            // Clean up if preview creation failed
            unlink($original_path);
        }
    }
    
    return $processed;
}

/**
 * Create compressed JPEG preview of image
 */
function create_photo_preview($source_path, $preview_path, $max_width = 800, $max_height = 600, $quality = 80) {
    $image_info = getimagesize($source_path);
    if ($image_info === false) {
        return false;
    }
    
    list($orig_width, $orig_height, $image_type) = $image_info;
    
    // Calculate new dimensions
    $ratio = min($max_width / $orig_width, $max_height / $orig_height);
    $new_width = intval($orig_width * $ratio);
    $new_height = intval($orig_height * $ratio);
    
    // Create source image
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($source_path);
            break;
        case IMAGETYPE_WEBP:
            $source = imagecreatefromwebp($source_path);
            break;
        default:
            return false;
    }
    
    if (!$source) {
        return false;
    }
    
    // Create new image
    $preview = imagecreatetruecolor($new_width, $new_height);
    
    // Handle transparency for PNG/GIF
    if ($image_type == IMAGETYPE_PNG || $image_type == IMAGETYPE_GIF) {
        imagealphablending($preview, false);
        imagesavealpha($preview, true);
        $transparent = imagecolorallocatealpha($preview, 255, 255, 255, 127);
        imagefill($preview, 0, 0, $transparent);
    }
    
    // Resize
    imagecopyresampled($preview, $source, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);
    
    // Save as JPEG
    $result = imagejpeg($preview, $preview_path, $quality);
    
    // Clean up
    imagedestroy($source);
    imagedestroy($preview);
    
    return $result;
}

// =============================================================================
// CHAT FUNCTIONS
// =============================================================================

/**
 * Format chat message with colors and emojis
 */
function format_chat_message($message, $username) {
    // Sanitize input
    $message = sanitize_input($message);
    
    // Limit message length
    if (mb_strlen($message, 'UTF-8') > 500) {
        $message = mb_substr($message, 0, 500, 'UTF-8') . '...';
    }
    
    // Parse IRC-style colors
    $message = parse_irc_colors($message);
    
    // Parse emojis
    $message = parse_emojis($message);
    
    // Parse @mentions for chat
    $message = parse_chat_mentions($message);
    
    // Auto-link URLs
    $message = preg_replace_callback(
        '/\b(https?:\/\/[^\s<>]+)/i',
        function($matches) {
            $url = escape_url($matches[1]);
            $display = truncate_text($matches[1], 50);
            return '<a href="' . $url . '" target="_blank" rel="noopener">' . escape_html($display) . '</a>';
        },
        $message
    );
    
    return $message;
}

/**
 * Parse IRC-style color codes
 */
function parse_irc_colors($text) {
    // IRC color codes (simplified)
    $colors = [
        '00' => '#ffffff', '01' => '#000000', '02' => '#000080', '03' => '#008000',
        '04' => '#ff0000', '05' => '#800040', '06' => '#800080', '07' => '#ff8040',
        '08' => '#ffff00', '09' => '#80ff00', '10' => '#008080', '11' => '#00ffff',
        '12' => '#0080ff', '13' => '#ff00ff', '14' => '#808080', '15' => '#c0c0c0'
    ];
    
    // Parse color codes like ^03text^
    $text = preg_replace_callback(
        '/\^(\d{2})([^^]*)\^?/',
        function($matches) use ($colors) {
            $color_code = $matches[1];
            $text = $matches[2];
            $color = $colors[$color_code] ?? '#000000';
            return '<span style="color: ' . $color . '">' . escape_html($text) . '</span>';
        },
        $text
    );
    
    return $text;
}

/**
 * Parse emoji shortcodes
 */
function parse_emojis($text) {
    $emojis = [
        ':)' => '😊', ':(' => '😢', ':D' => '😃', ':P' => '😛', ';)' => '😉',
        ':o' => '😮', ':/' => '😕', ':|' => '😐', ':*' => '😘', '<3' => '❤️',
        '</3' => '💔', ':thumbsup:' => '👍', ':thumbsdown:' => '👎', ':rock:' => '🤘',
        ':guitar:' => '🎸', ':music:' => '🎵', ':fire:' => '🔥', ':skull:' => '💀',
        ':beer:' => '🍺', ':coffee:' => '☕', ':punk:' => '🤘', ':headbang:' => '🤘'
    ];
    
    foreach ($emojis as $shortcode => $emoji) {
        $text = str_replace($shortcode, $emoji, $text);
    }
    
    return $text;
}

/**
 * Parse @mentions in chat (different styling than blog)
 */
function parse_chat_mentions($text) {
    return preg_replace_callback(
        '/@([a-zA-Z0-9_.-]+)/',
        function($matches) {
            $username = escape_html($matches[1]);
            return '<span class="chat-mention">@' . $username . '</span>';
        },
        $text
    );
}

/**
 * Generate chat message ID
 */
function generate_chat_message_id() {
    return uniqid('msg_', true);
}

// =============================================================================
// AUDIO STREAM FUNCTIONS
// =============================================================================

/**
 * Get current audio stream info
 */
function get_stream_info() {
    $stream_file = BROCHAT_CONFIG . '/stream.json';
    if (file_exists($stream_file)) {
        $info = json_decode(file_get_contents($stream_file), true);
        return $info ?: get_default_stream_info();
    }
    return get_default_stream_info();
}

/**
 * Get default stream information
 */
function get_default_stream_info() {
    return [
        'title' => 'BroChat Punk Rock Radio',
        'description' => 'Non-stop punk rock for the masses',
        'url' => '/stream.mp3',
        'bitrate' => 128,
        'sample_rate' => 44100,
        'channels' => 2,
        'format' => 'mp3',
        'status' => 'live'
    ];
}

/**
 * Update stream metadata
 */
function update_stream_info($info) {
    $stream_file = BROCHAT_CONFIG . '/stream.json';
    $current = get_stream_info();
    $updated = array_merge($current, $info);
    
    return file_put_contents($stream_file, json_encode($updated, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Log stream listener
 */
function log_stream_listener($action = 'connect') {
    db_insert('stream_listeners', [
        'session_id' => session_id(),
        'ip_address' => get_client_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'action' => $action,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get current listener count
 */
function get_listener_count() {
    // Count unique sessions in last 5 minutes
    $cutoff = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    $result = db_fetch(
        "SELECT COUNT(DISTINCT session_id) as count 
         FROM stream_listeners 
         WHERE timestamp > ? AND action = 'connect'",
        [$cutoff]
    );
    
    return $result['count'] ?? 0;
}

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

/**
 * Generate unique content ID
 */
function generate_content_id($prefix = 'content') {
    return $prefix . '_' . uniqid() . '_' . time();
}

/**
 * Time ago formatting for blog posts and chat
 */
function time_ago($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time / 60) . 'm ago';
    if ($time < 86400) return floor($time / 3600) . 'h ago';
    if ($time < 604800) return floor($time / 86400) . 'd ago';
    if ($time < 2592000) return floor($time / 604800) . 'w ago';
    
    return date('M j, Y', strtotime($datetime));
}

/**
 * Truncate text while preserving words
 */
function truncate_text($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    
    $truncated = mb_substr($text, 0, $length, 'UTF-8');
    $last_space = mb_strrpos($truncated, ' ', 0, 'UTF-8');
    
    if ($last_space !== false) {
        $truncated = mb_substr($truncated, 0, $last_space, 'UTF-8');
    }
    
    return $truncated . $suffix;
}

/**
 * Create directory if it doesn't exist
 */
function ensure_directory($path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    return $path;
}

/**
 * Clean up old files
 */
function cleanup_old_files($directory, $max_age_days = 30) {
    $cutoff = time() - ($max_age_days * 24 * 60 * 60);
    $files = glob($directory . '/*');
    $cleaned = 0;
    
    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < $cutoff) {
            unlink($file);
            $cleaned++;
        }
    }
    
    return $cleaned;
}

/**
 * Get punk rock quotes for random display
 */
function get_punk_quote() {
    $quotes = [
        "Punk rock is meant to be our freedom. - Kurt Cobain",
        "The only performance that makes it is the one that achieves madness. - Mick Jagger",
        "Rock and roll is a nuclear blast of reality in a mundane world. - Kim Fowley",
        "Punk is not dead. Punk will only die when corporations can exploit and mass produce it. - Jello Biafra",
        "I'd rather be hated for who I am, than loved for who I am not. - Kurt Cobain",
        "The important thing is to keep playing, to play against all odds. - Johnny Rotten",
        "Punk rock should mean freedom. - Kurt Cobain"
    ];
    
    return $quotes[array_rand($quotes)];
}
?>
