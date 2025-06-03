<?php
require_once __DIR__ . '/bootstrap.php';

// Get post slug from URL
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    flash_error('Post not found');
    redirect('/blog.php');
}

// Get the post
$post = get_blog_post_by_slug($slug);
if (!$post) {
    http_response_code(404);
    flash_error('Post not found');
    redirect('/blog.php');
}

// Track page view
track_page_view('single_post', $post['id']);

// Get author's other recent posts
$author_posts = get_blog_posts_by_user($post['user_id'], 5);
// Remove current post from the list
$author_posts = array_filter($author_posts, function($p) use ($post) {
    return $p['id'] !== $post['id'];
});

// Get related posts by tags
$related_posts = [];
if (!empty($post['tags'])) {
    foreach ($post['tags'] as $tag) {
        $tag_posts = get_blog_posts_by_tag($tag['name'], 3);
        foreach ($tag_posts as $tag_post) {
            if ($tag_post['id'] !== $post['id']) {
                $related_posts[] = $tag_post;
            }
        }
    }
    // Remove duplicates and limit
    $related_posts = array_slice(array_unique($related_posts, SORT_REGULAR), 0, 5);
}

// Get user info
$current_user = current_user();
$is_logged_in = is_logged_in();

// Can current user delete this post?
$can_delete = $is_logged_in && can_delete_blog_post($post['id']);

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    require_csrf();
    
    if ($can_delete) {
        if (delete_blog_post($post['id'], $current_user['id'])) {
            flash_success('Post deleted successfully');
            redirect('/blog.php');
        } else {
            flash_error('Failed to delete post');
        }
    } else {
        flash_error('You do not have permission to delete this post');
    }
}

// Get additional data for sidebar
$user_activity = get_user_activity($post['user_id']);
$user_profile = get_user_punk_profile($post['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape_html(truncate_text(strip_tags($post['content']), 60)) ?> - BroChat</title>
    <meta name="description" content="<?= escape_html(truncate_text(strip_tags($post['content']), 160)) ?>">
    <meta name="author" content="<?= escape_html($post['display_name'] ?: $post['username']) ?>">
    
    <!-- Open Graph meta tags -->
    <meta property="og:title" content="<?= escape_html(truncate_text(strip_tags($post['content']), 60)) ?>">
    <meta property="og:description" content="<?= escape_html(truncate_text(strip_tags($post['content']), 160)) ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= BROCHAT_BASE_URL ?>/post/<?= escape_html($post['slug']) ?>">
    <?php if (!empty($post['photos'])): ?>
        <meta property="og:image" content="<?= BROCHAT_BASE_URL ?>/uploads/photos/<?= escape_html($post['photos'][0]['filename']) ?>">
    <?php endif; ?>
    
    <!-- Security headers via meta tags -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';">
    
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="post-page">
    <!-- Header -->
    <header class="post-header-nav">
        <div class="header-content">
            <h1>🤘 BROCHAT 🤘</h1>
            <div class="breadcrumb">
                <a href="/">Home</a> > 
                <a href="/blog.php">Blog</a> > 
                <span><?= escape_html($post['username']) ?></span>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Flash Messages -->
        <?php if (flash_has()): ?>
            <div class="flash-messages">
                <?php foreach (flash_get() as $type => $messages): ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="flash-message flash-<?= $type ?>">
                            <?= $message ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Main Post -->
        <article class="post-container">
            <header class="post-header">
                <div class="author-info">
                    <div class="author-avatar">
                        <?= strtoupper(substr($post['username'], 0, 1)) ?>
                    </div>
                    <div class="author-details">
                        <h2>
                            <a href="/blog.php?user=<?= urlencode($post['username']) ?>" class="author-link">
                                <?= escape_html($post['display_name'] ?: $post['username']) ?>
                            </a>
                        </h2>
                        <div class="role"><?= BroChatRoles::get_role_display_name($post['role'] ?? 'fan') ?></div>
                        <div class="member-since">Member since <?= date('M Y', strtotime($post['created_at'])) ?></div>
                    </div>
                </div>
                
                <div class="post-meta">
                    <div class="post-date">
                        Posted <?= time_ago($post['created_at']) ?>
                        <?php if ($post['updated_at'] !== $post['created_at']): ?>
                            • Updated <?= time_ago($post['updated_at']) ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="post-actions">
                        <a href="/blog.php" class="action-btn">← Back to Blog</a>
                        <button class="action-btn" id="copyLinkBtn">🔗 Copy Link</button>
                        <?php if ($can_delete): ?>
                            <button class="action-btn delete" id="deletePostBtn">
                                🗑️ Delete Post
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </header>
            
            <!-- Post Content -->
            <div class="post-content">
                <?= $post['parsed_content'] ?>
            </div>
            
            <!-- Post Photos -->
            <?php if (!empty($post['photos'])): ?>
                <div class="post-photos">
                    <div class="photos-grid">
                        <?php foreach ($post['photos'] as $photo): ?>
                            <div class="photo-item" data-full-src="/uploads/photos/<?= escape_html($photo['filename']) ?>">
                                <img 
                                    src="/uploads/previews/<?= escape_html($photo['preview_filename']) ?>" 
                                    alt="Photo by <?= escape_html($post['username']) ?>"
                                    class="photo-image"
                                    loading="lazy"
                                >
                                <div class="photo-overlay">
                                    <?= format_file_size($photo['file_size']) ?> • Click to view full size
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Post Tags -->
            <?php if (!empty($post['tags'])): ?>
                <div class="post-tags">
                    <?php foreach ($post['tags'] as $tag): ?>
                        <a href="/blog.php?tag=<?= urlencode($tag['name']) ?>" class="tag">
                            #<?= escape_html($tag['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <!-- Sidebar with Author Info and Related Posts -->
        <div class="post-sidebar">
            <!-- Author Info -->
            <div class="sidebar-section">
                <div class="section-header">🎸 About the Author</div>
                <div class="section-content">
                    <div class="author-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?= $user_activity['blog_posts'] ?></span>
                            <span class="stat-label">Posts</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= $user_activity['chat_messages'] ?></span>
                            <span class="stat-label">Messages</span>
                        </div>
                    </div>
                    
                    <div class="author-link-container">
                        <a href="/blog.php?user=<?= urlencode($post['username']) ?>" class="action-btn">
                            View All Posts by <?= escape_html($post['display_name'] ?: $post['username']) ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Related Posts -->
            <div class="sidebar-section">
                <div class="section-header">🏷️ Related Posts</div>
                <div class="section-content">
                    <?php if (empty($related_posts)): ?>
                        <p class="no-content">No related posts found</p>
                    <?php else: ?>
                        <?php foreach ($related_posts as $related): ?>
                            <div class="related-post">
                                <div class="related-post-thumb">
                                    <?php if (!empty($related['photos'])): ?>
                                        <img src="/uploads/previews/<?= escape_html($related['photos'][0]['preview_filename']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="thumb-placeholder">#</div>
                                    <?php endif; ?>
                                </div>
                                <div class="related-post-info">
                                    <a href="/post/<?= escape_html($related['slug']) ?>" class="related-post-title">
                                        <?= escape_html(truncate_text(strip_tags($related['content']), 60)) ?>
                                    </a>
                                    <div class="related-post-meta">
                                        by <?= escape_html($related['username']) ?> • <?= time_ago($related['created_at']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Author's Other Posts -->
        <?php if (!empty($author_posts)): ?>
            <div class="sidebar-section author-posts-section">
                <div class="section-header">📝 More from <?= escape_html($post['display_name'] ?: $post['username']) ?></div>
                <div class="section-content">
                    <div class="author-posts-grid">
                        <?php foreach (array_slice($author_posts, 0, 4) as $author_post): ?>
                            <div class="related-post">
                                <div class="related-post-thumb">
                                    <?php if (!empty($author_post['photos'])): ?>
                                        <img src="/uploads/previews/<?= escape_html($author_post['photos'][0]['preview_filename']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="thumb-placeholder">📝</div>
                                    <?php endif; ?>
                                </div>
                                <div class="related-post-info">
                                    <a href="/post/<?= escape_html($author_post['slug']) ?>" class="related-post-title">
                                        <?= escape_html(truncate_text(strip_tags($author_post['content']), 80)) ?>
                                    </a>
                                    <div class="related-post-meta">
                                        <?= time_ago($author_post['created_at']) ?>
                                        <?php if (!empty($author_post['tags'])): ?>
                                            • <?= count($author_post['tags']) ?> tags
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Navigation -->
        <div class="post-navigation">
            <a href="/blog.php" class="nav-link">← All Posts</a>
            <div class="share-info">
                <small>Share this post: <strong><?= BROCHAT_BASE_URL ?>/post/<?= escape_html($post['slug']) ?></strong></small>
            </div>
            <?php if ($is_logged_in && can_write_blog()): ?>
                <a href="/write.php" class="nav-link">Write New Post →</a>
            <?php else: ?>
                <div class="nav-link disabled">End of posts</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <?php if ($can_delete): ?>
        <div class="delete-modal" id="deleteModal" style="display: none;">
            <div class="delete-modal-content">
                <h3>💀 Delete Post</h3>
                <p>Are you sure you want to delete this post? This action cannot be undone and will remove all photos and data associated with it.</p>
                <p><strong>The punk rock community will lose this content forever!</strong></p>
                
                <form method="POST" style="display: inline;">
                    <?= csrf_field() ?>
                    <div class="delete-modal-actions">
                        <button type="submit" name="delete_post" value="1" class="btn-danger">
                            💀 Yes, Delete It
                        </button>
                        <button type="button" class="btn-cancel" id="cancelDeleteBtn">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Pass data to JavaScript -->
    <script id="post-data" type="application/json">
        {
            "csrf_token": "<?= escape_js(csrf_token()) ?>",
            "post_url": "<?= escape_js(BROCHAT_BASE_URL . '/post/' . $post['slug']) ?>",
            "can_delete": <?= $can_delete ? 'true' : 'false' ?>,
            "can_write": <?= ($is_logged_in && can_write_blog()) ? 'true' : 'false' ?>,
            "is_logged_in": <?= $is_logged_in ? 'true' : 'false' ?>
        }
    </script>
    <script src="/assets/js/post.js"></script>
</body>
</html>
