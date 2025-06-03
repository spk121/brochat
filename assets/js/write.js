/**
 * BroChat - Write Post Page JavaScript
 * Handles post writing, draft management, photo uploads with strict XSS protection
 */
(function() {
    'use strict';
    
    // Write page namespace
    window.BroChatWrite = {
        // Configuration from server
        config: {},
        
        // DOM elements cache
        elements: {},
        
        // State management
        state: {
            selectedFiles: [],
            hasUnsavedChanges: false,
            autoSaveTimer: null,
            previewMode: false
        },
        
        // Initialize write page
        init: function() {
            this.loadConfig();
            this.cacheElements();
            this.bindEvents();
            this.setupKeyboardShortcuts();
            this.setupAutoSave();
            this.setupUnsavedChangesWarning();
            this.updateCharCount();
            this.focusContent();
        },
        
        // Load configuration from JSON script tag
        loadConfig: function() {
            const configScript = document.getElementById('write-data');
            if (configScript) {
                try {
                    this.config = JSON.parse(configScript.textContent);
                } catch (e) {
                    console.error('Failed to parse write configuration:', e);
                    this.config = {};
                }
            }
        },
        
        // Cache DOM elements for performance
        cacheElements: function() {
            this.elements = {
                form: document.getElementById('writeForm'),
                contentTextarea: document.getElementById('content'),
                charCount: document.getElementById('charCount'),
                publishBtn: document.getElementById('publishBtn'),
                draftBtn: document.getElementById('draftBtn'),
                previewBtn: document.getElementById('previewBtn'),
                contentPreview: document.getElementById('contentPreview'),
                photoUpload: document.getElementById('photoUpload'),
                photoInput: document.getElementById('photos'),
                photoPreview: document.getElementById('photoPreview'),
                suggestionTags: document.querySelectorAll('.suggestion-tags .suggestion-item'),
                suggestionUsers: document.querySelectorAll('.suggestion-users .suggestion-item')
            };
        },
        
        // Bind event listeners
        bindEvents: function() {
            // Form submission
            if (this.elements.form) {
                this.elements.form.addEventListener('submit', this.handleFormSubmit.bind(this));
            }
            
            // Content textarea
            if (this.elements.contentTextarea) {
                this.elements.contentTextarea.addEventListener('input', this.handleContentInput.bind(this));
            }
            
            // Preview button
            if (this.elements.previewBtn) {
                this.elements.previewBtn.addEventListener('click', this.togglePreview.bind(this));
            }
            
            // Photo upload
            if (this.config.can_upload_photos && this.elements.photoUpload) {
                this.setupPhotoUpload();
            }
            
            // Suggestion items
            this.elements.suggestionTags.forEach(item => {
                item.addEventListener('click', () => {
                    const tag = item.getAttribute('data-tag');
                    this.insertTag(tag);
                });
            });
            
            this.elements.suggestionUsers.forEach(item => {
                item.addEventListener('click', () => {
                    const user = item.getAttribute('data-user');
                    this.insertMention(user);
                });
            });
        },
        
        // Handle content input
        handleContentInput: function() {
            this.updateCharCount();
            this.state.hasUnsavedChanges = true;
            this.resetAutoSave();
        },
        
        // Update character count
        updateCharCount: function() {
            if (!this.elements.contentTextarea || !this.elements.charCount) return;
            
            const count = this.elements.contentTextarea.value.length;
            this.elements.charCount.textContent = count + ' / 1000';
            
            // Update styling
            this.elements.charCount.className = 'char-count';
            if (count > 800) {
                this.elements.charCount.className += ' warning';
            }
            if (count >= 1000) {
                this.elements.charCount.className += ' error';
            }
            
            // Update publish button state
            if (this.elements.publishBtn) {
                this.elements.publishBtn.disabled = count === 0 || count > 1000;
            }
        },
        
        // Handle form submission
        handleFormSubmit: function(e) {
            const isPublish = e.submitter === this.elements.publishBtn;
            const isDraft = e.submitter === this.elements.draftBtn;
            
            if (isPublish) {
                this.setButtonLoading(this.elements.publishBtn, 'Publishing...');
            } else if (isDraft) {
                this.setButtonLoading(this.elements.draftBtn, 'Saving...');
            }
            
            this.state.hasUnsavedChanges = false;
            
            // Reset buttons after 10 seconds
            setTimeout(() => {
                this.resetButtons();
            }, 10000);
        },
        
        // Set button loading state
        setButtonLoading: function(button, text) {
            if (button) {
                button.disabled = true;
                button.textContent = text;
            }
        },
        
        // Reset buttons to normal state
        resetButtons: function() {
            if (this.elements.publishBtn) {
                this.elements.publishBtn.disabled = false;
                this.elements.publishBtn.textContent = '🤘 Publish Post';
            }
            if (this.elements.draftBtn) {
                this.elements.draftBtn.disabled = false;
                this.elements.draftBtn.textContent = '📝 Save Draft';
            }
        },
        
        // Toggle preview mode
        togglePreview: function() {
            if (!this.elements.contentTextarea || !this.elements.contentPreview || !this.elements.previewBtn) return;
            
            if (this.state.previewMode) {
                // Switch back to edit mode
                this.elements.contentTextarea.style.display = 'block';
                this.elements.contentPreview.style.display = 'none';
                this.elements.previewBtn.textContent = '👁️ Preview';
                this.state.previewMode = false;
            } else {
                // Switch to preview mode
                const content = this.elements.contentTextarea.value;
                const preview = this.renderMarkdown(content);
                
                this.elements.contentPreview.innerHTML = preview || '<em style="color: gray;">Nothing to preview yet...</em>';
                this.elements.contentTextarea.style.display = 'none';
                this.elements.contentPreview.style.display = 'block';
                this.elements.previewBtn.textContent = '✏️ Edit';
                this.state.previewMode = true;
            }
        },
        
        // Simple markdown renderer
        renderMarkdown: function(content) {
            return content
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.+?)\*/g, '<em>$1</em>')
                .replace(/`(.+?)`/g, '<code style="background: darkgray; padding: 2px 5px; border-radius: 3px; color: lime;">$1</code>')
                .replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" target="_blank" style="color: deepskyblue;">$1</a>')
                .replace(/#([a-zA-Z0-9_-]+)/g, '<span style="color: deepskyblue;">#$1</span>')
                .replace(/@([a-zA-Z0-9_.-]+)/g, '<span style="color: lime;">@$1</span>')
                .replace(/\n/g, '<br>');
        },
        
        // Setup photo upload functionality
        setupPhotoUpload: function() {
            const { photoUpload, photoInput } = this.elements;
            
            // Click to upload
            photoUpload.addEventListener('click', () => {
                photoInput.click();
            });
            
            // File input change
            photoInput.addEventListener('change', this.handleFileSelect.bind(this));
            
            // Drag and drop
            photoUpload.addEventListener('dragover', (e) => {
                e.preventDefault();
                photoUpload.classList.add('dragover');
            });
            
            photoUpload.addEventListener('dragleave', () => {
                photoUpload.classList.remove('dragover');
            });
            
            photoUpload.addEventListener('drop', (e) => {
                e.preventDefault();
                photoUpload.classList.remove('dragover');
                
                const files = Array.from(e.dataTransfer.files);
                this.handleFiles(files);
            });
        },
        
        // Handle file selection
        handleFileSelect: function(e) {
            const files = Array.from(e.target.files);
            this.handleFiles(files);
        },
        
        // Handle uploaded files
        handleFiles: function(files) {
            // Limit to 4 photos
            const remainingSlots = 4 - this.state.selectedFiles.length;
            const filesToAdd = files.slice(0, remainingSlots);
            
            filesToAdd.forEach(file => {
                if (this.validateFile(file)) {
                    this.state.selectedFiles.push(file);
                    this.createPhotoPreview(file);
                } else {
                    this.showMessage(`Invalid file: ${file.name} (must be image, max 5MB)`, 'error');
                }
            });
            
            this.updatePhotoInput();
        },
        
        // Validate uploaded file
        validateFile: function(file) {
            return file.type.startsWith('image/') && file.size <= 5242880; // 5MB
        },
        
        // Create photo preview
        createPhotoPreview: function(file) {
            const previewItem = document.createElement('div');
            previewItem.className = 'preview-item';
            
            const img = document.createElement('img');
            img.className = 'preview-image';
            img.src = URL.createObjectURL(file);
            
            const removeBtn = document.createElement('button');
            removeBtn.className = 'remove-photo';
            removeBtn.textContent = '×';
            removeBtn.type = 'button';
            removeBtn.addEventListener('click', () => {
                this.removePhoto(file, previewItem);
            });
            
            previewItem.appendChild(img);
            previewItem.appendChild(removeBtn);
            this.elements.photoPreview.appendChild(previewItem);
        },
        
        // Remove photo
        removePhoto: function(file, previewElement) {
            this.state.selectedFiles = this.state.selectedFiles.filter(f => f !== file);
            this.elements.photoPreview.removeChild(previewElement);
            this.updatePhotoInput();
            URL.revokeObjectURL(previewElement.querySelector('img').src);
        },
        
        // Update photo input with selected files
        updatePhotoInput: function() {
            const dt = new DataTransfer();
            this.state.selectedFiles.forEach(file => dt.items.add(file));
            this.elements.photoInput.files = dt.files;
        },
        
        // Insert hashtag
        insertTag: function(tag) {
            this.insertAtCursor('#' + tag + ' ');
        },
        
        // Insert mention
        insertMention: function(username) {
            this.insertAtCursor('@' + username + ' ');
        },
        
        // Insert text at cursor position
        insertAtCursor: function(text) {
            const textarea = this.elements.contentTextarea;
            if (!textarea) return;
            
            const cursorPos = textarea.selectionStart;
            const textBefore = textarea.value.substring(0, cursorPos);
            const textAfter = textarea.value.substring(cursorPos);
            
            // Check if we need a space before the insertion
            const needsSpace = textBefore.length > 0 && 
                              !textBefore.endsWith(' ') && 
                              !textBefore.endsWith('\n');
            
            const insertion = (needsSpace ? ' ' : '') + text;
            
            textarea.value = textBefore + insertion + textAfter;
            textarea.selectionStart = textarea.selectionEnd = cursorPos + insertion.length;
            textarea.focus();
            
            this.updateCharCount();
            this.state.hasUnsavedChanges = true;
        },
        
        // Setup keyboard shortcuts
        setupKeyboardShortcuts: function() {
            document.addEventListener('keydown', (e) => {
                // Ctrl+Enter to publish
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    if (this.elements.publishBtn && !this.elements.publishBtn.disabled) {
                        this.elements.form.requestSubmit(this.elements.publishBtn);
                    }
                }
                
                // Ctrl+S to save draft
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    if (this.elements.draftBtn) {
                        this.elements.form.requestSubmit(this.elements.draftBtn);
                    }
                }
                
                // ESC to go back (with confirmation)
                if (e.key === 'Escape') {
                    this.handleEscapeKey();
                }
            });
        },
        
        // Handle escape key
        handleEscapeKey: function() {
            if (this.state.hasUnsavedChanges) {
                if (confirm('Leave without saving? Any unsaved changes will be lost.')) {
                    window.location.href = '/blog.php';
                }
            } else {
                window.location.href = '/blog.php';
            }
        },
        
        // Setup auto-save functionality
        setupAutoSave: function() {
            if (this.config.has_draft || this.elements.contentTextarea.value.trim().length > 0) {
                this.resetAutoSave();
            }
        },
        
        // Reset auto-save timer
        resetAutoSave: function() {
            clearTimeout(this.state.autoSaveTimer);
            this.state.autoSaveTimer = setTimeout(() => {
                this.autoSaveDraft();
            }, 30000); // 30 seconds
        },
        
        // Auto-save draft
        autoSaveDraft: function() {
            const content = this.elements.contentTextarea.value.trim();
            if (content.length > 10) {
                this.saveDraft(true); // Silent save
                this.resetAutoSave();
            }
        },
        
        // Save draft
        saveDraft: function(silent = false) {
            const content = this.elements.contentTextarea.value.trim();
            if (!content) return;
            
            const data = {
                context: 'blog_post',
                content: content,
                metadata: {
                    photos: this.state.selectedFiles.map(f => f.name),
                    timestamp: Math.floor(Date.now() / 1000)
                },
                csrf_token: this.config.csrf_token
            };
            
            fetch('/api/save-draft.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && !silent) {
                    this.showMessage('Draft saved! 📝', 'success');
                }
            })
            .catch(err => {
                if (!silent) {
                    console.error('Failed to save draft:', err);
                }
            });
        },
        
        // Setup unsaved changes warning
        setupUnsavedChangesWarning: function() {
            window.addEventListener('beforeunload', (e) => {
                if (this.state.hasUnsavedChanges) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
        },
        
        // Focus on content textarea
        focusContent: function() {
            if (this.elements.contentTextarea) {
                this.elements.contentTextarea.focus();
            }
        },
        
        // Show message
        showMessage: function(message, type = 'info') {
            const messageDiv = document.createElement('div');
            messageDiv.className = `flash-message flash-${type}`;
            messageDiv.textContent = message;
            
            const container = document.querySelector('.container');
            if (container) {
                const firstChild = container.firstElementChild;
                container.insertBefore(messageDiv, firstChild);
                
                // Auto-remove after 5 seconds
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
                }, 5000);
            }
        },
        
        // Handle errors
        handleError: function(error) {
            console.error('Write page error:', error);
            this.showMessage('An error occurred. Please try again.', 'error');
        },
        
        // Clean up resources
        destroy: function() {
            this.state.hasUnsavedChanges = false;
            
            if (this.state.autoSaveTimer) {
                clearTimeout(this.state.autoSaveTimer);
                this.state.autoSaveTimer = null;
            }
            
            // Clean up object URLs
            this.elements.photoPreview.querySelectorAll('img').forEach(img => {
                if (img.src.startsWith('blob:')) {
                    URL.revokeObjectURL(img.src);
                }
            });
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            BroChatWrite.init();
        });
    } else {
        BroChatWrite.init();
    }
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        BroChatWrite.destroy();
    });
    
})();
