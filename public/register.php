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
    
    <style>
        /* Punk Rock Registration Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: linear-gradient(135deg, #000 0%, #8B0000 50%, #000 100%);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }
        
        .register-container {
            background: rgba(0, 0, 0, 0.9);
            border: 3px solid #ff0000;
            border-radius: 10px;
            padding: 40px;
            max-width: 600px;
            margin: 0 auto;
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
            font-size: 1.1em;
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #ff0000;
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .form-group .label-description {
            color: #888;
            font-weight: normal;
            font-size: 0.8em;
            margin-left: 5px;
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
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            border-color: #ff0000;
            outline: none;
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.3);
        }
        
        .form-group input::placeholder {
            color: #666;
        }
        
        .form-group input.valid {
            border-color: #00ff00;
        }
        
        .form-group input.invalid {
            border-color: #ff6666;
        }
        
        .field-help {
            font-size: 0.8em;
            color: #888;
            margin-top: 5px;
        }
        
        .field-help.error {
            color: #ff6666;
        }
        
        .field-help.success {
            color: #00ff00;
        }
        
        .password-requirements {
            background: rgba(51, 51, 51, 0.5);
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
            font-size: 0.8em;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 3px;
        }
        
        .requirement .check {
            color: #666;
        }
        
        .requirement.met .check {
            color: #00ff00;
        }
        
        .requirement.met {
            color: #00ff00;
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
            margin-top: 3px;
        }
        
        .checkbox-group label {
            margin: 0;
            color: #ccc;
            font-weight: normal;
            cursor: pointer;
            line-height: 1.4;
        }
        
        .checkbox-group a {
            color: #ff0000;
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
            position: fixed;
            top: 20px;
            left: 20px;
            color: #fff;
            text-decoration: none;
            padding: 10px 15px;
            border: 2px solid #333;
            border-radius: 5px;
            transition: all 0.3s;
            z-index: 100;
        }
        
        .home-link:hover {
            border-color: #ff0000;
            background: rgba(255, 0, 0, 0.1);
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .register-container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .logo h1 {
                font-size: 2em;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .home-link {
                position: static;
                display: block;
                text-align: center;
                margin-bottom: 20px;
            }
        }
        
        /* Progress indicator */
        .progress-bar {
            background: #333;
            height: 4px;
            border-radius: 2px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #ff0000, #ff6600);
            height: 100%;
            width: 0%;
            transition: width 0.3s;
            border-radius: 2px;
        }
    </style>
</head>
<body>
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
        <div style="margin-top: 30px; padding: 20px; background: rgba(51, 51, 51, 0.3); border-radius: 5px; border-left: 4px solid #ff0000;">
            <strong style="color: #ff0000;">What you get:</strong><br>
            ✓ Write blog posts with photos<br>
            ✓ Real-time chat with punks worldwide<br>
            ✓ 24/7 punk rock audio stream<br>
            ✓ Connect with @mentions and #hashtags<br>
            ✓ Community milestones and achievements<br>
            <br>
            <strong style="color: #ff0000;">Community Rules:</strong><br>
            • Keep it punk, keep it real<br>
            • No fascists, racists, or trolls<br>
            • Respect each other's voices<br>
            • Share the punk rock love 🤘
        </div>
    </div>
    
    <script>
        // Form elements
        const form = document.getElementById('registerForm');
        const submitBtn = document.getElementById('registerButton');
        const progressFill = document.getElementById('progressFill');
        
        // Input elements
        const username = document.getElementById('username');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const passwordConfirm = document.getElementById('password_confirm');
        const agreeTerms = document.getElementById('agree_terms');
        
        // Help elements
        const usernameHelp = document.getElementById('usernameHelp');
        const emailHelp = document.getElementById('emailHelp');
        const passwordMatchHelp = document.getElementById('passwordMatchHelp');
        
        // Password requirements
        const requirements = {
            length: document.getElementById('req-length'),
            letter: document.getElementById('req-letter'),
            number: document.getElementById('req-number')
        };
        
        // Validation state
        let validation = {
            username: false,
            email: false,
            password: false,
            passwordMatch: false,
            terms: false
        };
        
        // Update progress bar
        function updateProgress() {
            const validCount = Object.values(validation).filter(v => v).length;
            const progress = (validCount / 5) * 100;
            progressFill.style.width = progress + '%';
            
            // Enable submit button if all valid
            const allValid = Object.values(validation).every(v => v);
            submitBtn.disabled = !allValid;
        }
        
        // Username validation
        username.addEventListener('input', function() {
            const value = this.value;
            const isValid = /^[a-zA-Z0-9_.-]{3,20}$/.test(value);
            
            this.className = isValid ? 'valid' : (value ? 'invalid' : '');
            
            if (!value) {
                usernameHelp.textContent = 'Letters, numbers, dots, dashes, underscores only';
                usernameHelp.className = 'field-help';
            } else if (value.length < 3) {
                usernameHelp.textContent = 'Username too short (minimum 3 characters)';
                usernameHelp.className = 'field-help error';
            } else if (value.length > 20) {
                usernameHelp.textContent = 'Username too long (maximum 20 characters)';
                usernameHelp.className = 'field-help error';
            } else if (!/^[a-zA-Z0-9_.-]+$/.test(value)) {
                usernameHelp.textContent = 'Invalid characters (use only letters, numbers, dots, dashes, underscores)';
                usernameHelp.className = 'field-help error';
            } else {
                usernameHelp.textContent = 'Username looks good! 🤘';
                usernameHelp.className = 'field-help success';
            }
            
            validation.username = isValid;
            updateProgress();
        });
        
        // Email validation
        email.addEventListener('input', function() {
            const value = this.value;
            const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            
            this.className = isValid ? 'valid' : (value ? 'invalid' : '');
            
            if (!value) {
                emailHelp.textContent = 'We won\'t spam you or share your email';
                emailHelp.className = 'field-help';
            } else if (isValid) {
                emailHelp.textContent = 'Email looks good! 📧';
                emailHelp.className = 'field-help success';
            } else {
                emailHelp.textContent = 'Please enter a valid email address';
                emailHelp.className = 'field-help error';
            }
            
            validation.email = isValid;
            updateProgress();
        });
        
        // Password validation
        password.addEventListener('input', function() {
            const value = this.value;
            
            // Check requirements
            const hasLength = value.length >= 8;
            const hasLetter = /[a-zA-Z]/.test(value);
            const hasNumber = /[0-9]/.test(value);
            
            // Update requirement indicators
            updateRequirement('length', hasLength);
            updateRequirement('letter', hasLetter);
            updateRequirement('number', hasNumber);
            
            const isValid = hasLength && hasLetter && hasNumber;
            this.className = isValid ? 'valid' : (value ? 'invalid' : '');
            
            validation.password = isValid;
            
            // Re-check password match
            checkPasswordMatch();
            updateProgress();
        });
        
        // Password confirmation
        passwordConfirm.addEventListener('input', checkPasswordMatch);
        
        function checkPasswordMatch() {
            const pass = password.value;
            const confirm = passwordConfirm.value;
            
            if (!confirm) {
                passwordMatchHelp.textContent = 'Passwords must match';
                passwordMatchHelp.className = 'field-help';
                passwordConfirm.className = '';
                validation.passwordMatch = false;
            } else if (pass === confirm && pass.length > 0) {
                passwordMatchHelp.textContent = 'Passwords match! 🔒';
                passwordMatchHelp.className = 'field-help success';
                passwordConfirm.className = 'valid';
                validation.passwordMatch = true;
            } else {
                passwordMatchHelp.textContent = 'Passwords do not match';
                passwordMatchHelp.className = 'field-help error';
                passwordConfirm.className = 'invalid';
                validation.passwordMatch = false;
            }
            
            updateProgress();
        }
        
        // Update password requirement
        function updateRequirement(type, met) {
            const req = requirements[type];
            const check = req.querySelector('.check');
            
            if (met) {
                req.classList.add('met');
                check.textContent = '✓';
            } else {
                req.classList.remove('met');
                check.textContent = '✗';
            }
        }
        
        // Terms agreement
        agreeTerms.addEventListener('change', function() {
            validation.terms = this.checked;
            updateProgress();
        });
        
        // Form submission
        form.addEventListener('submit', function(e) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Joining the pit...';
            
            // Re-enable after 10 seconds to prevent permanent lockout
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = '🤘 Join the Punk Rock Community 🤘';
            }, 10000);
        });
        
        // Auto-focus first field
        username.focus();
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (confirm('Clear the registration form?')) {
                    form.reset();
                    Object.keys(validation).forEach(key => validation[key] = false);
                    updateProgress();
                    username.focus();
                }
            }
        });
        
        // Initialize
        updateProgress();
    </script>
</body>
</html>
