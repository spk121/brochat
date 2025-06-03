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
    
    <style>
        /* Punk Rock CSS - Keep it raw but functional */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: #000;
            color: #fff;
            line-height: 1.4;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .header {
            background: linear-gradient(45deg, #8B0000, #000);
            padding: 20px 0;
            border-bottom: 3px solid #ff0000;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 3em;
            text-align: center;
            color: #ff0000;
            text-shadow: 2px 2px 4px #000;
            letter-spacing: 3px;
        }
        
        .tagline {
            text-align: center;
            color: #ccc;
            font-style: italic;
            margin-top: 10px;
        }
        
        /* Navigation */
        .nav {
            background: #333;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 5px solid #ff0000;
        }
        
        .nav ul {
            list-style: none;
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .nav a {
            color: #fff;
            text-decoration: none;
            font-weight: bold;
            text-transform: uppercase;
            padding: 8px 15px;
            border: 1px solid transparent;
            transition: all 0.3s;
        }
        
        .nav a:hover {
            border-color: #ff0000;
            background: rgba(255, 0, 0, 0.1);
        }
        
        .user-info {
            margin-left: auto;
            color: #ff0000;
        }
        
        /* Main layout */
        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        /* Blog section */
        .blog-section {
            background: #111;
            border: 2px solid #333;
            border-radius: 5px;
        }
        
        .section-header {
            background: #8B0000;
            color: #fff;
            padding: 15px 20px;
            font-size: 1.5em;
            font-weight: bold;
            border-bottom: 2px solid #ff0000;
        }
        
        .blog-post {
            padding: 20px;
            border-bottom: 1px solid #333;
        }
        
        .blog-post:last-child {
            border-bottom: none;
        }
        
        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #444;
        }
        
        .post-author {
            color: #ff0000;
            font-weight: bold;
        }
        
        .post-time {
            color: #888;
            font-size: 0.9em;
        }
        
        .post-content {
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .post-photos {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .post-photo {
            max-width: 150px;
            height: 100px;
            object-fit: cover;
            border: 2px solid #333;
            border-radius: 3px;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        
        .post-photo:hover {
            border-color: #ff0000;
        }
        
        .post-tags {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .tag {
            background: #333;
            color: #fff;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            text-decoration: none;
        }
        
        .tag:hover {
            background: #ff0000;
        }
        
        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .sidebar-section {
            background: #111;
            border: 2px solid #333;
            border-radius: 5px;
        }
        
        /* Stream player */
        .stream-player {
            padding: 20px;
        }
        
        .stream-info {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .stream-title {
            color: #ff0000;
            font-size: 1.2em;
            margin-bottom: 5px;
        }
        
        .stream-stats {
            color: #888;
            font-size: 0.9em;
        }
        
        .audio-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            background: #000;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #333;
        }
        
        .play-button {
            background: #ff0000;
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 3px;
            cursor: pointer;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .play-button:hover {
            background: #cc0000;
        }
        
        .volume-control {
            flex: 1;
        }
        
        /* Chat preview */
        .chat-preview {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .chat-message {
            padding: 8px 15px;
            border-bottom: 1px solid #222;
            font-size: 0.9em;
        }
        
        .chat-user {
            color: #ff0000;
            font-weight: bold;
        }
        
        .chat-text {
            margin-left: 10px;
        }
        
        /* Online users */
        .online-users {
            padding: 15px;
        }
        
        .user-list {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .online-user {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px;
            border-radius: 3px;
        }
        
        .online-user:hover {
            background: #222;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #00ff00;
        }
        
        .status-away .status-dot {
            background: #ffff00;
        }
        
        /* Quote section */
        .punk-quote {
            background: #8B0000;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 5px solid #ff0000;
        }
        
        .quote-text {
            font-style: italic;
            font-size: 1.1em;
            text-align: center;
        }
        
        /* Buttons */
        .btn {
            background: #ff0000;
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #cc0000;
        }
        
        .btn-secondary {
            background: #333;
        }
        
        .btn-secondary:hover {
            background: #555;
        }
        
        /* Quick post form */
        .quick-post {
            background: #111;
            border: 2px solid #333;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #ff0000;
            font-weight: bold;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 10px;
            background: #000;
            border: 1px solid #333;
            color: #fff;
            border-radius: 3px;
            font-family: inherit;
            resize: vertical;
        }
        
        .form-group textarea:focus {
            border-color: #ff0000;
            outline: none;
        }
        
        .char-count {
            text-align: right;
            color: #888;
            font-size: 0.8em;
            margin-top: 5px;
        }
        
        .char-count.warning {
            color: #ffff00;
        }
        
        .char-count.error {
            color: #ff0000;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            
            .nav ul {
                flex-direction: column;
                gap: 10px;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .post-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
        
        /* Flash messages */
        .flash-messages {
            margin-bottom: 20px;
        }
        
        .flash-message {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 3px;
            border-left: 5px solid;
        }
        
        .flash-success {
            background: rgba(0, 255, 0, 0.1);
            border-color: #00ff00;
            color: #00ff00;
        }
        
        .flash-error {
            background: rgba(255, 0, 0, 0.1);
            border-color: #ff0000;
            color: #ff0000;
        }
        
        .flash-warning {
            background: rgba(255, 255, 0, 0.1);
            border-color: #ffff00;
            color: #ffff00;
        }
        
        .flash-info {
            background: rgba(0, 150, 255, 0.1);
            border-color: #0096ff;
            color: #0096ff;
        }
        
        .flash-punk {
            background: rgba(255, 0, 255, 0.1);
            border-color: #ff00ff;
            color: #ff00ff;
        }
        
        /* Mentions and hashtags */
        .mention {
            color: #00ff00;
            text-decoration: none;
        }
        
        .mention:hover {
            text-decoration: underline;
        }
        
        .hashtag {
            color: #0096ff;
            text-decoration: none;
        }
        
        .hashtag:hover {
            text-decoration: underline;
        }
    </style>
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
                    <button type="button" class="btn btn-secondary" onclick="saveDraft()">Save Draft</button>
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
                                            onclick="viewPhoto('/uploads/photos/<?= escape_html($photo['filename']) ?>')"
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
                            <button class="play-button" id="playButton" onclick="toggleStream()">
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

    <script>
        // Character counter for quick post
        const contentTextarea = document.getElementById('content');
        const charCount = document.getElementById('char-count');
        
        if (contentTextarea && charCount) {
            function updateCharCount() {
                const count = contentTextarea.value.length;
                charCount.textContent = count + ' / 1000';
                
                charCount.className = 'char-count';
                if (count > 900) {
                    charCount.className += ' warning';
                }
                if (count >= 1000) {
                    charCount.className += ' error';
                }
            }
            
            contentTextarea.addEventListener('input', updateCharCount);
            updateCharCount(); // Initial count
        }
        
        // Save draft function
        function saveDraft() {
            const content = contentTextarea?.value;
            if (content) {
                fetch('/api/save-draft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        context: 'homepage_post',
                        content: content,
                        csrf_token: '<?= csrf_token() ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Draft saved!');
                    }
                })
                .catch(err => console.error('Failed to save draft:', err));
            }
        }
        
        // Stream player functionality
        const audioPlayer = document.getElementById('audioPlayer');
        const playButton = document.getElementById('playButton');
        const volumeSlider = document.getElementById('volumeSlider');
        let isPlaying = false;
        
        function toggleStream() {
            if (isPlaying) {
                audioPlayer.pause();
                playButton.textContent = '▶ PLAY';
                isPlaying = false;
            } else {
                audioPlayer.play().then(() => {
                    playButton.textContent = '⏸ PAUSE';
                    isPlaying = true;
                    
                    // Log stream connection
                    fetch('/api/stream-connect.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                }).catch(err => {
                    console.error('Failed to play stream:', err);
                    alert('Failed to connect to stream. Please try again.');
                });
            }
        }
        
        // Volume control
        if (volumeSlider) {
            volumeSlider.addEventListener('input', function() {
                if (audioPlayer) {
                    audioPlayer.volume = this.value / 100;
                    
                    // Save volume preference
                    fetch('/api/save-preference.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            key: 'stream_volume',
                            value: this.value
                        })
                    });
                }
            });
            
            // Set initial volume
            if (audioPlayer) {
                audioPlayer.volume = volumeSlider.value / 100;
            }
        }
        
        // Photo viewer
        function viewPhoto(src) {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.9); z-index: 1000;
                display: flex; align-items: center; justify-content: center;
                cursor: pointer;
            `;
            
            const img = document.createElement('img');
            img.src = src;
            img.style.cssText = 'max-width: 90%; max-height: 90%; border: 3px solid #ff0000;';
            
            modal.appendChild(img);
            modal.onclick = () => document.body.removeChild(modal);
            document.body.appendChild(modal);
        }
        
        // Auto-refresh chat preview every 30 seconds
        setInterval(function() {
            fetch('/api/chat-preview.php')
                .then(response => response.text())
                .then(html => {
                    const chatPreview = document.getElementById('chatPreview');
                    if (chatPreview) {
                        chatPreview.innerHTML = html;
                    }
                })
                .catch(err => console.error('Failed to refresh chat:', err));
        }, 30000);
        
        // Auto-save draft every 30 seconds
        if (contentTextarea) {
            setInterval(function() {
                const content = contentTextarea.value.trim();
                if (content && content.length > 10) {
                    saveDraft();
                }
            }, 30000);
        }
    </script>
</body>
</html>
