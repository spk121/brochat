<?php
require_once __DIR__ . '/bootstrap.php';

// Must be logged in to write
require_login('/login.php?redirect=' . urlencode('/write.php'));

// Must have write permission
require_permission('write_blog', 'You need to be a Regular or higher to write blog posts');

// Track page view
track_page_view('write_post');

$errors = [];
$form_data = [];
$success = false;

// Get user's draft if exists
$draft = draft_get('blog_post');
if ($draft) {
    $form_data = [
        'content' => $draft['content'],
        'existing_photos' => $draft['metadata']['photos'] ?? []
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    // Rate limiting for blog posts
    require_brochat_rate_limit('blog_post');
    
    // Get form data
    $form_data = [
        'content' => $_POST['content'] ?? '',
        'save_draft' => isset($_POST['save_draft'])
    ];
    
    // Validate content
    $validation_errors = validate_brochat_input($form_data, [
        'content' => ['required', 'blog_content', 'no_spam']
    ]);
    
    // Additional content validation
    $content_filter = filter_content($form_data['content'], 'blog');
    if (!$content_filter['allowed']) {
        $validation_errors['content'] = ['Content violates community guidelines'];
    }
    
    // Check spam score
    $spam_check = detect_punk_spam($form_data['content'], 'blog');
    if ($spam_check['is_spam']) {
        $validation_errors['content'] = ['Content appears to be spam'];
        log_security_event('spam_content_detected', [
            'type' => 'blog_post',
            'content_preview' => substr($form_data['content'], 0, 100),
            'spam_score' => $spam_check['spam_score'],
            'flags' => $spam_check['flags']
        ], 'medium');
    }
    
    // Handle photo uploads
    $photo_errors = [];
    $uploaded_photos = [];
    
    if (can_upload_photos() && !empty($_FILES['photos']['name'][0])) {
        require_brochat_rate_limit('photo_upload');
        
        // Process up to 4 photos
        for ($i = 0; $i < min(4, count($_FILES['photos']['name'])); $i++) {
            if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                $photo_file = [
                    'name' => $_FILES['photos']['name'][$i],
                    'type' => $_FILES['photos']['type'][$i],
                    'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                    'error' => $_FILES['photos']['error'][$i],
                    'size' => $_FILES['photos']['size'][$i]
                ];
                
                $photo_validation = validate_brochat_photo($photo_file);
                if (empty($photo_validation)) {
                    $uploaded_photos[] = $photo_file;
                } else {
                    $photo_errors = array_merge($photo_errors, $photo_validation);
                }
            }
        }
    }
    
    if ($form_data['save_draft']) {
        // Save as draft
        draft_save('blog_post', $form_data['content'], [
            'photos' => array_map(function($photo) {
                return $photo['name'];
            }, $uploaded_photos),
            'timestamp' => time()
        ]);
        
        flash_success('Draft saved! 📝');
        track_user_action('draft_saved', ['type' => 'blog_post']);
    } else {
        // Publish post
        if (empty($validation_errors) && empty($photo_errors)) {
            try {
                $post_id = create_blog_post(
                    current_user()['id'],
                    $form_data['content'],
                    $uploaded_photos
                );
                
                if ($post_id) {
                    // Clear draft
                    draft_clear('blog_post');
                    
                    // Track milestone
                    $user_activity = get_user_activity(current_user()['id']);
                    if ($user_activity['blog_posts'] == 1) {
                        track_punk_milestone('Posted first blog entry');
                    } elseif ($user_activity['blog_posts'] % 10 == 0) {
                        track_punk_milestone("Posted {$user_activity['blog_posts']} blog entries");
                    }
                    
                    // Track action
                    track_user_action('blog_post_created', [
                        'post_id' => $post_id,
                        'content_length' => mb_strlen($form_data['content'], 'UTF-8'),
                        'photo_count' => count($uploaded_photos),
                        'hashtags' => extract_hashtags($form_data['content']),
                        'mentions' => extract_mentions($form_data['content'])
                    ]);
                    
                    flash_punk('Post published! The pit is now louder! 🤘');
                    
                    // Get the post slug for redirect
                    $post = get_blog_post($post_id);
                    redirect('/post/' . $post['slug']);
                } else {
                    $errors[] = 'Failed to create post. Please try again.';
                }
            } catch (Exception $e) {
                error_log('Blog post creation error: ' . $e->getMessage());
                $errors[] = 'Failed to create post. Please try again.';
            }
        } else {
            // Collect all errors
            foreach ($validation_errors as $field => $field_errors) {
                $errors = array_merge($errors, $field_errors);
            }
            $errors = array_merge($errors, $photo_errors);
        }
    }
}

// Get punk quote
$punk_quote = get_punk_quote();

// Get popular tags for suggestions
$popular_tags = get_popular_tags(10);

// Get recent mentions for suggestions
$recent_users = db_fetch_all(
    'SELECT username FROM users WHERE status = "active" ORDER BY last_login DESC LIMIT 10'
);

// Current user info
$current_user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write Post - BroChat</title>
    <meta name="description" content="Share your punk rock thoughts with the community">
    
    <!-- Security headers via meta tags -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';">
    
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="write-page">
    <!-- Header -->
    <header class="write-header">
        <div class="container">
            <h1>✍️ WRITE POST ✍️</h1>
            <div class="subtitle">Share Your Punk Rock Thoughts</div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="write-nav">
        <div class="container">
            <div class="nav-links">
                <a href="/">Home</a>
                <a href="/blog.php">Blog</a>
                <a href="/write.php" class="active">Write</a>
                <a href="/chat.php">Chat</a>
                <a href="/stream.php">Stream</a>
            </div>
            
            <div class="user-info">
                <?= escape_html($current_user['display_name'] ?: $current_user['username']) ?>
                (<?= BroChatRoles::get_role_display_name($current_user['role']) ?>)
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
                            <?= $message ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Draft indicator -->
        <?php if ($draft): ?>
            <div class="draft-info">
                📝 You have a draft from <?= date('M j, Y g:i A', $draft['timestamp']) ?>. 
                It has been loaded into the editor.
            </div>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $error): ?>
                    <div class="error-item">💀 <?= escape_html($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Punk Quote -->
        <div class="punk-quote">
            <div class="quote-text"><?= escape_html($punk_quote) ?></div>
        </div>

        <!-- Write Form -->
        <div class="write-container">
            <form method="POST" enctype="multipart/form-data" id="writeForm">
                <?= csrf_field() ?>
                
                <!-- Content Section -->
                <div class="form-section">
                    <div class="section-title">🎸 Your Punk Rock Thoughts</div>
                    
                    <div class="form-group">
                        <label for="content">
                            What's on your mind? Let it out! 
                            <small class="char-limit">(1000 characters max)</small>
                        </label>
                        <textarea 
                            id="content" 
                            name="content" 
                            placeholder="Share your punk rock thoughts... 

Use #hashtags to categorize your post
Mention other punks with @username
Support your local punk scene!
Fuck fascists and corporate sellouts!

Remember: 1000 characters max - make every word count! 🤘"
                            maxlength="1000"
                            required
                        ><?= escape_html($form_data['content'] ?? '') ?></textarea>
                        
                        <div class="char-counter">
                            <div class="char-count" id="charCount">0 / 1000</div>
                            <div class="content-info">
                                Markdown supported: **bold**, *italic*, `code`, [links](url)
                            </div>
                            <button type="button" id="previewBtn" class="preview-btn">👁️ Preview</button>
                        </div>
                        
                        <div id="contentPreview" class="content-preview" style="display: none;"></div>
                    </div>
                </div>

                <!-- Photo Upload Section -->
                <?php if (can_upload_photos()): ?>
                    <div class="form-section">
                        <div class="section-title">📸 Photos (Optional)</div>
                        
                        <div class="form-group">
                            <label>Add up to 4 photos to your post</label>
                            
                            <div class="photo-upload" id="photoUpload">
                                <input 
                                    type="file" 
                                    id="photos" 
                                    name="photos[]" 
                                    multiple 
                                    accept="image/*" 
                                    max="4"
                                >
                                <div class="upload-icon">📷</div>
                                <div class="upload-text">
                                    <strong>Click to select photos</strong> or drag and drop
                                </div>
                                <div class="upload-details">
                                    JPG, PNG, GIF, WebP • Max 5MB each • Up to 4 photos
                                </div>
                            </div>
                            
                            <div class="photo-preview" id="photoPreview"></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Suggestions -->
                <div class="suggestions">
                    <div class="suggestions-grid">
                        <!-- Popular tags -->
                        <div>
                            <h4>🏷️ Popular Tags</h4>
                            <div class="suggestion-tags">
                                <?php foreach ($popular_tags as $tag): ?>
                                    <span class="suggestion-item" data-tag="<?= escape_html($tag['name']) ?>">
                                        #<?= escape_html($tag['name']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Recent users -->
                        <div>
                            <h4>👥 Recent Users</h4>
                            <div class="suggestion-users">
                                <?php foreach ($recent_users as $user): ?>
                                    <span class="suggestion-item" data-user="<?= escape_html($user['username']) ?>">
                                        @<?= escape_html($user['username']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="button-group">
                    <button type="submit" class="btn" id="publishBtn">
                        🤘 Publish Post
                    </button>
                    <button type="submit" name="save_draft" value="1" class="btn btn-secondary" id="draftBtn">
                        📝 Save Draft
                    </button>
                    <a href="/blog.php" class="btn btn-secondary">
                        ← Back to Blog
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Pass data to JavaScript -->
    <script id="write-data" type="application/json">
        {
            "csrf_token": "<?= escape_js(csrf_token()) ?>",
            "can_upload_photos": <?= can_upload_photos() ? 'true' : 'false' ?>,
            "popular_tags": <?= json_encode(array_column($popular_tags, 'name')) ?>,
            "recent_users": <?= json_encode(array_column($recent_users, 'username')) ?>,
            "has_draft": <?= $draft ? 'true' : 'false' ?>
        }
    </script>
    <script src="/assets/js/write.js"></script>
</body>
</html>
