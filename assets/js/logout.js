/**
 * BroChat - Logout Page JavaScript
 * Handles logout confirmation with auto-timeout and strict XSS protection
 */
(function() {
    'use strict';
    
    // Logout page namespace
    window.BroChatLogout = {
        // Configuration from server
        config: {},
        
        // DOM elements cache
        elements: {},
        
        // State management
        state: {
            countdownTimer: null,
            countdownSeconds: 30,
            isCountdownActive: false,
            isSubmitting: false
        },
        
        // Initialize logout page
        init: function() {
            this.loadConfig();
            this.cacheElements();
            this.bindEvents();
            this.setupKeyboardShortcuts();
            this.setupBeforeUnload();
            this.startInitialCountdownDelay();
            this.setInitialFocus();
        },
        
        // Load configuration from JSON script tag
        loadConfig: function() {
            const configScript = document.getElementById('logout-data');
            if (configScript) {
                try {
                    this.config = JSON.parse(configScript.textContent);
                    this.state.countdownSeconds = this.config.countdown_seconds || 30;
                } catch (e) {
                    console.error('Failed to parse logout configuration:', e);
                    this.config = {};
                }
            }
        },
        
        // Cache DOM elements for performance
        cacheElements: function() {
            this.elements = {
                countdownDiv: document.getElementById('countdown'),
                countdownNumber: document.getElementById('countdownNumber'),
                logoutForm: document.getElementById('logoutForm'),
                logoutBtn: document.getElementById('logoutBtn')
            };
        },
        
        // Bind event listeners
        bindEvents: function() {
            // Form submission
            if (this.elements.logoutForm) {
                this.elements.logoutForm.addEventListener('submit', this.handleFormSubmit.bind(this));
            }
            
            // Cancel countdown on user interaction
            document.addEventListener('click', this.cancelCountdown.bind(this));
            document.addEventListener('keydown', this.handleKeyDown.bind(this));
            document.addEventListener('mousemove', this.cancelCountdown.bind(this));
        },
        
        // Handle form submission
        handleFormSubmit: function(e) {
            if (this.state.isSubmitting) {
                e.preventDefault();
                return false;
            }
            
            this.state.isSubmitting = true;
            this.setLogoutButtonLoading();
            this.cancelCountdown();
        },
        
        // Set logout button to loading state
        setLogoutButtonLoading: function() {
            if (this.elements.logoutBtn) {
                this.elements.logoutBtn.disabled = true;
                this.elements.logoutBtn.textContent = 'Logging out...';
            }
        },
        
        // Setup keyboard shortcuts
        setupKeyboardShortcuts: function() {
            // Note: keydown events are handled in bindEvents to also cancel countdown
        },
        
        // Handle keydown events
        handleKeyDown: function(e) {
            // Cancel countdown on any key press
            this.cancelCountdown();
            
            // Handle specific shortcuts
            if (e.key === 'Enter') {
                e.preventDefault();
                if (this.elements.logoutForm) {
                    this.elements.logoutForm.requestSubmit();
                }
            } else if (e.key === 'Escape') {
                this.redirectToHome();
            }
        },
        
        // Setup before unload protection
        setupBeforeUnload: function() {
            window.addEventListener('beforeunload', (e) => {
                if (!this.state.isSubmitting) {
                    e.preventDefault();
                    e.returnValue = 'Are you sure you want to leave without logging out properly?';
                }
            });
        },
        
        // Start initial countdown delay
        startInitialCountdownDelay: function() {
            const delay = this.config.countdown_delay || 10000; // 10 seconds default
            setTimeout(() => {
                this.startCountdown();
            }, delay);
        },
        
        // Start countdown
        startCountdown: function() {
            if (this.state.isCountdownActive || this.state.isSubmitting) return;
            
            this.state.isCountdownActive = true;
            
            if (this.elements.countdownDiv) {
                this.elements.countdownDiv.style.display = 'block';
            }
            
            this.state.countdownTimer = setInterval(() => {
                this.updateCountdown();
            }, 1000);
        },
        
        // Update countdown display and check for timeout
        updateCountdown: function() {
            this.state.countdownSeconds--;
            
            if (this.elements.countdownNumber) {
                this.elements.countdownNumber.textContent = this.state.countdownSeconds;
            }
            
            if (this.state.countdownSeconds <= 0) {
                this.performAutoLogout();
            }
        },
        
        // Perform automatic logout
        performAutoLogout: function() {
            this.cancelCountdown();
            
            if (this.elements.logoutForm) {
                this.elements.logoutForm.requestSubmit();
            }
        },
        
        // Cancel countdown
        cancelCountdown: function() {
            if (this.state.countdownTimer) {
                clearInterval(this.state.countdownTimer);
                this.state.countdownTimer = null;
            }
            
            if (this.elements.countdownDiv) {
                this.elements.countdownDiv.style.display = 'none';
            }
            
            // Reset countdown state
            this.state.isCountdownActive = false;
            this.state.countdownSeconds = this.config.countdown_seconds || 30;
            
            if (this.elements.countdownNumber) {
                this.elements.countdownNumber.textContent = this.state.countdownSeconds;
            }
        },
        
        // Redirect to home page
        redirectToHome: function() {
            window.location.href = '/';
        },
        
        // Set initial focus
        setInitialFocus: function() {
            if (this.elements.logoutBtn) {
                this.elements.logoutBtn.focus();
            }
        },
        
        // Validate form before submission
        validateForm: function() {
            // For logout, we don't need complex validation
            // Just ensure we're not already submitting
            return !this.state.isSubmitting;
        },
        
        // Show confirmation dialog (if needed)
        showConfirmation: function(message) {
            return window.confirm(message);
        },
        
        // Handle errors
        handleError: function(error) {
            console.error('Logout error:', error);
            
            // Reset button state on error
            if (this.elements.logoutBtn) {
                this.elements.logoutBtn.disabled = false;
                this.elements.logoutBtn.textContent = '🤘 Yes, Log Me Out';
            }
            
            this.state.isSubmitting = false;
        },
        
        // Clean up resources
        destroy: function() {
            this.cancelCountdown();
            this.state.isSubmitting = false;
            this.state.isCountdownActive = false;
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            BroChatLogout.init();
        });
    } else {
        BroChatLogout.init();
    }
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        BroChatLogout.destroy();
    });
    
})();
