<?php
require_once __DIR__ . '/bootstrap.php';

// Must be logged in to logout
if (!is_logged_in()) {
    redirect('/login.php');
}

// Get user info before logout for farewell message
$current_user = current_user();
$username = $current_user['display_name'] ?: $current_user['username'];

// Track the logout action
track_user_action('logout_initiated');

// Handle POST request for actual logout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    // Perform logout
    auth_logout();
    
    // Set farewell message
    flash_punk("See you later, {$username}! Keep the punk spirit alive! 🤘");
    
    // Redirect to home page
    redirect('/');
}

// For GET requests, show confirmation page
track_page_view('logout_confirm');

// Get user session stats
$session_analytics = get_session_analytics();
$user_activity = get_user_activity($current_user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - BroChat</title>
    <meta name="description" content="Logout from the punk rock community">
    
    <!-- Security headers via meta tags -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';">
    
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="logout-page">
    <div class="logout-container">
        <div class="logout-icon">🚪</div>
        
        <h1 class="logout-title">Leaving the Pit?</h1>
        
        <div class="user-info">
            Goodbye, <span class="user-name"><?= escape_html($username) ?></span>!<br>
            <small>(<?= BroChatRoles::get_role_display_name($current_user['role']) ?>)</small>
        </div>
        
        <!-- User session stats -->
        <div class="quick-stats">
            <div class="stats-title">Your Session Stats</div>
            <div class="stat-item">
                <span class="stat-label">Time online:</span>
                <span class="stat-value"><?= gmdate('H:i:s', $session_analytics['online_time']) ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Pages viewed:</span>
                <span class="stat-value"><?= $session_analytics['page_views'] ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Your blog posts:</span>
                <span class="stat-value"><?= $user_activity['blog_posts'] ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Chat messages sent:</span>
                <span class="stat-value"><?= $user_activity['chat_messages'] ?></span>
            </div>
        </div>
        
        <div class="logout-message">
            <p>Are you sure you want to leave the punk rock community?</p>
            <p>Your session will be ended and you'll need to log back in to continue participating in the pit.</p>
            <p><strong>The music never stops, but you'll miss out on the conversation! 🎵</strong></p>
        </div>
        
        <form method="POST" id="logoutForm">
            <?= csrf_field() ?>
            
            <div class="button-group">
                <button type="submit" class="btn btn-logout" id="logoutBtn">
                    🤘 Yes, Log Me Out
                </button>
                <a href="/" class="btn btn-cancel">
                    🏠 Stay in the Pit
                </a>
            </div>
        </form>
        
        <!-- Auto-logout countdown -->
        <div class="countdown" id="countdown" style="display: none;">
            Auto-logout in <span class="countdown-number" id="countdownNumber">30</span> seconds<br>
            <small>Click anywhere to cancel</small>
        </div>
    </div>
    
    <!-- Pass data to JavaScript -->
    <script id="logout-data" type="application/json">
        {
            "csrf_token": "<?= escape_js(csrf_token()) ?>",
            "countdown_seconds": 30,
            "countdown_delay": 10000
        }
    </script>
    <script src="/assets/js/logout.js"></script>
</body>
</html>
