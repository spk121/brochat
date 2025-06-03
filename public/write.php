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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write Post - BroChat</title>
    <meta name="description" content="Share your punk rock thoughts with the community">
    
    <style>
        /* Punk Rock Write Post Styles */
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
            max-width: 1000px;
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
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
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
        }
        
        /* Main content */
        .write-container {
            background: #111;
            border: 2px solid #333;
            border-radius: 5px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .punk-quote {
            background: rgba(139, 0, 0, 0.3);
            padding: 15px;
            border-left: 4px solid #ff0000;
            margin-bottom: 30px;
            border-radius: 5px;
        }
        
        .quote-text {
            font-style: italic;
            text-align: center;
            color: #ddd;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .section-title {
            color: #ff0000;
            font-size: 1.2em;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #ff0000;
            font-weight: bold;
            font-size: 1em;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 15px;
            background: #000;
            border: 2px solid #333;
            color: #fff;
            border-radius: 5px;
            font-family: inherit;
            font-size: 1.1em;
            line-height: 1.6;
            resize: vertical;
            min-height: 300px;
            transition: border-color 0.3s;
        }
        
        .form-group textarea:focus {
            border-color: #ff0000;
            outline: none;
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.3);
        }
        
        .form-group textarea::placeholder {
            color: #666;
            font-style: italic;
        }
        
        .char-counter {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
            font-size: 0.9em;
        }
        
        .char-count {
            color: #888;
        }
        
        .char-count.warning {
            color: #ffff00;
        }
        
        .char-count.error {
            color: #ff0000;
        }
        
        .content-info {
            color: #666;
            font-size: 0.8em;
        }
        
        /* Photo upload */
        .photo-upload {
            border: 2px dashed #333;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            background: #0a0a0a;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .photo-upload:hover {
            border-color: #ff0000;
            background: rgba(255, 0, 0, 0.05);
        }
        
        .photo-upload.dragover {
            border-color: #ff0000;
            background: rgba(255, 0, 0, 0.1);
        }
        
        .photo-upload input[type="file"] {
            display: none;
        }
        
        .upload-text {
            color: #888;
            margin-bottom: 10px;
        }
        
        .upload-icon {
            font-size: 3em;
            color: #333;
            margin-bottom: 15px;
        }
        
        .upload-details {
            font-size: 0.8em;
            color: #666;
        }
        
        .photo-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .preview-item {
            position: relative;
            border: 2px solid #333;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .preview-image {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }
        
        .remove-photo {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 0, 0, 0.8);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            font-size: 0.8em;
        }
        
        /* Suggestions */
        .suggestions {
            background: #0a0a0a;
            border: 1px solid #333;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .suggestions h4 {
            color: #ff0000;
            margin-bottom: 10px;
            font-size: 0.9em;
            text-transform: uppercase;
        }
        
        .suggestion-tags, .suggestion-users {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .suggestion-item {
            background: #333;
            color: #fff;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .suggestion-item:hover {
            background: #ff0000;
        }
        
        /* Buttons */
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            background: #ff0000;
            color: #fff;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-family: inherit;
            font-size: 1.1em;
            font-weight: bold;
            text-transform: uppercase;
            transition: all 0.3s;
            letter-spacing: 1px;
            min-width: 150px;
        }
        
        .btn:hover {
            background: #cc0000;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 0, 0, 0.4);
        }
        
        .btn-secondary {
            background: #333;
        }
        
        .btn-secondary:hover {
            background: #555;
        }
        
        .btn:disabled {
            background: #666;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* Errors */
        .errors {
            background: rgba(255, 0, 0, 0.1);
            border: 2px solid #ff0000;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .error-item {
            margin-bottom: 5px;
            color: #ff6666;
        }
        
        .error-item:last-child {
            margin-bottom: 0;
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
        
        .flash-punk {
            background: rgba(255, 0, 255, 0.1);
            border-color: #ff00ff;
            color: #ff00ff;
        }
        
        /* Draft indicator */
        .draft-info {
            background: rgba(255, 255, 0, 0.1);
            border: 1px solid #ffff00;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            color: #ffff00;
            font-size: 0.9em;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .write-container {
                padding: 20px 15px;
            }
            
            .nav {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .form-group textarea {
                min-height: 250px;
                font-size: 1em;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <h1>✍️ WRITE POST ✍️</h1>
            <div class="subtitle">Share Your Punk Rock Thoughts</div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav">
        <div class="container">
            <div class="nav-links">
                <a href="/">Home</a>
                <a href="/blog.php">Blog</a>
                <a href="/write.php" class="active">Write</a>
                <a href="/chat.php">Chat</a>
                <a href="/stream.php">Stream</a>
            </div>
            
            <div class="user-info">
                <?= escape_html(current_user()['display_name'] ?: current_user()['username']) ?>
                (<?= BroChatRoles::get_role_display_name(current_user()['role']) ?>)
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
                            <small style="color: #888; font-weight: normal;">(1000 characters max)</small>
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
                        </div>
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
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <!-- Popular tags -->
                        <div>
                            <h4>🏷️ Popular Tags</h4>
                            <div class="suggestion-tags">
                                <?php foreach ($popular_tags as $tag): ?>
                                    <span class="suggestion-item" onclick="insertTag('<?= escape_html($tag['name']) ?>')">
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
                                    <span class="suggestion-item" onclick="insertMention('<?= escape_html($user['username']) ?>')">
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

    <script>
        // Form elements
        const form = document.getElementById('writeForm');
        const contentTextarea = document.getElementById('content');
        const charCount = document.getElementById('charCount');
        const publishBtn = document.getElementById('publishBtn');
        const draftBtn = document.getElementById('draftBtn');
        const photoUpload = document.getElementById('photoUpload');
        const photoInput = document.getElementById('photos');
        const photoPreview = document.getElementById('photoPreview');
        
        let selectedFiles = [];
        
        // Character counter
        function updateCharCount() {
            const count = contentTextarea.value.length;
            charCount.textContent = count + ' / 1000';
            
            charCount.className = 'char-count';
            if (count > 800) {
                charCount.className += ' warning';
            }
            if (count >= 1000) {
                charCount.className += ' error';
            }
            
            // Update publish button state
            publishBtn.disabled = count === 0 || count > 1000;
        }
        
        contentTextarea.addEventListener('input', updateCharCount);
        updateCharCount(); // Initial count
        
        // Auto-save draft every 30 seconds
        let autoSaveTimer;
        function startAutoSave() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                if (contentTextarea.value.trim().length > 10) {
                    saveDraft(true); // Silent save
                }
                startAutoSave(); // Schedule next save
            }, 30000);
        }
        
        contentTextarea.addEventListener('input', () => {
            startAutoSave();
        });
        
        // Save draft function
        function saveDraft(silent = false) {
            const content = contentTextarea.value.trim();
            if (!content) return;
            
            fetch('/api/save-draft.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    context: 'blog_post',
                    content: content,
                    metadata: {
                        photos: selectedFiles.map(f => f.name),
                        timestamp: Date.now() / 1000
                    },
                    csrf_token: '<?= csrf_token() ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && !silent) {
                    showMessage('Draft saved! 📝', 'success');
                }
            })
            .catch(err => {
                if (!silent) {
                    console.error('Failed to save draft:', err);
                }
            });
        }
        
        // Photo upload handling
        if (photoUpload && photoInput) {
            photoUpload.addEventListener('click', () => {
                photoInput.click();
            });
            
            photoInput.addEventListener('change', handleFileSelect);
            
            // Drag and drop
            photoUpload.addEventListener('dragover', (e) => {
                e.preventDefault();
                photoUpload.classList.add('dragover');
            });
            
            photoUpload.addEventListener('dragleave', () => {
                photoUpload.classList.remove('dragover');
            });
            
            photoUpload.addEventListener('drop', (e) => {
                e.preventDefault();
                photoUpload.classList.remove('dragover');
                
                const files = Array.from(e.dataTransfer.files);
                handleFiles(files);
            });
        }
        
        function handleFileSelect(e) {
            const files = Array.from(e.target.files);
            handleFiles(files);
        }
        
        function handleFiles(files) {
            // Limit to 4 photos
            const remainingSlots = 4 - selectedFiles.length;
            const filesToAdd = files.slice(0, remainingSlots);
            
            filesToAdd.forEach(file => {
                if (file.type.startsWith('image/') && file.size <= 5242880) { // 5MB
                    selectedFiles.push(file);
                    createPhotoPreview(file);
                } else {
                    showMessage('Invalid file: ' + file.name + ' (must be image, max 5MB)', 'error');
                }
            });
            
            updatePhotoInput();
        }
        
        function createPhotoPreview(file) {
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';
            
            const img = document.createElement('img');
            img.className = 'preview-image';
            img.src = URL.createObjectURL(file);
            
            const removeBtn = document.createElement('button');
            removeBtn.className = 'remove-photo';
            removeBtn.textContent = '×';
            removeBtn.type = 'button';
            removeBtn.onclick = () => removePhoto(file, previewItem);
            
            previewItem.appendChild(img);
            previewItem.appendChild(removeBtn);
            photoPreview.appendChild(previewItem);
        }
        
        function removePhoto(file, previewElement) {
            selectedFiles = selectedFiles.filter(f => f !== file);
            photoPreview.removeChild(previewElement);
            updatePhotoInput();
            URL.revokeObjectURL(previewElement.querySelector('img').src);
        }
        
        function updatePhotoInput() {
            // Create new file list
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            photoInput.files = dt.files;
        }
        
        // Suggestion insertion
        function insertTag(tag) {
            const cursorPos = contentTextarea.selectionStart;
            const textBefore = contentTextarea.value.substring(0, cursorPos);
            const textAfter = contentTextarea.value.substring(cursorPos);
            
            // Check if we need a space before the hashtag
            const needsSpace = textBefore.length > 0 && !textBefore.endsWith(' ') && !textBefore.endsWith('\n');
            const insertion = (needsSpace ? ' ' : '') + '#' + tag + ' ';
            
            contentTextarea.value = textBefore + insertion + textAfter;
            contentTextarea.selectionStart = contentTextarea.selectionEnd = cursorPos + insertion.length;
            contentTextarea.focus();
            updateCharCount();
        }
        
        function insertMention(username) {
            const cursorPos = contentTextarea.selectionStart;
            const textBefore = contentTextarea.value.substring(0, cursorPos);
            const textAfter = contentTextarea.value.substring(cursorPos);
            
            const needsSpace = textBefore.length > 0 && !textBefore.endsWith(' ') && !textBefore.endsWith('\n');
            const insertion = (needsSpace ? ' ' : '') + '@' + username + ' ';
            
            contentTextarea.value = textBefore + insertion + textAfter;
            contentTextarea.selectionStart = contentTextarea.selectionEnd = cursorPos + insertion.length;
            contentTextarea.focus();
            updateCharCount();
        }
        
        // Form submission
        form.addEventListener('submit', function(e) {
            const isPublish = e.submitter === publishBtn;
            const isDraft = e.submitter === draftBtn;
            
            if (isPublish) {
                publishBtn.disabled = true;
                publishBtn.textContent = 'Publishing...';
            } else if (isDraft) {
                draftBtn.disabled = true;
                draftBtn.textContent = 'Saving...';
            }
            
            // Re-enable buttons after 10 seconds
            setTimeout(() => {
                publishBtn.disabled = false;
                publishBtn.textContent = '🤘 Publish Post';
                draftBtn.disabled = false;
                draftBtn.textContent = '📝 Save Draft';
            }, 10000);
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+Enter to publish
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                if (!publishBtn.disabled) {
                    form.requestSubmit(publishBtn);
                }
            }
            
            // Ctrl+S to save draft
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                form.requestSubmit(draftBtn);
            }
            
            // ESC to go back
            if (e.key === 'Escape') {
                if (confirm('Leave without saving? Any unsaved changes will be lost.')) {
                    window.location.href = '/blog.php';
                }
            }
        });
        
        // Show message helper
        function showMessage(message, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `flash-message flash-${type}`;
            messageDiv.textContent = message;
            
            const container = document.querySelector('.container');
            const firstChild = container.firstElementChild;
            container.insertBefore(messageDiv, firstChild);
            
            // Auto-remove after 5 seconds
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
            }, 5000);
        }
        
        // Warn about unsaved changes
        let hasUnsavedChanges = false;
        
        contentTextarea.addEventListener('input', () => {
            hasUnsavedChanges = true;
        });
        
        form.addEventListener('submit', () => {
            hasUnsavedChanges = false;
        });
        
        window.addEventListener('beforeunload', (e) => {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
        
        // Auto-focus content textarea
        contentTextarea.focus();
        
        // Start auto-save if there's content
        if (contentTextarea.value.trim().length > 0) {
            startAutoSave();
        }
        
        // Preview mode toggle
        let previewMode = false;
        
        function togglePreview() {
            const previewBtn = document.getElementById('previewBtn');
            const previewDiv = document.getElementById('contentPreview');
            
            if (previewMode) {
                // Switch back to edit mode
                contentTextarea.style.display = 'block';
                previewDiv.style.display = 'none';
                previewBtn.textContent = '👁️ Preview';
                previewMode = false;
            } else {
                // Switch to preview mode
                const content = contentTextarea.value;
                
                // Simple markdown preview (basic implementation)
                let preview = content
                    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\*(.+?)\*/g, '<em>$1</em>')
                    .replace(/`(.+?)`/g, '<code>$1</code>')
                    .replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" target="_blank">$1</a>')
                    .replace(/#([a-zA-Z0-9_-]+)/g, '<span style="color: #0096ff">#$1</span>')
                    .replace(/@([a-zA-Z0-9_.-]+)/g, '<span style="color: #00ff00">@$1</span>')
                    .replace(/\n/g, '<br>');
                
                if (!previewDiv) {
                    const newPreviewDiv = document.createElement('div');
                    newPreviewDiv.id = 'contentPreview';
                    newPreviewDiv.style.cssText = `
                        background: #000; border: 2px solid #333; color: #fff; 
                        padding: 15px; border-radius: 5px; min-height: 300px;
                        font-family: inherit; font-size: 1.1em; line-height: 1.6;
                        display: none;
                    `;
                    contentTextarea.parentNode.insertBefore(newPreviewDiv, contentTextarea.nextSibling);
                }
                
                document.getElementById('contentPreview').innerHTML = preview || '<em style="color: #666;">Nothing to preview yet...</em>';
                contentTextarea.style.display = 'none';
                document.getElementById('contentPreview').style.display = 'block';
                previewBtn.textContent = '✏️ Edit';
                previewMode = true;
            }
        }
        
        // Add preview button
        const charCounter = document.querySelector('.char-counter');
        const previewBtn = document.createElement('button');
        previewBtn.type = 'button';
        previewBtn.id = 'previewBtn';
        previewBtn.textContent = '👁️ Preview';
        previewBtn.style.cssText = `
            background: #333; color: #fff; border: 1px solid #555; 
            padding: 5px 10px; border-radius: 3px; cursor: pointer;
            font-size: 0.8em; margin-left: 10px;
        `;
        previewBtn.onclick = togglePreview;
        charCounter.appendChild(previewBtn);
    </script>
</body>
</html>
