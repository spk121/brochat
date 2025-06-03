/**
 * BroChat - Blog Page JavaScript
 * Handles blog listing, search, photo viewing, and post management with strict XSS protection
 */
(function() {
    'use strict';
    
    // Blog page namespace
    window.BroChatBlog = {
        // Configuration from server
        config: {},
        
        // DOM elements cache
        elements: {},
        
        // State management
        state: {
            isLoading: false,
            autoRefreshTimer: null
        },
        
        // Initialize blog page
        init: function() {
            this.loadConfig();
            this.cacheElements();
            this.bindEvents();
            this.setupKeyboardShortcuts();
            this.setupLazyLoading();
            this.startAutoRefresh();
        },
        
        // Load configuration from JSON script tag
        loadConfig: function() {
            const configScript = document.getElementById('blog-data');
            if (configScript) {
                try {
                    this.config = JSON.parse(configScript.textContent);
                } catch (e) {
                    console.error('Failed to parse blog configuration:', e);
                    this.config = {};
                }
            }
        },
        
        // Cache DOM elements for performance
        cacheElements: function() {
            this.elements = {
                searchForm: document.getElementById('searchForm'),
                searchInput: document.getElementById('searchInput'),
                onlineUsersList: document.getElementById('onlineUsersList'),
                postPhotos: document.querySelectorAll('.post-photo'),
                deleteBtns: document.querySelectorAll('.delete-btn'),
                blogFeed: document.querySelector('.blog-feed')
            };
        },
        
        // Bind event listeners
        bindEvents: function() {
            // Search form handling
            if (this.elements.searchForm) {
                this.elements.searchForm.addEventListener('submit', this.handleSearchSubmit.bind(this));
            }
            
            if (this.elements.searchInput) {
                this.elements.searchInput.addEventListener('keydown', this.handleSearchKeydown.bind(this));
            }
            
            // Photo viewing
            this.elements.postPhotos.forEach(photo => {
                photo.addEventListener('click', () => {
                    const fullSrc = photo.getAttribute('data-full-src');
                    if (fullSrc) {
                        this.viewPhoto(fullSrc);
                    }
                });
            });
            
            // Delete buttons
            this.elements.deleteBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const postId = btn.getAttribute('data-post-id');
                    if (postId) {
                        this.deletePost(parseInt(postId));
                    }
                });
            });
            
            // Infinite scroll
            window.addEventListener('scroll', this.handleScroll.bind(this));
        },
        
        // Handle search form submission
        handleSearchSubmit: function(e) {
            // Allow default form submission
            // Just add loading state to search button
            const submitBtn = this.elements.searchForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Searching...';
                
                // Reset after 5 seconds in case of navigation issues
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '🔍 Search';
                }, 5000);
            }
        },
        
        // Handle search input keydown
        handleSearchKeydown: function(e) {
            if (e.key === 'Escape') {
                e.target.value = '';
                e.target.blur();
            }
        },
        
        // Setup keyboard shortcuts
        setupKeyboardShortcuts: function() {
            document.addEventListener('keydown', (e) => {
                // Alt + W to write new post
                if (e.altKey && (e.key === 'w' || e.key === 'W')) {
                    e.preventDefault();
                    const writeLink = document.querySelector('a[href="/write.php"]');
                    if (writeLink) {
                        window.location.href = '/write.php';
                    }
                }
                
                // Alt + S to focus search
                if (e.altKey && (e.key === 's' || e.key === 'S')) {
                    e.preventDefault();
                    if (this.elements.searchInput) {
                        this.elements.searchInput.focus();
                        this.elements.searchInput.select();
                    }
                }
                
                // Alt + H to go home
                if (e.altKey && (e.key === 'h' || e.key === 'H')) {
                    e.preventDefault();
                    window.location.href = '/';
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
        
        // Start auto-refresh for online users
        startAutoRefresh: function() {
            this.state.autoRefreshTimer = setInterval(() => {
                this.refreshOnlineUsers();
            }, 30000); // 30 seconds
        },
        
        // Refresh online users list
        refreshOnlineUsers: function() {
            fetch('/api/online-users.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.users) {
                        this.updateOnlineUsers(data.users);
                    }
                })
                .catch(error => {
                    console.error('Error refreshing online users:', error);
                });
        },
        
        // Update online users display
        updateOnlineUsers: function(users) {
            const userList = this.elements.onlineUsersList;
            if (!userList) return;
            
            userList.innerHTML = '';
            
            users.slice(0, 10).forEach(user => {
                const userDiv = document.createElement('div');
                userDiv.className = `online-user status-${user.status}`;
                
                const statusDot = document.createElement('div');
                statusDot.className = 'status-dot';
                
                const userLink = document.createElement('a');
                userLink.href = `/blog.php?user=${encodeURIComponent(user.username)}`;
                userLink.className = 'username';
                userLink.textContent = user.display_name || user.username;
                
                const roleSpan = document.createElement('small');
                roleSpan.className = 'user-role';
                roleSpan.textContent = `(${user.role_display})`;
                
                userDiv.appendChild(statusDot);
                userDiv.appendChild(userLink);
                userDiv.appendChild(roleSpan);
                userList.appendChild(userDiv);
            });
            
            if (users.length > 10) {
                const moreDiv = document.createElement('div');
                moreDiv.className = 'more-users';
                const moreSmall = document.createElement('small');
                moreSmall.textContent = `+${users.length - 10} more online`;
                moreDiv.appendChild(moreSmall);
                userList.appendChild(moreDiv);
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
            
            document.body.appendChild(modal);
            document.body.style.overflow = 'hidden';
            
            // ESC key to close
            const handleEscape = (e) => {
                if (e.key === 'Escape') {
                    this.closePhotoModal(modal);
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);
        },
        
        // Close photo modal
        closePhotoModal: function(modal) {
            if (modal && modal.parentNode) {
                document.body.removeChild(modal);
                document.body.style.overflow = '';
                this.currentPhotoModal = null;
            }
        },
        
        // Delete post
        deletePost: function(postId) {
            if (!confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
                return;
            }
            
            const data = {
                post_id: postId,
                csrf_token: this.config.csrf_token
            };
            
            fetch('/api/delete-post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove post from DOM
                    const postElement = document.querySelector(`[data-post-id="${postId}"]`);
                    if (postElement) {
                        postElement.style.transition = 'opacity 0.5s';
                        postElement.style.opacity = '0';
                        setTimeout(() => {
                            if (postElement.parentNode) {
                                postElement.parentNode.removeChild(postElement);
                            }
                        }, 500);
                    }
                    
                    // Show success message
                    this.showFlashMessage('Post deleted successfully', 'success');
                } else {
                    alert('Failed to delete post: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error deleting post:', error);
                alert('Failed to delete post. Please try again.');
            });
        },
        
        // Handle infinite scroll
        handleScroll: function() {
            if (this.state.isLoading) return;
            
            // Check if near bottom of page
            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 1000) {
                this.loadMorePosts();
            }
        },
        
        // Load more posts (infinite scroll)
        loadMorePosts: function() {
            if (this.state.isLoading) return;
            
            const currentPage = this.config.current_page;
            const totalPages = this.config.total_pages;
            
            if (currentPage >= totalPages) return;
            
            this.state.isLoading = true;
            
            // Show loading indicator
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'loading-indicator';
            loadingDiv.style.cssText = `
                text-align: center;
                padding: 20px;
                color: gray;
            `;
            loadingDiv.textContent = 'Loading more posts...';
            
            if (this.elements.blogFeed) {
                this.elements.blogFeed.appendChild(loadingDiv);
            }
            
            // Build URL for next page
            const url = new URL(window.location);
            url.searchParams.set('page', currentPage + 1);
            url.searchParams.set('ajax', '1');
            
            fetch(url.toString())
                .then(response => response.text())
                .then(html => {
                    // Parse HTML and extract posts
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newPosts = doc.querySelectorAll('.blog-post');
                    
                    // Remove loading indicator
                    if (loadingDiv.parentNode) {
                        loadingDiv.parentNode.removeChild(loadingDiv);
                    }
                    
                    // Add new posts
                    newPosts.forEach(post => {
                        if (this.elements.blogFeed) {
                            this.elements.blogFeed.appendChild(post);
                        }
                    });
                    
                    // Update current page
                    this.config.current_page = currentPage + 1;
                    
                    // Rebind events for new posts
                    this.bindNewPostEvents(newPosts);
                    
                    this.state.isLoading = false;
                })
                .catch(error => {
                    console.error('Error loading more posts:', error);
                    if (loadingDiv.parentNode) {
                        loadingDiv.parentNode.removeChild(loadingDiv);
                    }
                    this.state.isLoading = false;
                });
        },
        
        // Bind events for newly loaded posts
        bindNewPostEvents: function(posts) {
            posts.forEach(post => {
                // Photo viewing
                const photos = post.querySelectorAll('.post-photo');
                photos.forEach(photo => {
                    photo.addEventListener('click', () => {
                        const fullSrc = photo.getAttribute('data-full-src');
                        if (fullSrc) {
                            this.viewPhoto(fullSrc);
                        }
                    });
                });
                
                // Delete buttons
                const deleteBtn = post.querySelector('.delete-btn');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', () => {
                        const postId = deleteBtn.getAttribute('data-post-id');
                        if (postId) {
                            this.deletePost(parseInt(postId));
                        }
                    });
                }
            });
        },
        
        // Show flash message
        showFlashMessage: function(message, type = 'info') {
            const flashContainer = document.querySelector('.flash-messages') || this.createFlashContainer();
            
            const flashMessage = document.createElement('div');
            flashMessage.className = `flash-message flash-${type}`;
            flashMessage.textContent = message;
            
            flashContainer.appendChild(flashMessage);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (flashMessage.parentNode) {
                    flashMessage.style.transition = 'opacity 0.5s';
                    flashMessage.style.opacity = '0';
                    setTimeout(() => {
                        if (flashMessage.parentNode) {
                            flashMessage.parentNode.removeChild(flashMessage);
                        }
                    }, 500);
                }
            }, 5000);
        },
        
        // Create flash container if it doesn't exist
        createFlashContainer: function() {
            const container = document.createElement('div');
            container.className = 'flash-messages';
            
            const mainContent = document.querySelector('.container');
            const filters = document.querySelector('.filters');
            if (mainContent && filters) {
                mainContent.insertBefore(container, filters);
            }
            
            return container;
        },
        
        // Handle errors
        handleError: function(error) {
            console.error('Blog page error:', error);
            this.showFlashMessage('An error occurred. Please try again.', 'error');
        },
        
        // Clean up resources
        destroy: function() {
            this.state.isLoading = false;
            
            if (this.state.autoRefreshTimer) {
                clearInterval(this.state.autoRefreshTimer);
                this.state.autoRefreshTimer = null;
            }
            
            if (this.currentPhotoModal) {
                this.closePhotoModal(this.currentPhotoModal);
            }
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            BroChatBlog.init();
        });
    } else {
        BroChatBlog.init();
    }
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        BroChatBlog.destroy();
    });
    
})();
