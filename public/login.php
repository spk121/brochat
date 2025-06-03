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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BroChat</title>
    <meta name="description" content="Login to the punk rock community">
    
    <style>
        /* Punk Rock Authentication Styles */
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
        
        .login-container {
            background: rgba(0, 0, 0, 0.8);
            border: 3px solid #ff0000;
            border-radius: 10px;
            padding: 40px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 0 30px rgba(255, 0, 0, 0.3);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            font-size: 2.5em;
            color: #ff0000;
            text-shadow: 2px 2px 4px #000;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }
        
        .logo .tagline {
            color: #ccc;
            font-style: italic;
            font-size: 1em;
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
            font-size: 0.9em;
            color: #ddd;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #ff0000;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9em;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            background: #111;
            border: 2px solid #333;
            color: #fff;
            border-radius: 5px;
            font-family: inherit;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            border-color: #ff0000;
            outline: none;
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.3);
        }
        
        .form-group input::placeholder {
            color: #666;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .checkbox-group label {
            margin: 0;
            color: #ccc;
            font-weight: normal;
            text-transform: none;
            cursor: pointer;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: #ff0000;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-family: inherit;
            font-size: 1.1em;
            font-weight: bold;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s;
            letter-spacing: 1px;
        }
        
        .btn:hover {
            background: #cc0000;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 0, 0, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
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
        
        .links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #333;
        }
        
        .links a {
            color: #ff0000;
            text-decoration: none;
            font-weight: bold;
            margin: 0 10px;
        }
        
        .links a:hover {
            text-decoration: underline;
            color: #ff6666;
        }
        
        .home-link {
            position: absolute;
            top: 20px;
            left: 20px;
            color: #fff;
            text-decoration: none;
            padding: 10px 15px;
            border: 2px solid #333;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .home-link:hover {
            border-color: #ff0000;
            background: rgba(255, 0, 0, 0.1);
        }
        
        .rate-limit-info {
            background: rgba(255, 255, 0, 0.1);
            border: 1px solid #ffff00;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #ffff00;
            text-align: center;
        }
        
        /* Loading state */
        .btn.loading {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 600px) {
            .login-container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .logo h1 {
                font-size: 2em;
            }
            
            .home-link {
                position: static;
                display: block;
                text-align: center;
                margin-bottom: 20px;
            }
        }
        
        /* Security indicator */
        .security-info {
            background: rgba(0, 150, 0, 0.1);
            border: 1px solid #009600;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.8em;
            color: #00ff00;
            text-align: center;
        }
        
        /* Password strength indicator */
        .password-strength {
            height: 3px;
            background: #333;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
            border-radius: 2px;
        }
        
        .strength-weak { background: #ff0000; width: 25%; }
        .strength-fair { background: #ff6600; width: 50%; }
        .strength-good { background: #ffff00; width: 75%; }
        .strength-strong { background: #00ff00; width: 100%; }
    </style>
</head>
<body>
    <a href="/" class="home-link">← Back to BroChat</a>
    
    <div class="login-container">
        <div class="logo">
            <h1>🤘 BROCHAT 🤘</h1>
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
        <?php 
        $remaining_attempts = BroChatRateLimit::remaining('login');
        if ($remaining_attempts <= 3 && $remaining_attempts > 0): 
        ?>
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
        <div style="margin-top: 30px; padding: 15px; background: rgba(51, 51, 51, 0.5); border-radius: 5px; font-size: 0.8em; text-align: center; color: #888;">
            <strong>Demo Account:</strong><br>
            Username: admin | Password: punk4ever<br>
            <em>(Change this password after first login!)</em>
        </div>
    </div>
    
    <script>
        // Form submission handling
        const loginForm = document.getElementById('loginForm');
        const loginButton = document.getElementById('loginButton');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        
        // Prevent multiple submissions
        loginForm.addEventListener('submit', function(e) {
            loginButton.classList.add('loading');
            loginButton.textContent = 'Entering...';
            loginButton.disabled = true;
            
            // Re-enable after 5 seconds to prevent permanent lockout
            setTimeout(() => {
                loginButton.classList.remove('loading');
                loginButton.textContent = '🤘 Enter the Pit 🤘';
                loginButton.disabled = false;
            }, 5000);
        });
        
        // Auto-focus first empty field
        window.addEventListener('load', function() {
            if (!usernameInput.value) {
                usernameInput.focus();
            } else {
                passwordInput.focus();
            }
        });
        
        // Username validation
        usernameInput.addEventListener('input', function() {
            const value = this.value;
            const validChars = /^[a-zA-Z0-9_.-]*$/;
            
            if (!validChars.test(value)) {
                this.setCustomValidity('Username can only contain letters, numbers, dots, dashes, and underscores');
            } else if (value.length < 3 && value.length > 0) {
                this.setCustomValidity('Username must be at least 3 characters');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Password strength indicator (for demo purposes)
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strengthElement = document.getElementById('passwordStrength');
            const strengthBar = document.getElementById('strengthBar');
            
            if (password.length === 0) {
                strengthElement.style.display = 'none';
                return;
            }
            
            strengthElement.style.display = 'block';
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            
            if (strength <= 1) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 2) {
                strengthBar.classList.add('strength-fair');
            } else if (strength <= 3) {
                strengthBar.classList.add('strength-good');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });
        
        // Clear form on page load if there were errors
        if (window.location.search.includes('error')) {
            passwordInput.value = '';
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Enter key submits form if not already focused on submit button
            if (e.key === 'Enter' && document.activeElement !== loginButton) {
                e.preventDefault();
                loginForm.requestSubmit();
            }
            
            // Escape key clears form
            if (e.key === 'Escape') {
                if (confirm('Clear the login form?')) {
                    usernameInput.value = '';
                    passwordInput.value = '';
                    usernameInput.focus();
                }
            }
        });
        
        // Auto-fill demo credentials (for development)
        function fillDemo() {
            usernameInput.value = 'admin';
            passwordInput.value = 'punk4ever';
            usernameInput.dispatchEvent(new Event('input'));
            passwordInput.dispatchEvent(new Event('input'));
        }
        
        // Double-click logo to fill demo credentials
        document.querySelector('.logo h1').addEventListener('dblclick', fillDemo);
    </script>
</body>
</html>
