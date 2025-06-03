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

// Get rate limit info
$remaining_attempts = BroChatRateLimit::remaining('password_reset');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Recovery - BroChat</title>
    <meta name="description" content="Reset your password for the punk rock community">
    
    <!-- Security headers via meta tags -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';">
    
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="recovery-page">
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
            <?php if ($remaining_attempts <= 2 && $remaining_attempts > 0): ?>
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
            <div class="demo-info">
                <strong>Demo Mode:</strong><br>
                Password reset links will be displayed on screen instead of being emailed.<br>
                Use email: admin@brochat.local for demo account.
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Pass data to JavaScript -->
    <script id="recovery-data" type="application/json">
        {
            "csrf_token": "<?= escape_js(csrf_token()) ?>",
            "success": <?= $success ? 'true' : 'false' ?>,
            "has_errors": <?= !empty($errors) ? 'true' : 'false' ?>,
            "remaining_attempts": <?= (int)$remaining_attempts ?>
        }
    </script>
    <script src="/assets/js/forgot-password.js"></script>
</body>
</html>
