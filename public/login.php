<?php
require_once __DIR__ . '/bootstrap.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('/');
}

// Track page view
track_page_view('login');

$errors = [];
$username = '';
$remember_redirect = $_GET['redirect'] ?? '/';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    // Rate limiting
    require_brochat_rate_limit('login');
    
    $username = sanitize_brochat_input($_POST['username'] ?? '', 'username');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    // Validate inputs
    $validation_errors = validate_brochat_input($_POST, [
        'username' => ['required'],
        'password' => ['required']
    ]);
    
    if (empty($validation_errors)) {
        $login_result = auth_login($username, $password, $remember_me);
        
        if ($login_result['success']) {
            flash_success('Welcome back to the pit! 🤘');
            
            // Track login
            track_user_action('login', [
                'remember_me' => $remember_me,
                'redirect_to' => $remember_redirect
            ]);
            
            redirect($remember_redirect);
        } else {
            $errors[] = $login_result['error'];
            
            // Track failed login attempt
            track_user_action('failed_login', [
                'username' => $username,
                'reason' => $login_result['error']
            ]);
        }
    } else {
        $errors = array_merge($errors, array_values($validation_errors)[0] ?? []);
    }
}

// Get punk quote for the page
$punk_quote = get_punk_quote();

// Get rate limit info
$remaining_attempts = BroChatRateLimit::remaining('login');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BroChat</title>
    <meta name="description" content="Login to the punk rock community">
    
    <!-- Security headers via meta tags -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';">
    
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="auth-page">
    <a href="/" class="home-link">← Back to BroChat</a>
    
    <div class="login-container">
        <div class="logo">
            <h1 id="logoHeading">🤘 BROCHAT 🤘</h1>
            <div class="tagline">Enter the Pit</div>
        </div>
        
        <div class="punk-quote">
            <div class="quote-text"><?= escape_html($punk_quote) ?></div>
        </div>
        
        <!-- Security Info -->
        <div class="security-info">
            🔒 Secure login with rate limiting and encryption
        </div>
        
        <!-- Rate Limit Warning -->
        <?php if ($remaining_attempts <= 3 && $remaining_attempts > 0): ?>
            <div class="rate-limit-info">
                ⚠️ <?= $remaining_attempts ?> login attempts remaining before temporary lockout
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
        
        <!-- Login Form -->
        <form method="POST" id="loginForm">
            <?= csrf_field() ?>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    value="<?= escape_html($username) ?>"
                    placeholder="Your punk rock handle"
                    required
                    autocomplete="username"
                    maxlength="20"
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Keep it secret, keep it punk"
                    required
                    autocomplete="current-password"
                >
                <div class="password-strength" id="passwordStrength" style="display: none;">
                    <div class="password-strength-bar" id="strengthBar"></div>
                </div>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="remember_me" name="remember_me">
                <label for="remember_me">Remember me for 30 days</label>
            </div>
            
            <button type="submit" class="btn" id="loginButton">
                🤘 Enter the Pit 🤘
            </button>
        </form>
        
        <div class="links">
            <a href="/register.php">Join the Community</a>
            |
            <a href="/forgot-password.php">Forgot Password?</a>
        </div>
        
        <!-- Quick Demo Info -->
        <div class="demo-info">
            <strong>Demo Account:</strong><br>
            Username: admin | Password: punk4ever<br>
            <em>(Change this password after first login!)</em>
        </div>
    </div>
    
    <!-- Pass data to JavaScript -->
    <script id="login-data" type="application/json">
        {
            "has_errors": <?= !empty($errors) ? 'true' : 'false' ?>,
            "remaining_attempts": <?= (int)$remaining_attempts ?>
        }
    </script>
    <script src="/assets/js/login.js"></script>
</body>
</html>
