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

// Build pagination URLs
$base_url = '/blog.php';
$params = [];
if ($tag) $params['tag'] = $tag;
if ($user) $params['user'] = $user;
if ($search) $params['search'] = $search;

$query_string = empty($params) ? '' : '?' . http_build_query($params);
$page_param = empty($params) ? '?page=' : '&page=';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape_html($page_title) ?> - BroChat Blog</title>
    <meta name="description" content="Punk rock community blog - share your thoughts, photos, and connect with fellow punks">
    
    <!-- Security headers via meta tags -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';">
    
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="blog-page">
    <!-- Header -->
    <header class="blog-header">
        <div class="container">
            <h1>🤘 BROCHAT BLOG 🤘</h1>
            <div class="subtitle"><?= escape_html($page_title) ?></div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="blog-nav">
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
                    | <a href="/logout.php" class="logout-link">Logout</a>
                <?php else: ?>
                    <a href="/login.php" class="auth-link">Login</a> |
                    <a href="/register.php" class="auth-link">Join</a>
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
                <form class="search-form" method="GET" id="searchForm">
                    <input 
                        type="text" 
                        name="search" 
                        id="searchInput"
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
                        <a href="/blog.php" class="clear-filter">Show All</a>
                    <?php elseif ($user): ?>
                        Showing posts by: <strong><?= escape_html($user_data['display_name'] ?: $user_data['username']) ?></strong>
                        <a href="/blog.php" class="clear-filter">Show All</a>
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
                            <p>Try different keywords or <a href="/blog.php" class="empty-link">browse all posts</a></p>
                        <?php elseif ($tag): ?>
                            <p>No posts tagged with "<strong>#<?= escape_html($tag) ?></strong>" yet.</p>
                            <p><a href="/blog.php" class="empty-link">Browse all posts</a> or be the first to use this tag!</p>
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
                                            data-full-src="/uploads/photos/<?= escape_html($photo['filename']) ?>"
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
                                        <button class="action-btn delete-btn" data-post-id="<?= $post['id'] ?>">
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
                            <p class="no-content">No tags yet</p>
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
                            <p class="no-content">No one's around</p>
                        <?php else: ?>
                            <div class="user-list" id="onlineUsersList">
                                <?php foreach (array_slice($online_users, 0, 10) as $user): ?>
                                    <div class="online-user status-<?= $user['status'] ?>">
                                        <div class="status-dot"></div>
                                        <a href="/blog.php?user=<?= urlencode($user['username']) ?>" class="username">
                                            <?= escape_html($user['display_name'] ?: $user['username']) ?>
                                        </a>
                                        <small class="user-role">
                                            (<?= BroChatRoles::get_role_display_name($user['role']) ?>)
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (count($online_users) > 10): ?>
                                    <div class="more-users">
                                        <small>
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
                        <div class="stat-item">
                            <strong class="stat-number"><?= $app_stats['users'] ?></strong> punks joined
                        </div>
                        <div class="stat-item">
                            <strong class="stat-number"><?= $app_stats['blog_posts'] ?></strong> posts shared
                        </div>
                        <div class="stat-item">
                            <strong class="stat-number"><?= $app_stats['chat_messages'] ?></strong> messages sent
                        </div>
                        <div class="stat-item">
                            <strong class="stat-number"><?= count($popular_tags) ?></strong> tags created
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <?php if ($is_logged_in): ?>
                    <div class="sidebar-section">
                        <div class="section-header">⚡ Quick Actions</div>
                        <div class="section-content">
                            <?php if (can_write_blog()): ?>
                                <a href="/write.php" class="btn quick-action-btn">
                                    ✍️ Write New Post
                                </a>
                            <?php endif; ?>
                            <a href="/chat.php" class="btn btn-secondary quick-action-btn">
                                💬 Join Chat
                            </a>
                            <a href="/stream.php" class="btn btn-secondary quick-action-btn">
                                🎵 Listen to Stream
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="sidebar-section">
                        <div class="section-header">🎸 Join the Pit</div>
                        <div class="section-content">
                            <p class="join-description">
                                Ready to share your punk rock thoughts?
                            </p>
                            <a href="/register.php" class="btn quick-action-btn">
                                🤘 Join Community
                            </a>
                            <a href="/login.php" class="btn btn-secondary quick-action-btn">
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
                <!-- Previous page -->
                <?php if ($page > 1): ?>
                    <a href="<?= $base_url . $query_string . $page_param . ($page - 1) ?>" class="page-link">« Previous</a>
                <?php else: ?>
                    <span class="page-link disabled">« Previous</span>
                <?php endif; ?>
                
                <!-- Page numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <a href="<?= $base_url . $query_string . $page_param . '1' ?>" class="page-link">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="page-link">...</span>
                    <?php endif;
                endif;
                
                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="page-link current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= $base_url . $query_string . $page_param . $i ?>" class="page-link"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor;
                
                if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="page-link">...</span>
                    <?php endif; ?>
                    <a href="<?= $base_url . $query_string . $page_param . $total_pages ?>" class="page-link"><?= $total_pages ?></a>
                <?php endif; ?>
                
                <!-- Next page -->
                <?php if ($page < $total_pages): ?>
                    <a href="<?= $base_url . $query_string . $page_param . ($page + 1) ?>" class="page-link">Next »</a>
                <?php else: ?>
                    <span class="page-link disabled">Next »</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pass data to JavaScript -->
    <script id="blog-data" type="application/json">
        {
            "csrf_token": "<?= escape_js(csrf_token()) ?>",
            "current_page": <?= $page ?>,
            "total_pages": <?= $total_pages ?>,
            "can_delete": <?= $is_logged_in ? 'true' : 'false' ?>,
            "filters": {
                "tag": "<?= escape_js($tag ?? '') ?>",
                "user": "<?= escape_js($user ?? '') ?>",
                "search": "<?= escape_js($search ?? '') ?>"
            }
        }
    </script>
    <script src="/assets/js/blog.js"></script>
</body>
</html>
