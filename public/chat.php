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
    
    <style>
        /* Single Post Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: #000;
            color: #fff;
            line-height: 1.5;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .header {
            background: linear-gradient(45deg, #8B0000, #000);
            padding: 15px 0;
            border-bottom: 3px solid #ff0000;
            margin-bottom: 30px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .header h1 {
            font-size: 1.8em;
            color: #ff0000;
            text-shadow: 2px 2px 4px #000;
        }
        
        .breadcrumb {
            color: #ccc;
            font-size: 0.9em;
        }
        
        .breadcrumb a {
            color: #ff0000;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Main post */
        .post-container {
            background: #111;
            border: 2px solid #333;
            border-radius: 5px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .post-header {
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        
        .author-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .author-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #ff0000, #8B0000);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.5em;
            text-transform: uppercase;
        }
        
        .author-details h2 {
            color: #ff0000;
            margin-bottom: 5px;
            font-size: 1.3em;
        }
        
        .author-details .role {
            color: #888;
            text-transform: uppercase;
            font-size: 0.9em;
            margin-bottom: 3px;
        }
        
        .author-details .member-since {
            color: #666;
            font-size: 0.8em;
        }
        
        .post-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .post-date {
            color: #888;
            font-size: 0.9em;
        }
        
        .post-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            background: #333;
            color: #fff;
            border: 1px solid #555;
            padding: 8px 15px;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            border-color: #ff0000;
            background: rgba(255, 0, 0, 0.1);
        }
        
        .action-btn.delete {
            border-color: #ff0000;
            color: #ff0000;
        }
        
        .action-btn.delete:hover {
            background: #ff0000;
            color: #fff;
        }
        
        /* Post content */
        .post-content {
            font-size: 1.2em;
            line-height: 1.7;
            margin-bottom: 25px;
            color: #fff;
        }
        
        .post-content h1, .post-content h2, .post-content h3,
        .post-content h4, .post-content h5, .post-content h6 {
            color: #ff0000;
            margin: 20px 0 10px 0;
        }
        
        .post-content p {
            margin-bottom: 15px;
        }
        
        .post-content a {
            color: #0096ff;
        }
        
        .post-content code {
            background: #333;
            padding: 2px 5px;
            border-radius: 3px;
            color: #00ff00;
        }
        
        .post-content strong {
            color: #ff6666;
        }
        
        /* Post photos */
        .post-photos {
            margin-bottom: 25px;
        }
        
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .photo-item {
            position: relative;
            border: 2px solid #333;
            border-radius: 5px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .photo-item:hover {
            border-color: #ff0000;
            transform: scale(1.02);
        }
        
        .photo-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .photo-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            padding: 10px;
            color: #fff;
            font-size: 0.8em;
        }
        
        /* Post tags */
        .post-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tag {
            background: #333;
            color: #fff;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.9em;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .tag:hover {
            background: #ff0000;
            transform: translateY(-2px);
        }
        
        /* Sidebar */
        .sidebar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
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
        
        /* Related posts */
        .related-post {
            display: flex;
            gap: 10px;
            padding: 10px;
            border-radius: 3px;
            transition: background 0.3s;
            margin-bottom: 10px;
            border-bottom: 1px solid #333;
        }
        
        .related-post:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .related-post:hover {
            background: #222;
        }
        
        .related-post-thumb {
            width: 50px;
            height: 50px;
            background: #333;
            border-radius: 3px;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .related-post-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .related-post-info {
            flex: 1;
            min-width: 0;
        }
        
        .related-post-title {
            color: #fff;
            text-decoration: none;
            font-size: 0.9em;
            line-height: 1.3;
            display: block;
            margin-bottom: 3px;
        }
        
        .related-post-title:hover {
            color: #ff0000;
        }
        
        .related-post-meta {
            color: #888;
            font-size: 0.8em;
        }
        
        /* Author info */
        .author-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            background: #0a0a0a;
            border-radius: 3px;
        }
        
        .stat-number {
            display: block;
            font-size: 1.5em;
            color: #ff0000;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 0.8em;
            color: #888;
            text-transform: uppercase;
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
        
        /* Navigation */
        .post-navigation {
            background: #111;
            border: 2px solid #333;
            border-radius: 5px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }
        
        .nav-link {
            color: #ff0000;
            text-decoration: none;
            font-weight: bold;
            padding: 10px 20px;
            border: 1px solid #333;
            border-radius: 3px;
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            border-color: #ff0000;
            background: rgba(255, 0, 0, 0.1);
        }
        
        .nav-link.disabled {
            color: #666;
            cursor: not-allowed;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header-content {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .post-container {
                padding: 20px 15px;
            }
            
            .author-info {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .post-meta {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .sidebar {
                grid-template-columns: 1fr;
            }
            
            .post-navigation {
                flex-direction: column;
            }
        }
        
        /* Delete confirmation modal */
        .delete-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .delete-modal-content {
            background: #111;
            border: 3px solid #ff0000;
            border-radius: 5px;
            padding: 30px;
            max-width: 400px;
            text-align: center;
        }
        
        .delete-modal h3 {
            color: #ff0000;
            margin-bottom: 15px;
        }
        
        .delete-modal p {
            color: #ccc;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .delete-modal-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .btn-danger {
            background: #ff0000;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-cancel {
            background: #333;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
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
                            <a href="/blog.php?user=<?= urlencode($post['username']) ?>" style="color: #ff0000; text-decoration: none;">
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
                        <?php if ($can_delete): ?>
                            <button class="action-btn delete" onclick="showDeleteModal()">
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
                            <div class="photo-item" onclick="viewPhoto('/uploads/photos/<?= escape_html($photo['filename']) ?>')">
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
        <div class="sidebar">
            <!-- Author Info -->
            <div class="sidebar-section">
                <div class="section-header">🎸 About the Author</div>
                <div class="section-content">
                    <?php 
                    $user_activity = get_user_activity($post['user_id']);
                    $user_profile = get_user_punk_profile($post['user_id']);
                    ?>
                    
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
                    
                    <div style="text-align: center;">
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
                        <p style="color: #888; text-align: center;">No related posts found</p>
                    <?php else: ?>
                        <?php foreach ($related_posts as $related): ?>
                            <div class="related-post">
                                <div class="related-post-thumb">
                                    <?php if (!empty($related['photos'])): ?>
                                        <img src="/uploads/previews/<?= escape_html($related['photos'][0]['preview_filename']) ?>" alt="">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; background: #333; display: flex; align-items: center; justify-content: center; color: #666; font-size: 0.8em;">
                                            #
                                        </div>
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
            <div class="sidebar-section" style="grid-column: 1 / -1;">
                <div class="section-header">📝 More from <?= escape_html($post['display_name'] ?: $post['username']) ?></div>
                <div class="section-content">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                        <?php foreach (array_slice($author_posts, 0, 4) as $author_post): ?>
                            <div class="related-post">
                                <div class="related-post-thumb">
                                    <?php if (!empty($author_post['photos'])): ?>
                                        <img src="/uploads/previews/<?= escape_html($author_post['photos'][0]['preview_filename']) ?>" alt="">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; background: #333; display: flex; align-items: center; justify-content: center; color: #666;">
                                            📝
                                        </div>
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
            <div style="text-align: center; color: #888;">
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
                        <button type="button" class="btn-cancel" onclick="hideDeleteModal()">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

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
                document.body.style.overflow = '';
            });
            
            document.body.appendChild(modal);
            document.body.style.overflow = 'hidden';
            
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
        
        // Delete modal functions
        function showDeleteModal() {
            document.getElementById('deleteModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.body.style.overflow = '';
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // ESC to close any modal
            if (e.key === 'Escape') {
                const deleteModal = document.getElementById('deleteModal');
                if (deleteModal && deleteModal.style.display === 'flex') {
                    hideDeleteModal();
                }
            }
            
            // B to go back to blog
            if (e.key === 'b' || e.key === 'B') {
                window.location.href = '/blog.php';
            }
            
            // W to write new post (if logged in)
            if ((e.key === 'w' || e.key === 'W') && e.altKey) {
                e.preventDefault();
                <?php if ($is_logged_in && can_write_blog()): ?>
                    window.location.href = '/write.php';
                <?php endif; ?>
            }
        });
        
        // Copy link to clipboard
        function copyPostLink() {
            const url = "<?= BROCHAT_BASE_URL ?>/post/<?= escape_html($post['slug']) ?>";
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    showMessage('Post link copied to clipboard! 🤘', 'success');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = url;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showMessage('Post link copied to clipboard! 🤘', 'success');
            }
        }
        
        // Show message helper
        function showMessage(message, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `flash-message flash-${type}`;
            messageDiv.textContent = message;
            
            const container = document.querySelector('.container');
            const firstChild = container.firstElementChild;
            container.insertBefore(messageDiv, firstChild);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.style.transition = 'opacity 0.5s';
                    messageDiv.style.opacity = '0';
                    setTimeout(() => {
                        if (messageDiv.parentNode) {
                            messageDiv.parentNode.removeChild(messageDiv);
                        }
                    }, 500);
                }
            }, 3000);
        }
        
        // Add copy link button to post actions
        const postActions = document.querySelector('.post-actions');
        if (postActions) {
            const copyBtn = document.createElement('button');
            copyBtn.className = 'action-btn';
            copyBtn.textContent = '🔗 Copy Link';
            copyBtn.onclick = copyPostLink;
            postActions.appendChild(copyBtn);
        }
        
        // Lazy loading for related post images
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
    </script>
</body>
</html>
