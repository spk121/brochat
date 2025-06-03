<?php
require_once __DIR__ . '/bootstrap.php';

// Track page view
track_page_view('homepage');

// Get recent blog posts for feed
$recent_posts = get_recent_blog_posts(10);

// Get recent chat messages for preview
$recent_chat = get_recent_chat_messages(10);

// Get online users
$online_users = get_online_users();

// Get stream info and stats
$stream_info = get_stream_info();
$stream_stats = get_stream_stats();

// Get app statistics
$app_stats = get_app_stats();

// Get a punk quote
$punk_quote = get_punk_quote();

// Check if user is logged in
$current_user = current_user();
$is_logged_in = is_logged_in();

// Get user's draft if they have one
$draft = $is_logged_in ? draft_get('homepage_post') : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BroChat - Punk Rock Community</title>
    <meta name="description" content="The punk rock community platform - blog, chat, and non-stop punk rock streaming">
    
    <!-- Security headers via meta tags -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';">
    
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <h1>🤘 BROCHAT 🤘</h1>
            <div class="tagline">Where Punk Rock Lives Online</div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav">
        <div class="container">
            <ul>
                <li><a href="/">Home</a></li>
                <li><a href="/blog.php">Blog</a></li>
                <li><a href="/chat.php">Chat</a></li>
                <li><a href="/stream.php">Stream</a></li>
                <?php if ($is_logged_in): ?>
                    <li><a href="/profile.php">Profile</a></li>
                    <?php if (BroChatRoles::has_permission('write_blog')): ?>
                        <li><a href="/write.php">Write Post</a></li>
                    <?php endif; ?>
                    <?php if (BroChatRoles::has_role('admin')): ?>
                        <li><a href="/admin.php">Admin</a></li>
                    <?php endif; ?>
                    <li class="user-info">
                        Welcome, <?= escape_html($current_user['display_name'] ?: $current_user['username']) ?>
                        (<?= BroChatRoles::get_role_display_name($current_user['role']) ?>)
                        | <a href="/logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li><a href="/login.php">Login</a></li>
                    <li><a href="/register.php">Join the Pit</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="container">
        <!-- Flash Messages -->
        <?php if (flash_has()): ?>
            <div class="flash-messages">
                <?php foreach (flash_get() as $type => $messages): ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="flash-message flash-<?= $type ?>">
                            <?= escape_html($message) ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Punk Quote -->
        <div class="punk-quote">
            <div class="quote-text"><?= escape_html($punk_quote) ?></div>
        </div>

        <!-- Quick Post (if logged in and can write) -->
        <?php if ($is_logged_in && can_write_blog()): ?>
            <div class="quick-post">
                <div class="section-header">Quick Post</div>
                <form action="/post.php" method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="content">What's on your mind? (1000 chars max)</label>
                        <textarea 
                            id="content" 
                            name="content" 
                            rows="4" 
                            placeholder="Share your punk rock thoughts... Use #hashtags and @mentions!"
                            maxlength="1000"
                            required
                        ><?= $draft ? escape_html($draft['content']) : '' ?></textarea>
                        <div class="char-count" id="char-count">0 / 1000</div>
                    </div>
                    
                    <?php if (can_upload_photos()): ?>
                        <div class="form-group">
                            <label for="photos">Photos (up to 4, 5MB each)</label>
                            <input type="file" id="photos" name="photos[]" multiple accept="image/*" max="4">
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn">Post It! 🤘</button>
                    <button type="button" class="btn btn-secondary" id="saveDraftBtn">Save Draft</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Main Grid -->
        <div class="main-grid">
            <!-- Blog Feed -->
            <main class="blog-section">
                <div class="section-header">
                    Latest from the Pit (<?= $app_stats['blog_posts'] ?> total posts)
                </div>
                
                <?php if (empty($recent_posts)): ?>
                    <div class="blog-post">
                        <p>No posts yet. Be the first to share your punk rock thoughts!</p>
                        <?php if (!$is_logged_in): ?>
                            <p><a href="/register.php" class="btn">Join the Community</a></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_posts as $post): ?>
                        <article class="blog-post">
                            <header class="post-header">
                                <div class="post-author">
                                    <a href="/user/<?= escape_html($post['username']) ?>" style="color: #ff0000; text-decoration: none;">
                                        <?= escape_html($post['display_name'] ?: $post['username']) ?>
                                    </a>
                                </div>
                                <div class="post-time">
                                    <a href="/post/<?= escape_html($post['slug']) ?>" style="color: #888; text-decoration: none;">
                                        <?= time_ago($post['created_at']) ?>
                                    </a>
                                </div>
                            </header>
                            
                            <div class="post-content">
                                <?= $post['parsed_content'] ?>
                            </div>
                            
                            <?php if (!empty($post['photos'])): ?>
                                <div class="post-photos">
                                    <?php foreach ($post['photos'] as $photo): ?>
                                        <img 
                                            src="/uploads/previews/<?= escape_html($photo['preview_filename']) ?>" 
                                            alt="Photo"
                                            class="post-photo"
                                            data-full-src="/uploads/photos/<?= escape_html($photo['filename']) ?>"
                                        >
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($post['tags'])): ?>
                                <div class="post-tags">
                                    <?php foreach ($post['tags'] as $tag): ?>
                                        <a href="/tag/<?= urlencode($tag['name']) ?>" class="tag">
                                            #<?= escape_html($tag['name']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div style="padding: 20px; text-align: center;">
                    <a href="/blog.php" class="btn">View All Posts</a>
                </div>
            </main>

            <!-- Sidebar -->
            <aside class="sidebar">
                <!-- Stream Player -->
                <div class="sidebar-section">
                    <div class="section-header">🎵 Punk Rock Radio</div>
                    <div class="stream-player">
                        <div class="stream-info">
                            <div class="stream-title"><?= escape_html($stream_info['title']) ?></div>
                            <div class="stream-stats">
                                <?= $stream_stats['current_listeners'] ?> listeners • 
                                <?= escape_html($stream_info['bitrate']) ?>kbps
                            </div>
                        </div>
                        
                        <div class="audio-controls">
                            <button class="play-button" id="playButton">
                                ▶ PLAY
                            </button>
                            <div class="volume-control">
                                <input 
                                    type="range" 
                                    id="volumeSlider" 
                                    min="0" 
                                    max="100" 
                                    value="<?= get_stream_volume() ?>"
                                    style="width: 100%;"
                                >
                            </div>
                        </div>
                        
                        <audio id="audioPlayer" preload="none">
                            <source src="<?= escape_html($stream_info['url']) ?>" type="audio/mpeg">
                            Your browser doesn't support the audio stream.
                        </audio>
                        
                        <div style="text-align: center; margin-top: 15px;">
                            <a href="/stream.php" class="btn btn-secondary">Full Player</a>
                        </div>
                    </div>
                </div>

                <!-- Chat Preview -->
                <div class="sidebar-section">
                    <div class="section-header">💬 Live Chat</div>
                    <div class="chat-preview" id="chatPreview">
                        <?php if (empty($recent_chat)): ?>
                            <div style="padding: 15px; text-align: center; color: #888;">
                                No recent messages. Start the conversation!
                            </div>
                        <?php else: ?>
                            <?php foreach (array_reverse($recent_chat) as $message): ?>
                                <div class="chat-message">
                                    <span class="chat-user"><?= escape_html($message['username']) ?>:</span>
                                    <span class="chat-text"><?= $message['formatted_message'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 15px; text-align: center;">
                        <a href="/chat.php" class="btn">Join Chat</a>
                    </div>
                </div>

                <!-- Online Users -->
                <div class="sidebar-section">
                    <div class="section-header">👥 Who's Here (<?= count($online_users) ?>)</div>
                    <div class="online-users">
                        <?php if (empty($online_users)): ?>
                            <div style="color: #888; text-align: center;">No one's around right now</div>
                        <?php else: ?>
                            <div class="user-list">
                                <?php foreach ($online_users as $user): ?>
                                    <div class="online-user status-<?= $user['status'] ?>">
                                        <div class="status-dot"></div>
                                        <div>
                                            <a href="/user/<?= escape_html($user['username']) ?>" style="color: #fff; text-decoration: none;">
                                                <?= escape_html($user['display_name'] ?: $user['username']) ?>
                                            </a>
                                            <small style="color: #888;">
                                                (<?= BroChatRoles::get_role_display_name($user['role']) ?>)
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Stats -->
                <div class="sidebar-section">
                    <div class="section-header">📊 Community Stats</div>
                    <div style="padding: 15px;">
                        <div style="margin-bottom: 10px;">
                            <strong><?= $app_stats['users'] ?></strong> punks in the community
                        </div>
                        <div style="margin-bottom: 10px;">
                            <strong><?= $app_stats['blog_posts'] ?></strong> blog posts shared
                        </div>
                        <div style="margin-bottom: 10px;">
                            <strong><?= $app_stats['chat_messages'] ?></strong> chat messages sent
                        </div>
                        <div>
                            <strong><?= $stream_stats['total_connections'] ?></strong> stream connections today
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <!-- Pass data to JavaScript -->
    <script id="brochat-data" type="application/json">
        {
            "csrf_token": "<?= escape_js(csrf_token()) ?>",
            "stream_url": "<?= escape_js($stream_info['url']) ?>",
            "stream_volume": <?= (int)get_stream_volume() ?>,
            "is_logged_in": <?= $is_logged_in ? 'true' : 'false' ?>
        }
    </script>
    <script src="/assets/js/main.js"></script>
</body>
</html>
