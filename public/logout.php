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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - BroChat</title>
    <meta name="description" content="Logout from the punk rock community">
    
    <style>
        /* Punk Rock Logout Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: linear-gradient(135deg, #000 0%, #8B0000 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .logout-container {
            background: rgba(0, 0, 0, 0.9);
            border: 3px solid #ff0000;
            border-radius: 10px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 0 30px rgba(255, 0, 0, 0.3);
        }
        
        .logout-icon {
            font-size: 4em;
            margin-bottom: 20px;
            color: #ff0000;
        }
        
        .logout-title {
            font-size: 2em;
            color: #ff0000;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .user-info {
            font-size: 1.2em;
            color: #ccc;
            margin-bottom: 30px;
        }
        
        .user-name {
            color: #ff0000;
            font-weight: bold;
        }
        
        .logout-message {
            background: rgba(139, 0, 0, 0.3);
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border-left: 4px solid #ff0000;
        }
        
        .logout-message p {
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .logout-message p:last-child {
            margin-bottom: 0;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 15px 25px;
            border: none;
            border-radius: 5px;
            font-family: inherit;
            font-size: 1em;
            font-weight: bold;
            text-transform: uppercase;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            letter-spacing: 1px;
            min-width: 150px;
        }
        
        .btn-logout {
            background: #ff0000;
            color: #fff;
        }
        
        .btn-logout:hover {
            background: #cc0000;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 0, 0, 0.4);
        }
        
        .btn-cancel {
            background: #333;
            color: #fff;
        }
        
        .btn-cancel:hover {
            background: #555;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(85, 85, 85, 0.4);
        }
        
        .quick-stats {
            background: rgba(51, 51, 51, 0.5);
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .stats-title {
            color: #ff0000;
            font-weight: bold;
            margin-bottom: 15px;
            text-transform: uppercase;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9em;
        }
        
        .stat-label {
            color: #ccc;
        }
        
        .stat-value {
            color: #fff;
            font-weight: bold;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 600px) {
            .logout-container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .logout-title {
                font-size: 1.5em;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
        
        /* Auto-logout countdown */
        .countdown {
            background: rgba(255, 255, 0, 0.1);
            border: 1px solid #ffff00;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            color: #ffff00;
            font-size: 0.9em;
        }
        
        .countdown-number {
            color: #ff0000;
            font-weight: bold;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">🚪</div>
        
        <h1 class="logout-title">Leaving the Pit?</h1>
        
        <div class="user-info">
            Goodbye, <span class="user-name"><?= escape_html($username) ?></span>!<br>
            <small>(<?= BroChatRoles::get_role_display_name($current_user['role']) ?>)</small>
        </div>
        
        <!-- User session stats -->
        <?php 
        $session_analytics = get_session_analytics();
        $user_activity = get_user_activity($current_user['id']);
        ?>
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
    
    <script>
        // Auto-logout after 30 seconds of inactivity
        let countdownTimer;
        let countdownSeconds = 30;
        let isCountdownActive = false;
        
        const countdownDiv = document.getElementById('countdown');
        const countdownNumber = document.getElementById('countdownNumber');
        const logoutForm = document.getElementById('logoutForm');
        const logoutBtn = document.getElementById('logoutBtn');
        
        // Start countdown after 10 seconds
        setTimeout(startCountdown, 10000);
        
        function startCountdown() {
            if (isCountdownActive) return;
            
            isCountdownActive = true;
            countdownDiv.style.display = 'block';
            
            countdownTimer = setInterval(() => {
                countdownSeconds--;
                countdownNumber.textContent = countdownSeconds;
                
                if (countdownSeconds <= 0) {
                    // Auto-logout
                    logoutForm.submit();
                }
            }, 1000);
        }
        
        function cancelCountdown() {
            if (countdownTimer) {
                clearInterval(countdownTimer);
                countdownDiv.style.display = 'none';
                isCountdownActive = false;
                countdownSeconds = 30;
                countdownNumber.textContent = countdownSeconds;
            }
        }
        
        // Cancel countdown on any user interaction
        document.addEventListener('click', cancelCountdown);
        document.addEventListener('keydown', cancelCountdown);
        document.addEventListener('mousemove', cancelCountdown);
        
        // Form submission handling
        logoutForm.addEventListener('submit', function() {
            logoutBtn.disabled = true;
            logoutBtn.textContent = 'Logging out...';
            cancelCountdown();
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                logoutForm.submit();
            } else if (e.key === 'Escape') {
                window.location.href = '/';
            }
        });
        
        // Prevent accidental back button navigation
        window.addEventListener('beforeunload', function(e) {
            if (!logoutBtn.disabled) {
                e.preventDefault();
                e.returnValue = 'Are you sure you want to leave without logging out properly?';
            }
        });
        
        // Focus management
        logoutBtn.focus();
    </script>
</body>
</html>
