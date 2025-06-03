/**
 * BroChat - Registration Page JavaScript
 * Handles registration form validation with strict XSS protection
 */
(function() {
    'use strict';
    
    // Registration page namespace
    window.BroChatRegister = {
        // Configuration from server
        config: {},
        
        // DOM elements cache
        elements: {},
        
        // Validation state
        validation: {
            username: false,
            email: false,
            password: false,
            passwordMatch: false,
            terms: false
        },
        
        // Form submission state
        state: {
            isSubmitting: false
        },
        
        // Initialize registration page
        init: function() {
            this.loadConfig();
            this.cacheElements();
            this.bindEvents();
            this.setupKeyboardShortcuts();
            this.setInitialFocus();
            this.updateProgress();
        },
        
        // Load configuration from JSON script tag
        loadConfig: function() {
            const configScript = document.getElementById('register-data');
            if (configScript) {
                try {
                    this.config = JSON.parse(configScript.textContent);
                } catch (e) {
                    console.error('Failed to parse registration configuration:', e);
                    this.config = {};
                }
            }
        },
        
        // Cache DOM elements for performance
        cacheElements: function() {
            this.elements = {
                form: document.getElementById('registerForm'),
                submitBtn: document.getElementById('registerButton'),
                progressFill: document.getElementById('progressFill'),
                
                // Input elements
                username: document.getElementById('username'),
                email: document.getElementById('email'),
                password: document.getElementById('password'),
                passwordConfirm: document.getElementById('password_confirm'),
                agreeTerms: document.getElementById('agree_terms'),
                
                // Help elements
                usernameHelp: document.getElementById('usernameHelp'),
                emailHelp: document.getElementById('emailHelp'),
                passwordMatchHelp: document.getElementById('passwordMatchHelp'),
                
                // Password requirements
                requirements: {
                    length: document.getElementById('req-length'),
                    letter: document.getElementById('req-letter'),
                    number: document.getElementById('req-number')
                }
            };
        },
        
        // Bind event listeners
        bindEvents: function() {
            // Form submission
            if (this.elements.form) {
                this.elements.form.addEventListener('submit', this.handleFormSubmit.bind(this));
            }
            
            // Input validation
            if (this.elements.username) {
                this.elements.username.addEventListener('input', this.validateUsername.bind(this));
            }
            
            if (this.elements.email) {
                this.elements.email.addEventListener('input', this.validateEmail.bind(this));
            }
            
            if (this.elements.password) {
                this.elements.password.addEventListener('input', this.validatePassword.bind(this));
            }
            
            if (this.elements.passwordConfirm) {
                this.elements.passwordConfirm.addEventListener('input', this.checkPasswordMatch.bind(this));
            }
            
            if (this.elements.agreeTerms) {
                this.elements.agreeTerms.addEventListener('change', this.validateTerms.bind(this));
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
            if (this.elements.submitBtn) {
                this.elements.submitBtn.disabled = true;
                this.elements.submitBtn.textContent = 'Joining the pit...';
            }
        },
        
        // Reset submit button
        resetSubmitButton: function() {
            if (this.elements.submitBtn) {
                this.elements.submitBtn.textContent = '🤘 Join the Punk Rock Community 🤘';
                // Don't re-enable if validation fails
                const allValid = Object.values(this.validation).every(v => v);
                this.elements.submitBtn.disabled = !allValid;
            }
        },
        
        // Update progress bar
        updateProgress: function() {
            const validCount = Object.values(this.validation).filter(v => v).length;
            const progress = (validCount / 5) * 100;
            
            if (this.elements.progressFill) {
                this.elements.progressFill.style.width = progress + '%';
            }
            
            // Enable submit button if all valid
            const allValid = Object.values(this.validation).every(v => v);
            if (this.elements.submitBtn && !this.state.isSubmitting) {
                this.elements.submitBtn.disabled = !allValid;
            }
        },
        
        // Validate username
        validateUsername: function() {
            const input = this.elements.username;
            const help = this.elements.usernameHelp;
            if (!input || !help) return;
            
            const value = input.value;
            const isValid = /^[a-zA-Z0-9_.-]{3,20}$/.test(value);
            
            // Update input styling
            input.className = '';
            if (value && isValid) {
                input.className = 'valid';
            } else if (value) {
                input.className = 'invalid';
            }
            
            // Update help text
            if (!value) {
                help.textContent = 'Letters, numbers, dots, dashes, underscores only';
                help.className = 'field-help';
            } else if (value.length < 3) {
                help.textContent = 'Username too short (minimum 3 characters)';
                help.className = 'field-help error';
            } else if (value.length > 20) {
                help.textContent = 'Username too long (maximum 20 characters)';
                help.className = 'field-help error';
            } else if (!/^[a-zA-Z0-9_.-]+$/.test(value)) {
                help.textContent = 'Invalid characters (use only letters, numbers, dots, dashes, underscores)';
                help.className = 'field-help error';
            } else {
                help.textContent = 'Username looks good! 🤘';
                help.className = 'field-help success';
            }
            
            this.validation.username = isValid;
            this.updateProgress();
        },
        
        // Validate email
        validateEmail: function() {
            const input = this.elements.email;
            const help = this.elements.emailHelp;
            if (!input || !help) return;
            
            const value = input.value;
            const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            
            // Update input styling
            input.className = '';
            if (value && isValid) {
                input.className = 'valid';
            } else if (value) {
                input.className = 'invalid';
            }
            
            // Update help text
            if (!value) {
                help.textContent = 'We won\'t spam you or share your email';
                help.className = 'field-help';
            } else if (isValid) {
                help.textContent = 'Email looks good! 📧';
                help.className = 'field-help success';
            } else {
                help.textContent = 'Please enter a valid email address';
                help.className = 'field-help error';
            }
            
            this.validation.email = isValid;
            this.updateProgress();
        },
        
        // Validate password
        validatePassword: function() {
            const input = this.elements.password;
            if (!input) return;
            
            const value = input.value;
            
            // Check requirements
            const hasLength = value.length >= 8;
            const hasLetter = /[a-zA-Z]/.test(value);
            const hasNumber = /[0-9]/.test(value);
            
            // Update requirement indicators
            this.updateRequirement('length', hasLength);
            this.updateRequirement('letter', hasLetter);
            this.updateRequirement('number', hasNumber);
            
            const isValid = hasLength && hasLetter && hasNumber;
            
            // Update input styling
            input.className = '';
            if (value && isValid) {
                input.className = 'valid';
            } else if (value) {
                input.className = 'invalid';
            }
            
            this.validation.password = isValid;
            
            // Re-check password match
            this.checkPasswordMatch();
            this.updateProgress();
        },
        
        // Update password requirement indicator
        updateRequirement: function(type, met) {
            const req = this.elements.requirements[type];
            if (!req) return;
            
            const check = req.querySelector('.check');
            if (!check) return;
            
            if (met) {
                req.classList.add('met');
                check.textContent = '✓';
            } else {
                req.classList.remove('met');
                check.textContent = '✗';
            }
        },
        
        // Check password match
        checkPasswordMatch: function() {
            const passInput = this.elements.password;
            const confirmInput = this.elements.passwordConfirm;
            const help = this.elements.passwordMatchHelp;
            
            if (!passInput || !confirmInput || !help) return;
            
            const pass = passInput.value;
            const confirm = confirmInput.value;
            
            // Update confirmation input styling and help text
            confirmInput.className = '';
            
            if (!confirm) {
                help.textContent = 'Passwords must match';
                help.className = 'field-help';
                this.validation.passwordMatch = false;
            } else if (pass === confirm && pass.length > 0) {
                help.textContent = 'Passwords match! 🔒';
                help.className = 'field-help success';
                confirmInput.className = 'valid';
                this.validation.passwordMatch = true;
            } else {
                help.textContent = 'Passwords do not match';
                help.className = 'field-help error';
                confirmInput.className = 'invalid';
                this.validation.passwordMatch = false;
            }
            
            this.updateProgress();
        },
        
        // Validate terms agreement
        validateTerms: function() {
            const input = this.elements.agreeTerms;
            if (!input) return;
            
            this.validation.terms = input.checked;
            this.updateProgress();
        },
        
        // Setup keyboard shortcuts
        setupKeyboardShortcuts: function() {
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.handleEscapeKey();
                }
            });
        },
        
        // Handle escape key
        handleEscapeKey: function() {
            if (this.confirmClearForm()) {
                this.clearForm();
                this.setInitialFocus();
            }
        },
        
        // Confirm form clear
        confirmClearForm: function() {
            return window.confirm('Clear the registration form?');
        },
        
        // Clear form
        clearForm: function() {
            if (this.elements.form) {
                this.elements.form.reset();
            }
            
            // Reset validation state
            Object.keys(this.validation).forEach(key => {
                this.validation[key] = false;
            });
            
            // Reset input styling
            const inputs = this.elements.form?.querySelectorAll('input');
            if (inputs) {
                inputs.forEach(input => {
                    input.className = '';
                });
            }
            
            // Reset help text
            this.resetHelpText();
            this.updateProgress();
        },
        
        // Reset help text to initial state
        resetHelpText: function() {
            if (this.elements.usernameHelp) {
                this.elements.usernameHelp.textContent = 'Letters, numbers, dots, dashes, underscores only';
                this.elements.usernameHelp.className = 'field-help';
            }
            
            if (this.elements.emailHelp) {
                this.elements.emailHelp.textContent = 'We won\'t spam you or share your email';
                this.elements.emailHelp.className = 'field-help';
            }
            
            if (this.elements.passwordMatchHelp) {
                this.elements.passwordMatchHelp.textContent = 'Passwords must match';
                this.elements.passwordMatchHelp.className = 'field-help';
            }
            
            // Reset password requirements
            Object.keys(this.elements.requirements).forEach(type => {
                this.updateRequirement(type, false);
            });
        },
        
        // Set initial focus
        setInitialFocus: function() {
            if (this.elements.username) {
                this.elements.username.focus();
            }
        },
        
        // Validate entire form
        validateForm: function() {
            // Trigger validation on all fields
            this.validateUsername();
            this.validateEmail();
            this.validatePassword();
            this.checkPasswordMatch();
            this.validateTerms();
            
            return Object.values(this.validation).every(v => v);
        },
        
        // Handle errors
        handleError: function(error) {
            console.error('Registration error:', error);
            this.resetSubmitButton();
            this.state.isSubmitting = false;
        },
        
        // Clean up resources
        destroy: function() {
            this.state.isSubmitting = false;
            
            // Reset validation state
            Object.keys(this.validation).forEach(key => {
                this.validation[key] = false;
            });
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            BroChatRegister.init();
        });
    } else {
        BroChatRegister.init();
    }
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        BroChatRegister.destroy();
    });
    
})();
