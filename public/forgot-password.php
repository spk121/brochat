<?php
require_once __DIR__ . '/bootstrap.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('/');
}

// Track page view
track_page_view('forgot_password');

$errors = [];
$success = false;
$email = '';

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    // Rate limiting for password resets
    require_brochat_rate_limit('password_reset');
    
    $email = sanitize_brochat_input($_POST['email'] ?? '', 'email');
    
    // Validate email
    $validation_errors = validate_brochat_input($_POST, [
        'email' => ['required', 'email']
    ]);
    
    if (empty($validation_errors)) {
        // Check if user exists
        $user = get_user_by_email($email);
        
        if ($user) {
            try {
                // Generate password reset token
                $token = generate_password_reset_token($user['id']);
                
                // In a real application, you would send an email here
                // For now, we'll just show a success message
                
                // Log the password reset request
                track_user_action('password_reset_requested', [
                    'user_id' => $user['id'],
                    'email' => $email
                ], $user['id']);
                
                log_security_event('password_reset_requested', [
                    'user_id' => $user['id'],
                    'email' => $email
                ], 'low');
                
                $success = true;
                
                // For demo purposes, we'll show the reset link
                // In production, this would be sent via email
                flash_info("Password reset link: <a href='/reset-password.php?token=" . urlencode($token) . "'>Reset Password</a>");
                
            } catch (Exception $e) {
                error_log('Password reset error: ' . $e->getMessage());
                $errors[] = 'Failed to process password reset. Please try again.';
            }
        } else {
            // Don't reveal whether email exists or not for security
            $success = true;
        }
    } else {
        $errors = array_merge($errors, array_values($validation_errors)[0] ?? []);
    }
}

// Get punk quote
$punk_quote = get_punk_quote();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Recovery - BroChat</title>
    <meta name="description" content="Reset your password for the punk rock community">
    
    <style>
        /* Punk Rock Password Recovery Styles */
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
        
        .recovery-container {
            background: rgba(0, 0, 0, 0.9);
            border: 3px solid #ff0000;
            border-radius: 10px;
            padding: 40px;
            max-width: 500px;
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
        
        .btn:disabled {
            background: #666;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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
        
        .success-message {
            background: rgba(0, 255, 0, 0.1);
            border: 2px solid #00ff00;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success-message h3 {
            color: #00ff00;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .success-message p {
            color: #ccc;
            line-height: 1.5;
            margin-bottom: 10px;
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
        
        .info-box {
            background: rgba(51, 51, 51, 0.5);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #0096ff;
        }
        
        .info-box h4 {
            color: #0096ff;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .info-box p {
            color: #ccc;
            font-size: 0.9em;
            line-height: 1.4;
            margin-bottom: 8px;
        }
        
        .info-box p:last-child {
            margin-bottom: 0;
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
        
        /* Mobile responsiveness */
        @media (max-width: 600px) {
            .recovery-container {
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
    </style>
</head>
<body>
    <a href="/" class="home-link">← Back to BroChat</a>
    
    <div class="recovery-container">
        <div class="logo">
            <h1>🔑 PASSWORD RECOVERY</h1>
            <div class="tagline">Get Back Into the Pit</div>
        </div>
        
        <div class="punk-quote">
            <div class="quote-text"><?= escape_html($punk_quote) ?></div>
        </div>
        
        <?php if ($success): ?>
            <!-- Success Message -->
            <div class="success-message">
                <h3>🎸 Check Your Email!</h3>
                <p>If an account with that email exists, we've sent you a password reset link.</p>
                <p>Check your inbox and spam folder. The link expires in 1 hour.</p>
                <p><strong>Keep rocking! 🤘</strong></p>
            </div>
            
            <div class="links">
                <a href="/login.php">Back to Login</a>
                |
                <a href="/">Home</a>
            </div>
            
        <?php else: ?>
            <!-- Password Recovery Form -->
            
            <!-- Rate Limit Warning -->
            <?php 
            $remaining_attempts = BroChatRateLimit::remaining('password_reset');
            if ($remaining_attempts <= 2 && $remaining_attempts > 0): 
            ?>
                <div class="rate-limit-info">
                    ⚠️ <?= $remaining_attempts ?> password reset attempts remaining this hour
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
            
            <!-- Info Box -->
            <div class="info-box">
                <h4>🔒 Security Info</h4>
                <p>Enter your email address and we'll send you a secure link to reset your password.</p>
                <p>The reset link will expire in 1 hour for security reasons.</p>
                <p>If you don't receive an email, check your spam folder or try again.</p>
            </div>
            
            <form method="POST" id="recoveryForm">
                <?= csrf_field() ?>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?= escape_html($email) ?>"
                        placeholder="Enter your registered email"
                        required
                        autocomplete="email"
                    >
                </div>
                
                <button type="submit" class="btn" id="submitButton">
                    🎸 Send Reset Link
                </button>
            </form>
            
            <div class="links">
                <a href="/login.php">Back to Login</a>
                |
                <a href="/register.php">Join the Community</a>
            </div>
            
            <!-- Demo Info -->
            <div style="margin-top: 30px; padding: 15px; background: rgba(51, 51, 51, 0.5); border-radius: 5px; font-size: 0.8em; text-align: center; color: #888;">
                <strong>Demo Mode:</strong><br>
                Password reset links will be displayed on screen instead of being emailed.<br>
                Use email: admin@brochat.local for demo account.
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        <?php if (!$success): ?>
        // Form handling
        const form = document.getElementById('recoveryForm');
        const submitButton = document.getElementById('submitButton');
        const emailInput = document.getElementById('email');
        
        // Form submission
        form.addEventListener('submit', function(e) {
            submitButton.disabled = true;
            submitButton.textContent = 'Sending...';
            
            // Re-enable after 10 seconds
            setTimeout(() => {
                submitButton.disabled = false;
                submitButton.textContent = '🎸 Send Reset Link';
            }, 10000);
        });
        
        // Auto-focus email field
        emailInput.focus();
        
        // Email validation
        emailInput.addEventListener('input', function() {
            const email = this.value;
            const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            
            if (email && !isValid) {
                this.setCustomValidity('Please enter a valid email address');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && document.activeElement !== submitButton) {
                e.preventDefault();
                form.requestSubmit();
            }
            
            if (e.key === 'Escape') {
                window.location.href = '/login.php';
            }
        });
        <?php endif; ?>
        
        // Auto-redirect after successful submission
        <?php if ($success): ?>
        setTimeout(function() {
            if (confirm('Redirect to login page?')) {
                window.location.href = '/login.php';
            }
        }, 10000); // 10 seconds
        <?php endif; ?>
    </script>
</body>
</html>>
