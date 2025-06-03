/**
 * BroChat - Main JavaScript Module
 * Handles all client-side functionality with strict XSS protection
 */
(function() {
    'use strict';
    
    // Main BroChat namespace
    window.BroChat = {
        // Configuration from server
        config: window.BroChatData || {},
        
        // DOM elements cache
        elements: {},
        
        // State management
        state: {
            isPlaying: false,
            autoRefreshEnabled: true,
            draftSaveTimer: null
        },
        
        // Initialize application
        init: function() {
            this.loadConfig();
            this.cacheElements();
            this.bindEvents();
            this.initStreamPlayer();
            this.initCharCounter();
            this.startAutoRefresh();
            this.startAutoDraftSave();
        },
        
        // Load configuration from JSON script tag
        loadConfig: function() {
            const configScript = document.getElementById('brochat-data');
            if (configScript) {
                try {
                    this.config = JSON.parse(configScript.textContent);
                } catch (e) {
                    console.error('Failed to parse configuration:', e);
                    this.config = {};
                }
            }
        },
        
        // Cache DOM elements for performance
        cacheElements: function() {
            this.elements = {
                contentTextarea: document.getElementById('content'),
                charCount: document.getElementById('char-count'),
                audioPlayer: document.getElementById('audioPlayer'),
                playButton: document.getElementById('playButton'),
                volumeSlider: document.getElementById('volumeSlider'),
                chatPreview: document.getElementById('chatPreview')
            };
        },
        
        // Bind event listeners
        bindEvents: function() {
            // Volume control
            if (this.elements.volumeSlider) {
                this.elements.volumeSlider.addEventListener('input', this.handleVolumeChange.bind(this));
            }
            
            // Character counter
            if (this.elements.contentTextarea) {
                this.elements.contentTextarea.addEventListener('input', this.updateCharCount.bind(this));
            }
            
            // Play button
            if (this.elements.playButton) {
                this.elements.playButton.addEventListener('click', this.toggleStream.bind(this));
            }
            
            // Save draft button
            const saveDraftBtn = document.getElementById('saveDraftBtn');
            if (saveDraftBtn) {
                saveDraftBtn.addEventListener('click', this.saveDraft.bind(this));
            }
            
            // Photo viewing
            const postPhotos = document.querySelectorAll('.post-photo');
            postPhotos.forEach(photo => {
                photo.addEventListener('click', () => {
                    const fullSrc = photo.getAttribute('data-full-src');
                    if (fullSrc) {
                        this.viewPhoto(fullSrc);
                    }
                });
            });
        },
        
        // Initialize stream player
        initStreamPlayer: function() {
            const { audioPlayer, volumeSlider } = this.elements;
            
            if (audioPlayer && volumeSlider) {
                // Set initial volume
                audioPlayer.volume = this.config.stream_volume / 100;
                
                // Set up audio event listeners
                audioPlayer.addEventListener('play', this.handleStreamPlay.bind(this));
                audioPlayer.addEventListener('pause', this.handleStreamPause.bind(this));
                audioPlayer.addEventListener('error', this.handleStreamError.bind(this));
            }
        },
        
        // Initialize character counter
        initCharCounter: function() {
            if (this.elements.contentTextarea && this.elements.charCount) {
                this.updateCharCount();
            }
        },
        
        // Update character count display
        updateCharCount: function() {
            const textarea = this.elements.contentTextarea;
            const counter = this.elements.charCount;
            
            if (!textarea || !counter) return;
            
            const count = textarea.value.length;
            counter.textContent = count + ' / 1000';
            
            // Update styling based on character count
            counter.className = 'char-count';
            if (count > 900) {
                counter.className += ' warning';
            }
            if (count >= 1000) {
                counter.className += ' error';
            }
        },
        
        // Handle volume changes
        handleVolumeChange: function(event) {
            const volume = event.target.value;
            
            if (this.elements.audioPlayer) {
                this.elements.audioPlayer.volume = volume / 100;
            }
            
            // Save volume preference
            this.savePreference('stream_volume', volume);
        },
        
        // Handle stream play event
        handleStreamPlay: function() {
            this.state.isPlaying = true;
            if (this.elements.playButton) {
                this.elements.playButton.textContent = '⏸ PAUSE';
            }
            
            // Log stream connection
            this.logStreamConnection();
        },
        
        // Handle stream pause event
        handleStreamPause: function() {
            this.state.isPlaying = false;
            if (this.elements.playButton) {
                this.elements.playButton.textContent = '▶ PLAY';
            }
        },
        
        // Handle stream error
        handleStreamError: function(error) {
            console.error('Stream error:', error);
            this.showAlert('Failed to connect to stream. Please try again.');
            
            if (this.elements.playButton) {
                this.elements.playButton.textContent = '▶ PLAY';
            }
            this.state.isPlaying = false;
        },
        
        // Toggle stream playback
        toggleStream: function() {
            const player = this.elements.audioPlayer;
            if (!player) return;
            
            if (this.state.isPlaying) {
                player.pause();
            } else {
                const playPromise = player.play();
                
                if (playPromise !== undefined) {
                    playPromise.catch(this.handleStreamError.bind(this));
                }
            }
        },
        
        // Save user preference
        savePreference: function(key, value) {
            const data = {
                key: key,
                value: value
            };
            
            this.makeRequest('/api/save-preference.php', 'POST', data)
                .catch(error => console.error('Failed to save preference:', error));
        },
        
        // Log stream connection
        logStreamConnection: function() {
            this.makeRequest('/api/stream-connect.php', 'POST', {})
                .catch(error => console.error('Failed to log stream connection:', error));
        },
        
        // Save draft
        saveDraft: function() {
            const textarea = this.elements.contentTextarea;
            if (!textarea || !this.config.is_logged_in) return;
            
            const content = textarea.value.trim();
            if (!content) return;
            
            const data = {
                context: 'homepage_post',
                content: content,
                csrf_token: this.config.csrf_token
            };
            
            this.makeRequest('/api/save-draft.php', 'POST', data)
                .then(response => {
                    if (response.success) {
                        this.showAlert('Draft saved!', 'success');
                    }
                })
                .catch(error => console.error('Failed to save draft:', error));
        },
        
        // View photo in modal
        viewPhoto: function(src) {
            // Sanitize the src parameter
            if (typeof src !== 'string' || !src.match(/^\/uploads\/photos\/[a-zA-Z0-9_\-\.]+$/)) {
                console.error('Invalid photo source');
                return;
            }
            
            const modal = document.createElement('div');
            modal.className = 'photo-modal';
            
            const img = document.createElement('img');
            img.src = src;
            img.alt = 'Photo';
            
            modal.appendChild(img);
            modal.addEventListener('click', function() {
                document.body.removeChild(modal);
            });
            
            document.body.appendChild(modal);
        },
        
        // Start auto-refresh for chat preview
        startAutoRefresh: function() {
            if (!this.state.autoRefreshEnabled) return;
            
            setInterval(() => {
                this.refreshChatPreview();
            }, 30000); // 30 seconds
        },
        
        // Refresh chat preview
        refreshChatPreview: function() {
            if (!this.elements.chatPreview) return;
            
            this.makeRequest('/api/chat-preview.php', 'GET')
                .then(html => {
                    if (typeof html === 'string') {
                        this.elements.chatPreview.innerHTML = html;
                    }
                })
                .catch(error => console.error('Failed to refresh chat:', error));
        },
        
        // Start auto-draft save
        startAutoDraftSave: function() {
            if (!this.elements.contentTextarea || !this.config.is_logged_in) return;
            
            this.state.draftSaveTimer = setInterval(() => {
                const content = this.elements.contentTextarea.value.trim();
                if (content && content.length > 10) {
                    this.saveDraft();
                }
            }, 30000); // 30 seconds
        },
        
        // Make secure AJAX request
        makeRequest: function(url, method = 'GET', data = null) {
            const options = {
                method: method,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };
            
            if (method === 'POST' && data) {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            }
            
            return fetch(url, options)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        return response.text();
                    }
                });
        },
        
        // Show alert message
        showAlert: function(message, type = 'info') {
            // Create a simple alert for now
            // In a full implementation, this would show a styled notification
            alert(message);
        },
        
        // Clean up resources
        destroy: function() {
            if (this.state.draftSaveTimer) {
                clearInterval(this.state.draftSaveTimer);
            }
            
            this.state.autoRefreshEnabled = false;
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            BroChat.init();
        });
    } else {
        BroChat.init();
    }
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        BroChat.destroy();
    });
    
})();
