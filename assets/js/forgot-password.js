/**
 * BroChat - Forgot Password Page JavaScript
 * Handles password recovery form with strict XSS protection
 */
(function() {
    'use strict';
    
    // Forgot password page namespace
    window.BroChatForgotPassword = {
        // Configuration from server
        config: {},
        
        // DOM elements cache
        elements: {},
        
        // State management
        state: {
            isSubmitting: false,
            autoRedirectTimer: null
        },
        
        // Initialize forgot password page
        init: function() {
            this.loadConfig();
            this.cacheElements();
            this.bindEvents();
            this.setupKeyboardShortcuts();
            this.setInitialFocus();
            this.setupAutoRedirect();
        },
        
        // Load configuration from JSON script tag
        loadConfig: function() {
            const configScript = document.getElementById('recovery-data');
            if (configScript) {
                try {
                    this.config = JSON.parse(configScript.textContent);
                } catch (e) {
                    console.error('Failed to parse recovery configuration:', e);
                    this.config = {};
                }
            }
        },
        
        // Cache DOM elements for performance
        cacheElements: function() {
            this.elements = {
                form: document.getElementById('recoveryForm'),
                submitButton: document.getElementById('submitButton'),
                emailInput: document.getElementById('email')
            };
        },
        
        // Bind event listeners
        bindEvents: function() {
            // Form submission (only if form exists - not on success page)
            if (this.elements.form) {
                this.elements.form.addEventListener('submit', this.handleFormSubmit.bind(this));
            }
            
            // Email validation
            if (this.elements.emailInput) {
                this.elements.emailInput.addEventListener('input', this.validateEmail.bind(this));
            }
        },
        
        // Handle form submission
        handleFormSubmit: function(e) {
            if (this.state.isSubmitting) {
                e.preventDefault();
                return false;
            }
            
            this.state.isSubmitting = true;
            this.setSubmitButtonLoading();
            
            // Re-enable after 10 seconds to prevent permanent lockout
            setTimeout(() => {
                this.resetSubmitButton();
                this.state.isSubmitting = false;
            }, 10000);
        },
        
        // Set submit button to loading state
        setSubmitButtonLoading: function() {
            if (this.elements.submitButton) {
                this.elements.submitButton.disabled = true;
                this.elements.submitButton.textContent = 'Sending...';
            }
        },
        
        // Reset submit button
        resetSubmitButton: function() {
            if (this.elements.submitButton) {
                this.elements.submitButton.disabled = false;
                this.elements.submitButton.textContent = '🎸 Send Reset Link';
            }
        },
        
        // Validate email input
        validateEmail: function() {
            const input = this.elements.emailInput;
            if (!input) return;
            
            const email = input.value;
            const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            
            if (email && !isValid) {
                input.setCustomValidity('Please enter a valid email address');
            } else {
                input.setCustomValidity('');
            }
        },
        
        // Setup keyboard shortcuts
        setupKeyboardShortcuts: function() {
            document.addEventListener('keydown', (e) => {
                // Only handle shortcuts if form exists (not on success page)
                if (!this.elements.form) return;
                
                if (e.key === 'Enter' && document.activeElement !== this.elements.submitButton) {
                    e.preventDefault();
                    if (this.elements.form) {
                        this.elements.form.requestSubmit();
                    }
                }
                
                if (e.key === 'Escape') {
                    this.redirectToLogin();
                }
            });
        },
        
        // Set initial focus
        setInitialFocus: function() {
            // Only focus email input if form exists (not on success page)
            if (this.elements.emailInput) {
                this.elements.emailInput.focus();
            }
        },
        
        // Setup auto-redirect for success page
        setupAutoRedirect: function() {
            // Only setup auto-redirect if we're on the success page
            if (this.config.success) {
                this.state.autoRedirectTimer = setTimeout(() => {
                    this.offerRedirect();
                }, 10000); // 10 seconds
            }
        },
        
        // Offer redirect to login page
        offerRedirect: function() {
            if (window.confirm('Redirect to login page?')) {
                this.redirectToLogin();
            } else {
                // If user declines, offer again in 30 seconds
                this.state.autoRedirectTimer = setTimeout(() => {
                    this.offerRedirect();
                }, 30000);
            }
        },
        
        // Redirect to login page
        redirectToLogin: function() {
            window.location.href = '/login.php';
        },
        
        // Redirect to home page
        redirectToHome: function() {
            window.location.href = '/';
        },
        
        // Validate form before submission
        validateForm: function() {
            if (!this.elements.emailInput) return false;
            
            const email = this.elements.emailInput.value.trim();
            
            if (!email) {
                this.showValidationError('Email address is required');
                this.elements.emailInput.focus();
                return false;
            }
            
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                this.showValidationError('Please enter a valid email address');
                this.elements.emailInput.focus();
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
            
            if (this.config.remaining_attempts <= 2) {
                this.showRateLimitWarning();
            }
            
            return true;
        },
        
        // Show rate limit error
        showRateLimitError: function() {
            alert('Too many password reset attempts. Please try again later.');
        },
        
        // Show rate limit warning
        showRateLimitWarning: function() {
            // The warning is already shown in the PHP template
            // This method can be used for additional client-side warnings
        },
        
        // Handle errors
        handleError: function(error) {
            console.error('Password recovery error:', error);
            this.resetSubmitButton();
            this.state.isSubmitting = false;
        },
        
        // Clean up resources
        destroy: function() {
            this.state.isSubmitting = false;
            
            if (this.state.autoRedirectTimer) {
                clearTimeout(this.state.autoRedirectTimer);
                this.state.autoRedirectTimer = null;
            }
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            BroChatForgotPassword.init();
        });
    } else {
        BroChatForgotPassword.init();
    }
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        BroChatForgotPassword.destroy();
    });
    
})();
