/**
 * BroChat - Login Page JavaScript
 * Handles login form functionality with strict XSS protection
 */
(function() {
    'use strict';
    
    // Login page namespace
    window.BroChatLogin = {
        // Configuration from server
        config: {},
        
        // DOM elements cache
        elements: {},
        
        // State management
        state: {
            isSubmitting: false,
            passwordStrengthVisible: false
        },
        
        // Initialize login page
        init: function() {
            this.loadConfig();
            this.cacheElements();
            this.bindEvents();
            this.setupInitialFocus();
            this.setupKeyboardShortcuts();
            this.clearPasswordOnError();
        },
        
        // Load configuration from JSON script tag
        loadConfig: function() {
            const configScript = document.getElementById('login-data');
            if (configScript) {
                try {
                    this.config = JSON.parse(configScript.textContent);
                } catch (e) {
                    console.error('Failed to parse login configuration:', e);
                    this.config = {};
                }
            }
        },
        
        // Cache DOM elements for performance
        cacheElements: function() {
            this.elements = {
                loginForm: document.getElementById('loginForm'),
                loginButton: document.getElementById('loginButton'),
                usernameInput: document.getElementById('username'),
                passwordInput: document.getElementById('password'),
                passwordStrength: document.getElementById('passwordStrength'),
                strengthBar: document.getElementById('strengthBar'),
                logoHeading: document.getElementById('logoHeading')
            };
        },
        
        // Bind event listeners
        bindEvents: function() {
            // Form submission
            if (this.elements.loginForm) {
                this.elements.loginForm.addEventListener('submit', this.handleFormSubmit.bind(this));
            }
            
            // Username validation
            if (this.elements.usernameInput) {
                this.elements.usernameInput.addEventListener('input', this.handleUsernameInput.bind(this));
            }
            
            // Password strength indicator
            if (this.elements.passwordInput) {
                this.elements.passwordInput.addEventListener('input', this.handlePasswordInput.bind(this));
            }
            
            // Demo credentials (double-click logo)
            if (this.elements.logoHeading) {
                this.elements.logoHeading.addEventListener('dblclick', this.fillDemoCredentials.bind(this));
            }
        },
        
        // Handle form submission
        handleFormSubmit: function(e) {
            if (this.state.isSubmitting) {
                e.preventDefault();
                return false;
            }
            
            this.state.isSubmitting = true;
            this.setLoadingState(true);
            
            // Re-enable after 5 seconds to prevent permanent lockout
            setTimeout(() => {
                this.setLoadingState(false);
                this.state.isSubmitting = false;
            }, 5000);
        },
        
        // Set loading state
        setLoadingState: function(loading) {
            if (!this.elements.loginButton) return;
            
            if (loading) {
                this.elements.loginButton.classList.add('loading');
                this.elements.loginButton.textContent = 'Entering...';
                this.elements.loginButton.disabled = true;
            } else {
                this.elements.loginButton.classList.remove('loading');
                this.elements.loginButton.textContent = '🤘 Enter the Pit 🤘';
                this.elements.loginButton.disabled = false;
            }
        },
        
        // Handle username input validation
        handleUsernameInput: function(e) {
            const value = e.target.value;
            const validChars = /^[a-zA-Z0-9_.-]*$/;
            
            if (!validChars.test(value)) {
                e.target.setCustomValidity('Username can only contain letters, numbers, dots, dashes, and underscores');
            } else if (value.length < 3 && value.length > 0) {
                e.target.setCustomValidity('Username must be at least 3 characters');
            } else {
                e.target.setCustomValidity('');
            }
        },
        
        // Handle password input and strength indicator
        handlePasswordInput: function(e) {
            const password = e.target.value;
            this.updatePasswordStrength(password);
        },
        
        // Update password strength indicator
        updatePasswordStrength: function(password) {
            const strengthElement = this.elements.passwordStrength;
            const strengthBar = this.elements.strengthBar;
            
            if (!strengthElement || !strengthBar) return;
            
            if (password.length === 0) {
                strengthElement.style.display = 'none';
                this.state.passwordStrengthVisible = false;
                return;
            }
            
            if (!this.state.passwordStrengthVisible) {
                strengthElement.style.display = 'block';
                this.state.passwordStrengthVisible = true;
            }
            
            // Calculate password strength
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            // Update strength bar
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
        },
        
        // Set initial focus
        setupInitialFocus: function() {
            const { usernameInput, passwordInput } = this.elements;
            
            if (!usernameInput || !passwordInput) return;
            
            window.addEventListener('load', () => {
                if (!usernameInput.value.trim()) {
                    usernameInput.focus();
                } else {
                    passwordInput.focus();
                }
            });
        },
        
        // Setup keyboard shortcuts
        setupKeyboardShortcuts: function() {
            document.addEventListener('keydown', (e) => {
                // Enter key submits form if not already focused on submit button
                if (e.key === 'Enter' && document.activeElement !== this.elements.loginButton) {
                    e.preventDefault();
                    if (this.elements.loginForm) {
                        this.elements.loginForm.requestSubmit();
                    }
                }
                
                // Escape key clears form
                if (e.key === 'Escape') {
                    this.handleEscapeKey();
                }
            });
        },
        
        // Handle escape key
        handleEscapeKey: function() {
            if (this.confirmClearForm()) {
                this.clearForm();
                if (this.elements.usernameInput) {
                    this.elements.usernameInput.focus();
                }
            }
        },
        
        // Confirm form clear
        confirmClearForm: function() {
            return window.confirm('Clear the login form?');
        },
        
        // Clear form inputs
        clearForm: function() {
            if (this.elements.usernameInput) {
                this.elements.usernameInput.value = '';
            }
            if (this.elements.passwordInput) {
                this.elements.passwordInput.value = '';
                this.updatePasswordStrength('');
            }
        },
        
        // Clear password on error
        clearPasswordOnError: function() {
            if (this.config.has_errors && this.elements.passwordInput) {
                this.elements.passwordInput.value = '';
            }
        },
        
        // Fill demo credentials
        fillDemoCredentials: function() {
            if (!this.elements.usernameInput || !this.elements.passwordInput) return;
            
            this.elements.usernameInput.value = 'admin';
            this.elements.passwordInput.value = 'punk4ever';
            
            // Trigger input events
            this.elements.usernameInput.dispatchEvent(new Event('input', { bubbles: true }));
            this.elements.passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
        },
        
        // Validate form before submission
        validateForm: function() {
            const { usernameInput, passwordInput } = this.elements;
            
            if (!usernameInput || !passwordInput) return false;
            
            const username = usernameInput.value.trim();
            const password = passwordInput.value;
            
            if (!username) {
                this.showValidationError('Username is required');
                usernameInput.focus();
                return false;
            }
            
            if (username.length < 3) {
                this.showValidationError('Username must be at least 3 characters');
                usernameInput.focus();
                return false;
            }
            
            if (!password) {
                this.showValidationError('Password is required');
                passwordInput.focus();
                return false;
            }
            
            return true;
        },
        
        // Show validation error
        showValidationError: function(message) {
            // For now, use alert. In a full implementation, this would show
            // a styled notification or update an error container
            alert(message);
        },
        
        // Check rate limiting status
        checkRateLimit: function() {
            if (this.config.remaining_attempts <= 0) {
                this.showRateLimitError();
                return false;
            }
            
            if (this.config.remaining_attempts <= 3) {
                this.showRateLimitWarning();
            }
            
            return true;
        },
        
        // Show rate limit error
        showRateLimitError: function() {
            alert('Too many login attempts. Please try again later.');
        },
        
        // Show rate limit warning
        showRateLimitWarning: function() {
            // The warning is already shown in the PHP template
            // This method can be used for additional client-side warnings
        },
        
        // Clean up resources
        destroy: function() {
            // Remove event listeners if needed
            this.state.isSubmitting = false;
            this.state.passwordStrengthVisible = false;
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            BroChatLogin.init();
        });
    } else {
        BroChatLogin.init();
    }
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        BroChatLogin.destroy();
    });
    
})();
