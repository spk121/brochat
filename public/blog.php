<?php
require_once __DIR__ . '/bootstrap.php';

// Track page view
track_page_view('blog');

// Get parameters for filtering and pagination
$tag = $_GET['tag'] ?? null;
$user = $_GET['user'] ?? null;
$search = $_GET['search'] ?? null;
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get posts based on filters
if ($tag) {
    $posts = get_blog_posts_by_tag($tag, $per_page, $offset);
    $page_title = "Posts tagged: #$tag";
} elseif ($user) {
    $user_data = get_user_by_username($user);
    if (!$user_data) {
        flash_error("User '$user' not found");
        redirect('/blog.php');
    }
    $posts = get_blog_posts_by_user($user_data['id'], $per_page, $offset);
    $page_title = "Posts by " . ($user_data['display_name'] ?: $user_data['username']);
} elseif ($search) {
    $posts = search_blog_posts($search, $per_page, $offset);
    $page_title = "Search results: " . escape_html($search);
} else {
    $posts = get_recent_blog_posts($per_page, $offset);
    $page_title = "Latest from the Pit";
}

// Get popular tags for sidebar
$popular_tags = get_popular_tags(15);

// Get app stats
$app_stats = get_app_stats();

// Get online users
$online_users = get_online_users();

// Current user info
$current_user = current_user();
$is_logged_in = is_logged_in();

// Pagination info
$total_posts = $app_stats['blog_posts']; // Rough estimate
$total_pages = max(1, ceil($total_posts / $per_page));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape_html($page_title) ?> - BroChat Blog</title>
    <meta name="description" content="Punk rock community blog - share your thoughts, photos, and connect with fellow punks">
    
    <style>
        /* Punk Rock Blog Styles */
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
            font-size: 2.5em;
            text-align: center;
            color: #ff0000;
            text-shadow: 2px 2px 4px #000;
            letter-spacing: 2px;
        }
        
        .header .subtitle {
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
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
        
        .nav a:hover, .nav a.active {
            border-color: #ff0000;
            background: rgba(255, 0, 0, 0.1);
        }
        
        .user-info {
            color: #ff0000;
            font-size: 0.9em;
        }
        
        /* Search and filters */
        .filters {
            background: #111;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border: 2px solid #333;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            min-width: 300px;
        }
        
        .search-form input {
            flex: 1;
            padding: 10px;
            background: #000;
            border: 1px solid #333;
            color: #fff;
            border-radius: 3px;
        }
        
        .search-form input:focus {
            border-color: #ff0000;
            outline: none;
        }
        
        .btn {
            background: #ff0000;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            transition: background 0.3s;
            font-size: 0.9em;
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
        
        .filter-info {
            color: #ff0000;
            font-weight: bold;
        }
        
        /* Main layout */
        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        /* Blog posts */
        .blog-feed {
            background: #111;
            border: 2px solid #333;
            border-radius: 5px;
        }
        
        .feed-header {
            background: #8B0000;
            color: #fff;
            padding: 15px 20px;
            font-size: 1.3em;
            font-weight: bold;
            border-bottom: 2px solid #ff0000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .post-count {
            font-size: 0.8em;
            color: #ccc;
        }
        
        .blog-post {
            padding: 25px;
            border-bottom: 1px solid #333;
            position: relative;
        }
        
        .blog-post:last-child {
            border-bottom: none;
        }
        
        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #444;
        }
        
        .post-author-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .author-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(45deg, #ff0000, #8B0000);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2em;
        }
        
        .author-details h4 {
            color: #ff0000;
            margin-bottom: 3px;
        }
        
        .author-details .role {
            color: #888;
            font-size: 0.8em;
            text-transform: uppercase;
        }
        
        .post-meta {
            text-align: right;
            color: #888;
            font-size: 0.9em;
        }
        
        .post-time {
            color: #ccc;
        }
        
        .post-link {
            color: #ff0000;
            text-decoration: none;
            font-weight: bold;
        }
        
        .post-link:hover {
            text-decoration: underline;
        }
        
        .post-content {
            margin-bottom: 20px;
            line-height: 1.6;
            font-size: 1.1em;
        }
        
        .post-photos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .post-photo {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border: 2px solid #333;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .post-photo:hover {
            border-color: #ff0000;
            transform: scale(1.02);
        }
        
        .post-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .post-tags {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .tag {
            background: #333;
            color: #fff;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .tag:hover {
            background: #ff0000;
        }
        
        .post-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            background: none;
            border: 1px solid #333;
            color: #ccc;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8em;
            text-decoration: none;
        }
        
        .action-btn:hover {
            border-color: #ff0000;
            color: #ff0000;
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
        
        .section-header {
            background: #8B0000;
            color: #fff;
            padding: 12px 15px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9em;
        }
        
        .section-content {
            padding: 15px;
        }
        
        /* Popular tags */
        .tag-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .tag-cloud .tag {
            font-size: 0.8em;
        }
        
        .tag.popular {
            background: #ff0000;
            font-size: 0.9em;
        }
        
        /* Online users */
        .user-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .online-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px;
            border-radius: 3px;
            transition: background 0.3s;
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
        
        .username {
            color: #fff;
            text-decoration: none;
            font-weight: bold;
        }
        
        .username:hover {
            color: #ff0000;
        }
        
        /* Pagination */
        .pagination {
            background: #111;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
            margin-top: 30px;
            border: 2px solid #333;
        }
        
        .pagination a, .pagination span {
            display: inline-block;
            padding: 10px 15px;
            margin: 0 5px;
            background: #333;
            color: #fff;
            text-decoration: none;
            border-radius: 3px;
            transition: background 0.3s;
        }
        
        .pagination a:hover {
            background: #ff0000;
        }
        
        .pagination .current {
            background: #ff0000;
        }
        
        .pagination .disabled {
            background: #222;
            color: #666;
            cursor: not-allowed;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }
        
        .empty-state h3 {
            color: #ff0000;
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        
        .empty-state p {
            margin-bottom: 20px;
            line-height: 1.5;
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
        
        .flash-info {
            background: rgba(0, 150, 255, 0.1);
            border-color: #0096ff;
            color: #0096ff;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-form {
                min-width: auto;
            }
            
            .post-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .post-footer {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .nav {
                flex-direction: column;
                align-items: stretch;
            }
            
            .nav-links {
                justify-content: center;
                flex-wrap: wrap;
            }
        }
        
        /* Photo modal */
        .photo-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.95);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .photo-modal img {
            max-width: 90%;
            max-height: 90%;
            border: 3px solid #ff0000;
            border-radius: 5px;
        }
        
        .photo-modal .close-hint {
            position: absolute;
            top: 20px;
            right: 20px;
            color: #fff;
            font-size: 1.2em;
            background: rgba(0, 0, 0, 0.7);
            padding: 10px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <h1>🤘 BROCHAT BLOG 🤘</h1>
            <div class="subtitle"><?= escape_html($page_title) ?></div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav">
        <div class="container">
            <div class="nav-links">
                <a href="/">Home</a>
                <a href="/blog.php" class="active">Blog</a>
                <a href="/chat.php">Chat</a>
                <a href="/stream.php">Stream</a>
                <?php if ($is_logged_in && can_write_blog()): ?>
                    <a href="/write.php" class="btn">✍️ Write Post</a>
                <?php endif; ?>
            </div>
            
            <div class="user-info">
                <?php if ($is_logged_in): ?>
                    <?= escape_html($current_user['display_name'] ?: $current_user['username']) ?>
                    (<?= BroChatRoles::get_role_display_name($current_user['role']) ?>)
                    | <a href="/logout.php" style="color: #ccc;">Logout</a>
                <?php else: ?>
                    <a href="/login.php" style="color: #ff0000;">Login</a> |
                    <a href="/register.php" style="color: #ff0000;">Join</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Flash Messages -->
        <?php if (flash_has()): ?>
            <div class="flash-messages">
                <?php foreach (flash_get() as $type => $messages): ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="flash-message flash-<?= $type ?>">
                            <?= $message ?> <!-- Allow HTML in flash messages for links -->
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Filters and Search -->
        <div class="filters">
            <div class="filter-row">
                <form class="search-form" method="GET">
                    <input 
                        type="text" 
                        name="search" 
                        value="<?= escape_html($search ?? '') ?>"
                        placeholder="Search posts, hashtags, mentions..."
                    >
                    <button type="submit" class="btn">🔍 Search</button>
                    <?php if ($search): ?>
                        <a href="/blog.php" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </form>
                
                <div class="filter-info">
                    <?php if ($tag): ?>
                        Showing posts tagged: <strong>#<?= escape_html($tag) ?></strong>
                        <a href="/blog.php" style="color: #ccc; margin-left: 10px;">Show All</a>
                    <?php elseif ($user): ?>
                        Showing posts by: <strong><?= escape_html($user_data['display_name'] ?: $user_data['username']) ?></strong>
                        <a href="/blog.php" style="color: #ccc; margin-left: 10px;">Show All</a>
                    <?php elseif ($search): ?>
                        Search: <strong><?= escape_html($search) ?></strong>
                    <?php else: ?>
                        Showing all posts
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="main-grid">
            <!-- Blog Feed -->
            <main class="blog-feed">
                <div class="feed-header">
                    <span><?= escape_html($page_title) ?></span>
                    <span class="post-count"><?= count($posts) ?> posts</span>
                </div>
                
                <?php if (empty($posts)): ?>
                    <div class="empty-state">
                        <h3>🎸 No Posts Found</h3>
                        <?php if ($search): ?>
                            <p>No posts match your search for "<strong><?= escape_html($search) ?></strong>"</p>
                            <p>Try different keywords or <a href="/blog.php" style="color: #ff0000;">browse all posts</a></p>
                        <?php elseif ($tag): ?>
                            <p>No posts tagged with "<strong>#<?= escape_html($tag) ?></strong>" yet.</p>
                            <p><a href="/blog.php" style="color: #ff0000;">Browse all posts</a> or be the first to use this tag!</p>
                        <?php elseif ($user): ?>
                            <p><?= escape_html($user_data['display_name'] ?: $user_data['username']) ?> hasn't posted anything yet.</p>
                        <?php else: ?>
                            <p>No posts in the community yet. Be the first to share your punk rock thoughts!</p>
                        <?php endif; ?>
                        
                        <?php if ($is_logged_in && can_write_blog()): ?>
                            <a href="/write.php" class="btn">✍️ Write the First Post</a>
                        <?php elseif (!$is_logged_in): ?>
                            <a href="/register.php" class="btn">Join the Community</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <article class="blog-post" data-post-id="<?= $post['id'] ?>">
                            <header class="post-header">
                                <div class="post-author-info">
                                    <div class="author-avatar">
                                        <?= strtoupper(substr($post['username'], 0, 1)) ?>
                                    </div>
                                    <div class="author-details">
                                        <h4>
                                            <a href="/blog.php?user=<?= urlencode($post['username']) ?>" class="username">
                                                <?= escape_html($post['display_name'] ?: $post['username']) ?>
                                            </a>
                                        </h4>
                                        <div class="role"><?= BroChatRoles::get_role_display_name($post['role'] ?? 'fan') ?></div>
                                    </div>
                                </div>
                                
                                <div class="post-meta">
                                    <div class="post-time">
                                        <a href="/post/<?= escape_html($post['slug']) ?>" class="post-link">
                                            <?= time_ago($post['created_at']) ?>
                                        </a>
                                    </div>
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
                                            alt="Photo by <?= escape_html($post['username']) ?>"
                                            class="post-photo"
                                            onclick="viewPhoto('/uploads/photos/<?= escape_html($photo['filename']) ?>')"
                                            loading="lazy"
                                        >
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <footer class="post-footer">
                                <?php if (!empty($post['tags'])): ?>
                                    <div class="post-tags">
                                        <?php foreach ($post['tags'] as $tag_item): ?>
                                            <a href="/blog.php?tag=<?= urlencode($tag_item['name']) ?>" class="tag">
                                                #<?= escape_html($tag_item['name']) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="post-actions">
                                    <a href="/post/<?= escape_html($post['slug']) ?>" class="action-btn">
                                        💬 View Post
                                    </a>
                                    
                                    <?php if ($is_logged_in && can_delete_blog_post($post['id'])): ?>
                                        <button class="action-btn" onclick="deletePost(<?= $post['id'] ?>)">
                                            🗑️ Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </main>

            <!-- Sidebar -->
            <aside class="sidebar">
                <!-- Popular Tags -->
                <div class="sidebar-section">
                    <div class="section-header">🏷️ Popular Tags</div>
                    <div class="section-content">
                        <?php if (empty($popular_tags)): ?>
                            <p style="color: #888; text-align: center;">No tags yet</p>
                        <?php else: ?>
                            <div class="tag-cloud">
                                <?php foreach ($popular_tags as $tag_item): ?>
                                    <a href="/blog.php?tag=<?= urlencode($tag_item['name']) ?>" 
                                       class="tag <?= $tag_item['post_count'] >= 5 ? 'popular' : '' ?>">
                                        #<?= escape_html($tag_item['name']) ?>
                                        <small>(<?= $tag_item['post_count'] ?>)</small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Online Users -->
                <div class="sidebar-section">
                    <div class="section-header">👥 Who's Online (<?= count($online_users) ?>)</div>
                    <div class="section-content">
                        <?php if (empty($online_users)): ?>
                            <p style="color: #888; text-align: center;">No one's around</p>
                        <?php else: ?>
                            <div class="user-list">
                                <?php foreach (array_slice($online_users, 0, 10) as $user): ?>
                                    <div class="online-user status-<?= $user['status'] ?>">
                                        <div class="status-dot"></div>
                                        <a href="/blog.php?user=<?= urlencode($user['username']) ?>" class="username">
                                            <?= escape_html($user['display_name'] ?: $user['username']) ?>
                                        </a>
                                        <small style="color: #666;">
                                            (<?= BroChatRoles::get_role_display_name($user['role']) ?>)
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (count($online_users) > 10): ?>
                                    <div style="text-align: center; margin-top: 10px;">
                                        <small style="color: #888;">
                                            +<?= count($online_users) - 10 ?> more online
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Community Stats -->
                <div class="sidebar-section">
                    <div class="section-header">📊 Community Stats</div>
                    <div class="section-content">
                        <div style="margin-bottom: 10px;">
                            <strong style="color: #ff0000;"><?= $app_stats['users'] ?></strong> punks joined
                        </div>
                        <div style="margin-bottom: 10px;">
                            <strong style="color: #ff0000;"><?= $app_stats['blog_posts'] ?></strong> posts shared
                        </div>
                        <div style="margin-bottom: 10px;">
                            <strong style="color: #ff0000;"><?= $app_stats['chat_messages'] ?></strong> messages sent
                        </div>
                        <div>
                            <strong style="color: #ff0000;"><?= count($popular_tags) ?></strong> tags created
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <?php if ($is_logged_in): ?>
                    <div class="sidebar-section">
                        <div class="section-header">⚡ Quick Actions</div>
                        <div class="section-content">
                            <?php if (can_write_blog()): ?>
                                <a href="/write.php" class="btn" style="width: 100%; margin-bottom: 10px;">
                                    ✍️ Write New Post
                                </a>
                            <?php endif; ?>
                            <a href="/chat.php" class="btn btn-secondary" style="width: 100%; margin-bottom: 10px;">
                                💬 Join Chat
                            </a>
                            <a href="/stream.php" class="btn btn-secondary" style="width: 100%;">
                                🎵 Listen to Stream
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="sidebar-section">
                        <div class="section-header">🎸 Join the Pit</div>
                        <div class="section-content">
                            <p style="color: #ccc; margin-bottom: 15px; font-size: 0.9em;">
                                Ready to share your punk rock thoughts?
                            </p>
                            <a href="/register.php" class="btn" style="width: 100%; margin-bottom: 10px;">
                                🤘 Join Community
                            </a>
                            <a href="/login.php" class="btn btn-secondary" style="width: 100%;">
                                🔑 Login
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </aside>
        </div>

        <!-- Pagination -->
        <?php if (!empty($posts) && $total_pages > 1): ?>
            <div class="pagination">
                <?php
                $base_url = '/blog.php';
                $params = [];
                if ($tag) $params['tag'] = $tag;
                if ($user) $params['user'] = $user;
                if ($search) $params['search'] = $search;
                
                $query_string = empty($params) ? '' : '?' . http_build_query($params);
                $page_param = empty($params) ? '?page=' : '&page=';
                ?>
                
                <!-- Previous page -->
                <?php if ($page > 1): ?>
                    <a href="<?= $base_url . $query_string . $page_param . ($page - 1) ?>">« Previous</a>
                <?php else: ?>
                    <span class="disabled">« Previous</span>
                <?php endif; ?>
                
                <!-- Page numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <a href="<?= $base_url . $query_string . $page_param . '1' ?>">1</a>
                    <?php if ($start_page > 2): ?>
                        <span>...</span>
                    <?php endif;
                endif;
                
                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= $base_url . $query_string . $page_param . $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor;
                
                if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span>...</span>
                    <?php endif; ?>
                    <a href="<?= $base_url . $query_string . $page_param . $total_pages ?>"><?= $total_pages ?></a>
                <?php endif; ?>
                
                <!-- Next page -->
                <?php if ($page < $total_pages): ?>
                    <a href="<?= $base_url . $query_string . $page_param . ($page + 1) ?>">Next »</a>
                <?php else: ?>
                    <span class="disabled">Next »</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Photo modal viewer
        function viewPhoto(src) {
            const modal = document.createElement('div');
            modal.className = 'photo-modal';
            
            const img = document.createElement('img');
            img.src = src;
            img.alt = 'Full size photo';
            
            const closeHint = document.createElement('div');
            closeHint.className = 'close-hint';
            closeHint.textContent = 'Click to close';
            
            modal.appendChild(img);
            modal.appendChild(closeHint);
            
            modal.addEventListener('click', () => {
                document.body.removeChild(modal);
            });
            
            document.body.appendChild(modal);
            
            // Prevent scrolling
            document.body.style.overflow = 'hidden';
            
            // Restore scrolling when closed
            modal.addEventListener('click', () => {
                document.body.style.overflow = '';
            });
            
            // ESC key to close
            function handleEscape(e) {
                if (e.key === 'Escape') {
                    document.body.removeChild(modal);
                    document.body.style.overflow = '';
                    document.removeEventListener('keydown', handleEscape);
                }
            }
            document.addEventListener('keydown', handleEscape);
        }
        
        // Delete post function
        function deletePost(postId) {
            if (!confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
                return;
            }
            
            fetch('/api/delete-post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    post_id: postId,
                    csrf_token: '<?= csrf_token() ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove post from DOM
                    const postElement = document.querySelector(`[data-post-id="${postId}"]`);
                    if (postElement) {
                        postElement.style.transition = 'opacity 0.5s';
                        postElement.style.opacity = '0';
                        setTimeout(() => {
                            postElement.remove();
                        }, 500);
                    }
                    
                    // Show success message
                    showFlashMessage('Post deleted successfully', 'success');
                } else {
                    alert('Failed to delete post: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error deleting post:', error);
                alert('Failed to delete post. Please try again.');
            });
        }
        
        // Show flash message dynamically
        function showFlashMessage(message, type) {
            const flashContainer = document.querySelector('.flash-messages') || createFlashContainer();
            
            const flashMessage = document.createElement('div');
            flashMessage.className = `flash-message flash-${type}`;
            flashMessage.textContent = message;
            
            flashContainer.appendChild(flashMessage);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                flashMessage.style.transition = 'opacity 0.5s';
                flashMessage.style.opacity = '0';
                setTimeout(() => {
                    if (flashMessage.parentNode) {
                        flashMessage.parentNode.removeChild(flashMessage);
                    }
                }, 500);
            }, 5000);
        }
        
        function createFlashContainer() {
            const container = document.createElement('div');
            container.className = 'flash-messages';
            
            const mainContent = document.querySelector('.container');
            const filters = document.querySelector('.filters');
            mainContent.insertBefore(container, filters);
            
            return container;
        }
        
        // Search form enhancements
        const searchForm = document.querySelector('.search-form');
        const searchInput = searchForm?.querySelector('input[name="search"]');
        
        if (searchInput) {
            // Auto-submit on Enter
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchForm.submit();
                }
            });
            
            // Clear search with Escape
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    this.value = '';
                    this.blur();
                }
            });
        }
        
        // Infinite scroll (optional enhancement)
        let isLoading = false;
        
        function loadMorePosts() {
            if (isLoading) return;
            
            const currentPage = <?= $page ?>;
            const totalPages = <?= $total_pages ?>;
            
            if (currentPage >= totalPages) return;
            
            isLoading = true;
            
            // Show loading indicator
            const loadingDiv = document.createElement('div');
            loadingDiv.style.textAlign = 'center';
            loadingDiv.style.padding = '20px';
            loadingDiv.style.color = '#888';
            loadingDiv.textContent = 'Loading more posts...';
            
            const blogFeed = document.querySelector('.blog-feed');
            blogFeed.appendChild(loadingDiv);
            
            // Build URL for next page
            const url = new URL(window.location);
            url.searchParams.set('page', currentPage + 1);
            
            fetch(url.toString() + '&ajax=1')
                .then(response => response.text())
                .then(html => {
                    // Parse HTML and extract posts
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newPosts = doc.querySelectorAll('.blog-post');
                    
                    // Remove loading indicator
                    blogFeed.removeChild(loadingDiv);
                    
                    // Add new posts
                    newPosts.forEach(post => {
                        blogFeed.appendChild(post);
                    });
                    
                    isLoading = false;
                })
                .catch(error => {
                    console.error('Error loading more posts:', error);
                    blogFeed.removeChild(loadingDiv);
                    isLoading = false;
                });
        }
        
        // Auto-load more posts when scrolling near bottom
        window.addEventListener('scroll', function() {
            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 1000) {
                loadMorePosts();
            }
        });
        
        // Lazy loading for images
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                        }
                        observer.unobserve(img);
                    }
                });
            });
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt + W to write new post
            if (e.altKey && e.key === 'w') {
                e.preventDefault();
                const writeLink = document.querySelector('a[href="/write.php"]');
                if (writeLink) {
                    window.location.href = '/write.php';
                }
            }
            
            // Alt + S to focus search
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
            
            // Alt + H to go home
            if (e.altKey && e.key === 'h') {
                e.preventDefault();
                window.location.href = '/';
            }
        });
        
        // Auto-refresh online users every 30 seconds
        setInterval(function() {
            fetch('/api/online-users.php')
                .then(response => response.json())
                .then(data => {
                    updateOnlineUsers(data.users);
                })
                .catch(error => {
                    console.error('Error refreshing online users:', error);
                });
        }, 30000);
        
        function updateOnlineUsers(users) {
            const userList = document.querySelector('.user-list');
            if (!userList) return;
            
            userList.innerHTML = '';
            
            users.slice(0, 10).forEach(user => {
                const userDiv = document.createElement('div');
                userDiv.className = `online-user status-${user.status}`;
                userDiv.innerHTML = `
                    <div class="status-dot"></div>
                    <a href="/blog.php?user=${encodeURIComponent(user.username)}" class="username">
                        ${escapeHtml(user.display_name || user.username)}
                    </a>
                    <small style="color: #666;">
                        (${user.role_display})
                    </small>
                `;
                userList.appendChild(userDiv);
            });
            
            if (users.length > 10) {
                const moreDiv = document.createElement('div');
                moreDiv.style.textAlign = 'center';
                moreDiv.style.marginTop = '10px';
                moreDiv.innerHTML = `<small style="color: #888;">+${users.length - 10} more online</small>`;
                userList.appendChild(moreDiv);
            }
        }
        
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    </script>
</body>
</html>
