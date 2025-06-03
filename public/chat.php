<?php
require_once __DIR__ . '/bootstrap.php';

// Must be logged in to chat
require_login('/login.php?redirect=' . urlencode('/chat.php'));

// Check if user can chat (not muted/banned)
if (!can_chat()) {
    $user = current_user();
    if (is_user_muted($user['id'])) {
        flash_error('You are currently muted and cannot send messages');
    } elseif ($user['banned_until'] && strtotime($user['banned_until']) > time()) {
        flash_error('You are temporarily banned from chatting');
    } else {
        flash_error('You do not have permission to chat');
    }
    redirect('/');
}

// Track page view
track_page_view('chat');

// Mark user as online
mark_user_online();

// Get recent chat messages for initial load
$recent_messages = get_recent_chat_messages(50);

// Get online users
$online_users = get_online_users();

// Get typing users
$typing_users = get_typing_users('chat');

// Get current user info
$current_user = current_user();

// Handle AJAX message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    require_csrf();
    
    if (!can_chat()) {
        echo json_encode(['success' => false, 'error' => 'You cannot send messages']);
        exit;
    }
    
    // Rate limiting for chat messages
    if (!brochat_rate_limit_check('chat_message')) {
        echo json_encode(['success' => false, 'error' => 'Too many messages. Slow down!']);
        exit;
    }
    
    $message = trim($_POST['message'] ?? '');
    $action = $_POST['action'] ?? 'message';
    
    if ($action === 'send_message') {
        if (empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            exit;
        }
        
        // Validate message
        $validation_errors = validate_brochat_input(['message' => $message], [
            'message' => ['required', 'chat_message', 'no_spam']
        ]);
        
        if (!empty($validation_errors)) {
            echo json_encode(['success' => false, 'error' => 'Invalid message content']);
            exit;
        }
        
        // Check for spam
        $spam_check = detect_punk_spam($message, 'chat');
        if ($spam_check['is_spam']) {
            log_security_event('spam_chat_detected', [
                'user_id' => $current_user['id'],
                'message' => substr($message, 0, 100),
                'spam_score' => $spam_check['spam_score']
            ], 'medium');
            echo json_encode(['success' => false, 'error' => 'Message appears to be spam']);
            exit;
        }
        
        // Create the message
        $message_id = create_chat_message($current_user['id'], $message);
        
        if ($message_id) {
            // Track user action
            track_user_action('chat_message_sent', [
                'message_id' => $message_id,
                'message_length' => strlen($message)
            ]);
            
            // Check for milestones
            $user_activity = get_user_activity($current_user['id']);
            if ($user_activity['chat_messages'] == 1) {
                track_punk_milestone('Sent first chat message');
            } elseif ($user_activity['chat_messages'] % 100 == 0) {
                track_punk_milestone("Sent {$user_activity['chat_messages']} chat messages");
            }
            
            echo json_encode(['success' => true, 'message_id' => $message_id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to send message']);
        }
        exit;
    }
    
    if ($action === 'typing') {
        $is_typing = isset($_POST['typing']) && $_POST['typing'] === 'true';
        set_typing_status($is_typing, 'chat');
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'delete_message') {
        $message_id = intval($_POST['message_id'] ?? 0);
        if (can_delete_chat_message($message_id)) {
            if (delete_chat_message($message_id, $current_user['id'])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete message']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// Handle AJAX requests for getting new messages
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] === 'messages') {
        $since = $_GET['since'] ?? '';
        $messages = $since ? get_chat_messages_since($since, 50) : get_recent_chat_messages(50);
        
        echo json_encode([
            'success' => true,
            'messages' => $messages,
            'current_time' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    if ($_GET['ajax'] === 'online_users') {
        $users = get_online_users();
        echo json_encode([
            'success' => true,
            'users' => $users,
            'count' => count($users)
        ]);
        exit;
    }
    
    if ($_GET['ajax'] === 'typing') {
        $typing = get_typing_users('chat');
        echo json_encode([
            'success' => true,
            'typing_users' => $typing
        ]);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Unknown request']);
    exit;
}

// Get chat statistics
$chat_stats = get_chat_stats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Room - BroChat</title>
    <meta name="description" content="Real-time punk rock community chat - connect with fellow punks">
    
    <link rel="stylesheet" href="/assets/css/chat.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <h1>💬 PUNK ROCK CHAT 💬</h1>
            <div class="header-info">
                <div class="nav-links">
                    <a href="/">Home</a>
                    <a href="/blog.php">Blog</a>
                    <a href="/stream.php">Stream</a>
                </div>
                <div class="user-info">
                    <?= escape_html($current_user['display_name'] ?: $current_user['username']) ?>
                    (<?= BroChatRoles::get_role_display_name($current_user['role']) ?>)
                </div>
                <div class="chat-stats">
                    <?= count($online_users) ?> online • <?= $chat_stats['total_messages'] ?> messages
                </div>
            </div>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php if (flash_has()): ?>
        <div class="flash-messages" id="flashMessages">
            <?php foreach (flash_get() as $type => $messages): ?>
                <?php foreach ($messages as $message): ?>
                    <div class="flash-message flash-<?= $type ?>">
                        <?= escape_html($message) ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Main Chat Container -->
    <div class="chat-container">
        <!-- Chat Main Area -->
        <main class="chat-main">
            <!-- Connection Status -->
            <div class="connection-status connected" id="connectionStatus">
                🟢 Connected to chat
            </div>
            
            <!-- Chat Messages -->
            <div class="chat-messages" id="chatMessages">
                <?php foreach (array_reverse($recent_messages) as $message): ?>
                    <div class="message <?= $message['user_id'] == $current_user['id'] ? 'own-message' : '' ?>" 
                         data-message-id="<?= $message['id'] ?>">
                        <div class="message-header">
                            <span class="message-author role-<?= strtolower($current_user['role'] ?? 'fan') ?>">
                                <?= escape_html($message['username']) ?>
                            </span>
                            <span class="message-time"><?= date('H:i', strtotime($message['timestamp'])) ?></span>
                        </div>
                        <div class="message-content">
                            <?= $message['formatted_message'] ?>
                        </div>
                        <?php if (can_delete_chat_message($message['id'])): ?>
                            <div class="message-actions">
                                <button class="action-btn" data-action="delete-message" data-message-id="<?= $message['id'] ?>">🗑️</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Typing Indicator -->
            <div class="typing-indicator" id="typingIndicator">
                <!-- Typing users will be shown here -->
            </div>
            
            <!-- Chat Input -->
            <div class="chat-input-container">
                <form class="chat-input-form" id="chatForm">
                    <textarea 
                        id="chatInput" 
                        class="chat-input" 
                        placeholder="Type your message... Use ^03colored text^, :emojis:, #hashtags, @mentions"
                        rows="1"
                        maxlength="500"
                        required
                    ></textarea>
                    <button type="submit" class="send-btn" id="sendBtn">
                        Send 🤘
                    </button>
                </form>
                <div class="input-info">
                    <div class="chat-help">
                        <strong>Commands:</strong> 
                        <span class="command">/me action</span> • 
                        <span class="command">/shrug</span> • 
                        <span class="command">/punk</span>
                    </div>
                    <div class="char-count" id="charCount">0 / 500</div>
                </div>
            </div>
        </main>

        <!-- Sidebar -->
        <aside class="chat-sidebar">
            <!-- Online Users -->
            <div class="sidebar-section">
                <div class="sidebar-header">
                    👥 Online (<span id="onlineCount"><?= count($online_users) ?></span>)
                </div>
                <div class="sidebar-content">
                    <div class="user-list" id="onlineUsers">
                        <?php foreach ($online_users as $user): ?>
                            <div class="online-user status-<?= $user['status'] ?>" 
                                 data-action="mention-user" data-username="<?= escape_html($user['username']) ?>">
                                <div class="status-dot"></div>
                                <div class="username role-<?= strtolower($user['role']) ?>">
                                    <?= escape_html($user['display_name'] ?: $user['username']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Chat Commands -->
            <div class="sidebar-section">
                <div class="sidebar-header">⚡ Commands</div>
                <div class="sidebar-content">
                    <div class="commands-list">
                        <div><span class="command">/me [action]</span> - Action message</div>
                        <div><span class="command">/shrug</span> - ¯\_(ツ)_/¯</div>
                        <div><span class="command">/punk</span> - 🤘 PUNK ROCK! 🤘</div>
                        <div><span class="command">/quote</span> - Random punk quote</div>
                        <div><span class="command">^03text^</span> - Colored text</div>
                        <div><span class="command">:rock:</span> - Emoji</div>
                        <div><span class="command">@user</span> - Mention user</div>
                        <div><span class="command">#tag</span> - Hashtag</div>
                    </div>
                </div>
            </div>

            <!-- Quick Emojis -->
            <div class="sidebar-section">
                <div class="sidebar-header">😎 Quick Emojis</div>
                <div class="sidebar-content">
                    <div class="emoji-grid">
                        <button class="emoji-btn" data-emoji="🤘">🤘</button>
                        <button class="emoji-btn" data-emoji="💀">💀</button>
                        <button class="emoji-btn" data-emoji="🎸">🎸</button>
                        <button class="emoji-btn" data-emoji="🔥">🔥</button>
                        <button class="emoji-btn" data-emoji="⚡">⚡</button>
                        <button class="emoji-btn" data-emoji="💥">💥</button>
                        <button class="emoji-btn" data-emoji="🎵">🎵</button>
                        <button class="emoji-btn" data-emoji="🍺">🍺</button>
                        <button class="emoji-btn" data-emoji="😎">😎</button>
                        <button class="emoji-btn" data-emoji="😈">😈</button>
                        <button class="emoji-btn" data-emoji="👹">👹</button>
                        <button class="emoji-btn" data-emoji="🖤">🖤</button>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <!-- Pass data to JavaScript -->
    <script>
        window.BroChatData = {
            currentUserId: <?= json_encode($current_user['id']) ?>,
            currentUsername: <?= json_encode($current_user['username']) ?>,
            currentUserRole: <?= json_encode($current_user['role']) ?>,
            csrfToken: <?= json_encode(csrf_token()) ?>,
            lastMessageTime: <?= json_encode(date('Y-m-d H:i:s')) ?>,
            canModerate: <?= json_encode(BroChatRoles::has_permission('moderate_chat') || BroChatRoles::has_role('admin')) ?>
        };
    </script>
    <script src="/assets/js/chat.js"></script>
</body>
</html>
