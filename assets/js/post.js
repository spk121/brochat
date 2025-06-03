/**
 * BroChat - Single Post Page JavaScript
 * Handles post viewing, photo modals, and post management with strict XSS protection
 */
(function() {
    'use strict';
    
    // Post page namespace
    window.BroChatPost = {
        // Configuration from server
        config: {},
        
        // DOM elements cache
        elements: {},
        
        // State management
        state: {
            deleteModalOpen: false,
            photoModalOpen: false
        },
        
        // Initialize post page
        init: function() {
            this.loadConfig();
            this.cacheElements();
            this.bindEvents();
            this.setupKeyboardShortcuts();
            this.setupLazyLoading();
            this.addCopyLinkButton();
        },
        
        // Load configuration from JSON script tag
        loadConfig: function() {
            const configScript = document.getElementById('post-data');
            if (configScript) {
                try {
                    this.config = JSON.parse(configScript.textContent);
                } catch (e) {
                    console.error('Failed to parse post configuration:', e);
                    this.config = {};
                }
            }
        },
        
        // Cache DOM elements for performance
        cacheElements: function() {
            this.elements = {
                copyLinkBtn: document.getElementById('copyLinkBtn'),
                deletePostBtn: document.getElementById('deletePostBtn'),
                deleteModal: document.getElementById('deleteModal'),
                cancelDeleteBtn: document.getElementById('cancelDeleteBtn'),
                photoItems: document.querySelectorAll('.photo-item')
            };
        },
        
        // Bind event listeners
        bindEvents: function() {
            // Copy link button
            if (this.elements.copyLinkBtn) {
                this.elements.copyLinkBtn.addEventListener('click', this.copyPostLink.bind(this));
            }
            
            // Delete post button
            if (this.elements.deletePostBtn) {
                this.elements.deletePostBtn.addEventListener('click', this.showDeleteModal.bind(this));
            }
            
            // Cancel delete button
            if (this.elements.cancelDeleteBtn) {
                this.elements.cancelDeleteBtn.addEventListener('click', this.hideDeleteModal.bind(this));
            }
            
            // Delete modal backdrop click
            if (this.elements.deleteModal) {
                this.elements.deleteModal.addEventListener('click', (e) => {
                    if (e.target === this.elements.deleteModal) {
                        this.hideDeleteModal();
                    }
                });
            }
            
            // Photo items
            this.elements.photoItems.forEach(photoItem => {
                photoItem.addEventListener('click', () => {
                    const fullSrc = photoItem.getAttribute('data-full-src');
                    if (fullSrc) {
                        this.viewPhoto(fullSrc);
                    }
                });
            });
        },
        
        // Setup keyboard shortcuts
        setupKeyboardShortcuts: function() {
            document.addEventListener('keydown', (e) => {
                // ESC to close any modal
                if (e.key === 'Escape') {
                    if (this.state.deleteModalOpen) {
                        this.hideDeleteModal();
                    } else if (this.state.photoModalOpen) {
                        this.closePhotoModal();
                    }
                }
                
                // B to go back to blog
                if (e.key === 'b' || e.key === 'B') {
                    if (!this.state.deleteModalOpen && !this.state.photoModalOpen) {
                        window.location.href = '/blog.php';
                    }
                }
                
                // W to write new post (if logged in)
                if ((e.key === 'w' || e.key === 'W') && e.altKey) {
                    e.preventDefault();
                    if (this.config.can_write && !this.state.deleteModalOpen && !this.state.photoModalOpen) {
                        window.location.href = '/write.php';
                    }
                }
                
                // C to copy link
                if ((e.key === 'c' || e.key === 'C') && (e.ctrlKey || e.metaKey) && e.shiftKey) {
                    e.preventDefault();
                    this.copyPostLink();
                }
            });
        },
        
        // Setup lazy loading for images
        setupLazyLoading: function() {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.removeAttribute('data-src');
                            }
                            observer.unobserve(img);
                        }
                    });
                });
                
                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
        },
        
        // Add copy link button functionality
        addCopyLinkButton: function() {
            // The copy link button is already in the DOM via PHP
            // This just ensures the functionality is set up
        },
        
        // Copy post link to clipboard
        copyPostLink: function() {
            const url = this.config.post_url || window.location.href;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    this.showMessage('Post link copied to clipboard! 🤘', 'success');
                }).catch(err => {
                    console.error('Failed to copy link:', err);
                    this.fallbackCopyLink(url);
                });
            } else {
                this.fallbackCopyLink(url);
            }
        },
        
        // Fallback copy method for older browsers
        fallbackCopyLink: function(url) {
            const textArea = document.createElement('textarea');
            textArea.value = url;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            
            try {
                document.execCommand('copy');
                this.showMessage('Post link copied to clipboard! 🤘', 'success');
            } catch (err) {
                console.error('Failed to copy link:', err);
                this.showMessage('Failed to copy link. Please copy manually.', 'error');
            }
            
            document.body.removeChild(textArea);
        },
        
        // Show delete confirmation modal
        showDeleteModal: function() {
            if (this.elements.deleteModal) {
                this.elements.deleteModal.style.display = 'flex';
                this.state.deleteModalOpen = true;
                document.body.style.overflow = 'hidden';
            }
        },
        
        // Hide delete confirmation modal
        hideDeleteModal: function() {
            if (this.elements.deleteModal) {
                this.elements.deleteModal.style.display = 'none';
                this.state.deleteModalOpen = false;
                document.body.style.overflow = '';
            }
        },
        
        // View photo in modal
        viewPhoto: function(src) {
            // Validate src parameter
            if (typeof src !== 'string' || !src.match(/^\/uploads\/photos\/[a-zA-Z0-9_\-\.]+$/)) {
                console.error('Invalid photo source');
                return;
            }
            
            const modal = document.createElement('div');
            modal.className = 'photo-modal';
            
            const img = document.createElement('img');
            img.src = src;
            img.alt = 'Full size photo';
            
            const closeHint = document.createElement('div');
            closeHint.className = 'close-hint';
            closeHint.textContent = 'Click to close';
            closeHint.style.cssText = `
                position: absolute;
                top: 20px;
                right: 20px;
                color: white;
                font-size: 1.2em;
                background: rgba(0, 0, 0, 0.7);
                padding: 10px;
                border-radius: 3px;
            `;
            
            modal.appendChild(img);
            modal.appendChild(closeHint);
            
            // Close on click
            modal.addEventListener('click', () => {
                this.closePhotoModal(modal);
            });
            
            // Store reference for keyboard shortcut
            this.currentPhotoModal = modal;
            this.state.photoModalOpen = true;
            
            document.body.appendChild(modal);
            document.body.style.overflow = 'hidden';
        },
        
        // Close photo modal
        closePhotoModal: function(modal = null) {
            const modalToClose = modal || this.currentPhotoModal;
            if (modalToClose && modalToClose.parentNode) {
                document.body.removeChild(modalToClose);
                document.body.style.overflow = '';
                this.state.photoModalOpen = false;
                this.currentPhotoModal = null;
            }
        },
        
        // Show temporary message
        showMessage: function(message, type = 'info') {
            const messageDiv = document.createElement('div');
            messageDiv.className = `flash-message flash-${type}`;
            messageDiv.textContent = message;
            
            const container = document.querySelector('.container');
            if (container) {
                const firstChild = container.firstElementChild;
                container.insertBefore(messageDiv, firstChild);
                
                // Auto-remove after 3 seconds
                setTimeout(() => {
                    if (messageDiv.parentNode) {
                        messageDiv.style.transition = 'opacity 0.5s';
                        messageDiv.style.opacity = '0';
                        setTimeout(() => {
                            if (messageDiv.parentNode) {
                                messageDiv.parentNode.removeChild(messageDiv);
                            }
                        }, 500);
                    }
                }, 3000);
            }
        },
        
        // Navigate to blog page
        goToBlog: function() {
            window.location.href = '/blog.php';
        },
        
        // Navigate to write page
        goToWrite: function() {
            if (this.config.can_write) {
                window.location.href = '/write.php';
            }
        },
        
        // Handle errors
        handleError: function(error) {
            console.error('Post page error:', error);
            this.showMessage('An error occurred. Please try again.', 'error');
        },
        
        // Clean up resources
        destroy: function() {
            this.state.deleteModalOpen = false;
            this.state.photoModalOpen = false;
            
            if (this.currentPhotoModal) {
                this.closePhotoModal();
            }
            
            if (this.elements.deleteModal) {
                this.hideDeleteModal();
            }
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            BroChatPost.init();
        });
    } else {
        BroChatPost.init();
    }
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        BroChatPost.destroy();
    });
    
})();
