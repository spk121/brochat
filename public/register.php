<?php
require_once __DIR__ . '/bootstrap.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('/');
}

// Track page view
track_page_view('register');

$errors = [];
$form_data = [];

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    // Rate limiting
    require_brochat_rate_limit('login'); // Use login rate limit for registration
    
    // Sanitize inputs
    $form_data = [
        'username' => sanitize_brochat_input($_POST['username'] ?? '', 'username'),
        'email' => sanitize_brochat_input($_POST['email'] ?? '', 'email'),
        'display_name' => sanitize_brochat_input($_POST['display_name'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
        'favorite_band' => sanitize_brochat_input($_POST['favorite_band'] ?? '', 'band_name'),
        'agree_terms' => isset($_POST['agree_terms'])
    ];
    
    // Validate inputs
    $validation_errors = validate_brochat_input($form_data, [
        'username' => ['required', 'punk_username'],
        'email' => ['required', 'email'],
        'password' => ['required', 'strong_password'],
        'favorite_band' => ['band_name']
    ]);
    
    // Additional validation
    if (empty($validation_errors)) {
        // Check password confirmation
        if ($form_data['password'] !== $form_data['password_confirm']) {
            $validation_errors['password_confirm'] = ['Passwords do not match'];
        }
        
        // Check terms agreement
        if (!$form_data['agree_terms']) {
            $validation_errors['agree_terms'] = ['You must agree to the community guidelines'];
        }
        
        // Check if username exists
        if (get_user_by_username($form_data['username'])) {
            $validation_errors['username'] = ['Username is already taken'];
        }
        
        // Check if email exists
        if (get_user_by_email($form_data['email'])) {
            $validation_errors['email'] = ['Email is already registered'];
        }
    }
    
    if (empty($validation_errors)) {
        try {
            // Create user account
            $user_id = create_user(
                $form_data['username'],
                $form_data['email'],
                $form_data['password'],
                $form_data['display_name'] ?: $form_data['username']
            );
            
            if ($user_id) {
                // Set favorite band preference if provided
                if (!empty($form_data['favorite_band'])) {
                    // This will be set after login
                    session_set('pending_favorite_band', $form_data['favorite_band']);
                }
                
                // Auto-login the new user
                $login_result = auth_login($form_data['username'], $form_data['password']);
                
                if ($login_result['success']) {
                    // Set favorite band now that we're logged in
                    if (!empty($form_data['favorite_band'])) {
                        set_favorite_band($form_data['favorite_band']);
                        session_remove('pending_favorite_band');
                    }
                    
                    // Track milestone
                    track_punk_milestone('Joined the community');
                    
                    flash_success('Welcome to the punk rock community! 🤘 Let\'s raise some hell!');
                    redirect('/');
                } else {
                    flash_success('Account created! Please log in to continue.');
                    redirect('/login.php');
                }
            }
        } catch (Exception $e) {
            error_log('Registration error: ' . $e->getMessage());
            $errors[] = 'Failed to create account. Please try again.';
        }
    } else {
        // Flatten validation errors
        foreach ($validation_errors as $field => $field_errors) {
            $errors = array_merge($errors, $field_errors);
        }
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
    <title>Join the Pit - BroChat</title>
    <meta name="description" content="Join the punk rock community - register for BroChat">
    
    <!-- Security headers via meta tags -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';">
    
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="register-page">
    <a href="/" class="home-link">← Back to BroChat</a>
    
    <div class="register-container">
        <div class="logo">
            <h1>🤘 JOIN THE PIT 🤘</h1>
            <div class="tagline">Become Part of the Punk Rock Community</div>
        </div>
        
        <div class="punk-quote">
            <div class="quote-text"><?= escape_html($punk_quote) ?></div>
        </div>
        
        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        
        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $error): ?>
                    <div class="error-item">💀 <?= escape_html($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Registration Form -->
        <form method="POST" id="registerForm" novalidate>
            <?= csrf_field() ?>
            
            <!-- Basic Info Section -->
            <div class="form-section">
                <div class="section-title">🎸 Basic Info</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">
                            Username *
                            <span class="label-description">(Your punk handle)</span>
                        </label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            value="<?= escape_html($form_data['username'] ?? '') ?>"
                            placeholder="3-20 characters"
                            required
                            autocomplete="username"
                            maxlength="20"
                            pattern="[a-zA-Z0-9_.-]+"
                        >
                        <div class="field-help" id="usernameHelp">Letters, numbers, dots, dashes, underscores only</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="display_name">
                            Display Name
                            <span class="label-description">(Optional, shown to others)</span>
                        </label>
                        <input 
                            type="text" 
                            id="display_name" 
                            name="display_name" 
                            value="<?= escape_html($form_data['display_name'] ?? '') ?>"
                            placeholder="How others see you"
                            maxlength="50"
                        >
                        <div class="field-help">Leave blank to use your username</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">
                        Email Address *
                        <span class="label-description">(For password resets only)</span>
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?= escape_html($form_data['email'] ?? '') ?>"
                        placeholder="your@email.com"
                        required
                        autocomplete="email"
                    >
                    <div class="field-help" id="emailHelp">We won't spam you or share your email</div>
                </div>
            </div>
            
            <!-- Security Section -->
            <div class="form-section">
                <div class="section-title">🔒 Security</div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Make it strong!"
                            required
                            autocomplete="new-password"
                        >
                        <div class="password-requirements" id="passwordRequirements">
                            <div class="requirement" id="req-length">
                                <span class="check">✗</span> At least 8 characters
                            </div>
                            <div class="requirement" id="req-letter">
                                <span class="check">✗</span> Contains letters
                            </div>
                            <div class="requirement" id="req-number">
                                <span class="check">✗</span> Contains numbers
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirm">Confirm Password *</label>
                        <input 
                            type="password" 
                            id="password_confirm" 
                            name="password_confirm" 
                            placeholder="Type it again"
                            required
                            autocomplete="new-password"
                        >
                        <div class="field-help" id="passwordMatchHelp">Passwords must match</div>
                    </div>
                </div>
            </div>
            
            <!-- Punk Rock Info Section -->
            <div class="form-section">
                <div class="section-title">🎵 Punk Rock Info</div>
                
                <div class="form-group">
                    <label for="favorite_band">
                        Favorite Punk Band
                        <span class="label-description">(Optional, but come on...)</span>
                    </label>
                    <input 
                        type="text" 
                        id="favorite_band" 
                        name="favorite_band" 
                        value="<?= escape_html($form_data['favorite_band'] ?? '') ?>"
                        placeholder="The Ramones, Dead Kennedys, Black Flag..."
                        maxlength="50"
                    >
                    <div class="field-help">Help us understand your punk rock taste 🤘</div>
                </div>
            </div>
            
            <!-- Terms Agreement -->
            <div class="checkbox-group">
                <input type="checkbox" id="agree_terms" name="agree_terms" required>
                <label for="agree_terms">
                    I agree to the <a href="/terms.php" target="_blank">Community Guidelines</a> 
                    and understand that BroChat is a punk rock community that values 
                    respect, authenticity, and anti-fascist principles. *
                </label>
            </div>
            
            <button type="submit" class="btn" id="registerButton" disabled>
                🤘 Join the Punk Rock Community 🤘
            </button>
        </form>
        
        <div class="links">
            <a href="/login.php">Already have an account? Login here</a>
        </div>
        
        <!-- Community Info -->
        <div class="community-info">
            <strong class="info-title">What you get:</strong><br>
            ✓ Write blog posts with photos<br>
            ✓ Real-time chat with punks worldwide<br>
            ✓ 24/7 punk rock audio stream<br>
            ✓ Connect with @mentions and #hashtags<br>
            ✓ Community milestones and achievements<br>
            <br>
            <strong class="info-title">Community Rules:</strong><br>
            • Keep it punk, keep it real<br>
            • No fascists, racists, or trolls<br>
            • Respect each other's voices<br>
            • Share the punk rock love 🤘
        </div>
    </div>
    
    <!-- Pass data to JavaScript -->
    <script id="register-data" type="application/json">
        {
            "csrf_token": "<?= escape_js(csrf_token()) ?>",
            "has_errors": <?= !empty($errors) ? 'true' : 'false' ?>
        }
    </script>
    <script src="/assets/js/register.js"></script>
</body>
</html>
