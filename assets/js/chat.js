// BroChat Real-time Chat JavaScript
document.addEventListener('DOMContentLoaded', function() {
    window.punkChat = new PunkRockChat();
    initializeEventHandlers();
    initializeKeyboardShortcuts();
    autoHideFlashMessages();
    handlePageVisibility();
});

// Chat functionality
class PunkRockChat {
    constructor() {
        this.chatMessages = document.getElementById('chatMessages');
        this.chatInput = document.getElementById('chatInput');
        this.chatForm = document.getElementById('chatForm');
        this.sendBtn = document.getElementById('sendBtn');
        this.charCount = document.getElementById('charCount');
        this.typingIndicator = document.getElementById('typingIndicator');
        this.onlineUsers = document.getElementById('onlineUsers');
        this.onlineCount = document.getElementById('onlineCount');
        this.connectionStatus = document.getElementById('connectionStatus');
        
        this.lastMessageTime = window.BroChatData.lastMessageTime;
        this.isTyping = false;
        this.typingTimer = null;
        this.updateTimer = null;
        this.isConnected = true;
        
        this.init();
    }
    
    init() {
        // Form submission
        this.chatForm.addEventListener('submit', (e) => this.sendMessage(e));
        
        // Character counter
        this.chatInput.addEventListener('input', () => this.updateCharCount());
        
        // Typing indicator
        this.chatInput.addEventListener('input', () => this.handleTyping());
        this.chatInput.addEventListener('keydown', (e) => this.handleKeydown(e));
        
        // Auto-resize textarea
        this.chatInput.addEventListener('input', () => this.autoResize());
        
        // Start update loops
        this.startMessageUpdates();
        this.startUserUpdates();
        this.startTypingUpdates();
        
        // Scroll to bottom
        this.scrollToBottom();
        
        // Focus input
        this.chatInput.focus();
        
        // Mark as online
        this.markOnline();
    }
    
    sendMessage(e) {
        e.preventDefault();
        
        const message = this.chatInput.value.trim();
        if (!message) return;
        
        // Disable form temporarily
        this.sendBtn.disabled = true;
        this.chatInput.disabled = true;
        
        // Process commands
        if (this.processCommand(message)) {
            this.chatInput.value = '';
            this.updateCharCount();
            this.sendBtn.disabled = false;
            this.chatInput.disabled = false;
            this.chatInput.focus();
            return;
        }
        
        // Send message via AJAX
        fetch('/chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                ajax: '1',
                action: 'send_message',
                message: message,
                csrf_token: window.BroChatData.csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.chatInput.value = '';
                this.updateCharCount();
                this.setTyping(false);
                this.scrollToBottom();
            } else {
                this.showError(data.error || 'Failed to send message');
            }
        })
        .catch(error => {
            console.error('Error sending message:', error);
            this.showError('Connection error. Please try again.');
            this.updateConnectionStatus(false);
        })
        .finally(() => {
            this.sendBtn.disabled = false;
            this.chatInput.disabled = false;
            this.chatInput.focus();
        });
    }
    
    processCommand(message) {
        if (!message.startsWith('/')) return false;
        
        const parts = message.split(' ');
        const command = parts[0].toLowerCase();
        const args = parts.slice(1).join(' ');
        
        switch (command) {
            case '/me':
                if (args) {
                    this.sendActionMessage(args);
                }
                return true;
                
            case '/shrug':
                this.chatInput.value = '¯\\_(ツ)_/¯';
                return false; // Let it send normally
                
            case '/punk':
                this.chatInput.value = '🤘 PUNK ROCK FOREVER! 🤘';
                return false;
                
            case '/quote':
                this.insertPunkQuote();
                return true;
                
            case '/help':
                this.showHelp();
                return true;
                
            case '/clear':
                this.clearChat();
                return true;
                
            default:
                this.showError(`Unknown command: ${command}. Type /help for commands.`);
                return true;
        }
    }
    
    sendActionMessage(action) {
        const actionMessage = `* ${window.BroChatData.currentUsername} ${action}`;
        
        // Send as regular message but formatted as action
        fetch('/chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                ajax: '1',
                action: 'send_message',
                message: actionMessage,
                csrf_token: window.BroChatData.csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                this.showError(data.error || 'Failed to send action');
            }
        });
    }
    
    insertPunkQuote() {
        const quotes = [
            "Punk is not dead. Punk will only die when corporations can exploit and mass produce it. - Jello Biafra",
            "The only performance that makes it is the one that achieves madness. - Mick Jagger", 
            "I'd rather be hated for who I am, than loved for who I am not. - Kurt Cobain",
            "Punk rock should mean freedom. - Kurt Cobain",
            "The important thing is to keep playing, to play against all odds. - Johnny Rotten"
        ];
        
        const randomQuote = quotes[Math.floor(Math.random() * quotes.length)];
        this.chatInput.value = randomQuote;
    }
    
    showHelp() {
        this.addSystemMessage(`
            <strong>🤘 Punk Rock Chat Commands:</strong><br>
            /me [action] - Action message<br>
            /shrug - ¯\\_(ツ)_/¯<br>
            /punk - Show punk rock spirit<br>
            /quote - Random punk quote<br>
            /clear - Clear your chat window<br>
            /help - Show this help<br><br>
            <strong>Text Formatting:</strong><br>
            ^03colored text^ - IRC-style colors<br>
            :rock: :punk: :fire: - Emojis<br>
            @username - Mention someone<br>
            #hashtag - Create hashtag
        `);
    }
    
    clearChat() {
        this.chatMessages.innerHTML = '';
        this.addSystemMessage('Chat cleared locally. Other users can still see all messages.');
    }
    
    addSystemMessage(content) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message system-message';
        messageDiv.innerHTML = `
            <div class="message-header">
                <span class="message-author">System</span>
                <span class="message-time">${new Date().toLocaleTimeString('en-US', {hour12: false, hour: '2-digit', minute: '2-digit'})}</span>
            </div>
            <div class="message-content">${content}</div>
        `;
        
        this.chatMessages.appendChild(messageDiv);
        this.scrollToBottom();
    }
    
    updateCharCount() {
        const count = this.chatInput.value.length;
        this.charCount.textContent = `${count} / 500`;
        
        this.charCount.className = 'char-count';
        if (count > 400) this.charCount.className += ' warning';
        if (count >= 500) this.charCount.className += ' error';
        
        this.sendBtn.disabled = count === 0 || count > 500;
    }
    
    autoResize() {
        this.chatInput.style.height = 'auto';
        this.chatInput.style.height = Math.min(this.chatInput.scrollHeight, 120) + 'px';
    }
    
    handleTyping() {
        if (!this.isTyping) {
            this.setTyping(true);
        }
        
        clearTimeout(this.typingTimer);
        this.typingTimer = setTimeout(() => {
            this.setTyping(false);
        }, 3000);
    }
    
    setTyping(typing) {
        if (this.isTyping === typing) return;
        
        this.isTyping = typing;
        
        fetch('/chat.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                ajax: '1',
                action: 'typing',
                typing: typing.toString(),
                csrf_token: window.BroChatData.csrfToken
            })
        })
        .catch(error => console.error('Error updating typing status:', error));
    }
    
    handleKeydown(e) {
        // Enter to send (Shift+Enter for new line)
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            this.chatForm.dispatchEvent(new Event('submit', {bubbles: true}));
        }
        
        // Tab for mention autocomplete
        if (e.key === 'Tab') {
            e.preventDefault();
            this.handleMentionAutocomplete();
        }
    }
    
    handleMentionAutocomplete() {
        const text = this.chatInput.value;
        const cursorPos = this.chatInput.selectionStart;
        
        // Find the @ symbol before cursor
        const beforeCursor = text.substring(0, cursorPos);
        const match = beforeCursor.match(/@(\w*)$/);
        
        if (match) {
            const partial = match[1].toLowerCase();
            const users = Array.from(document.querySelectorAll('.online-user .username'))
                .map(el => el.textContent.trim())
                .filter(username => username.toLowerCase().startsWith(partial));
            
            if (users.length > 0) {
                const completion = users[0];
                const before = text.substring(0, cursorPos - match[1].length);
                const after = text.substring(cursorPos);
                
                this.chatInput.value = before + completion + ' ' + after;
                this.chatInput.selectionStart = this.chatInput.selectionEnd = before.length + completion.length + 1;
            }
        }
    }
    
    startMessageUpdates() {
        this.updateTimer = setInterval(() => {
            this.fetchNewMessages();
        }, 2000); // Check every 2 seconds
    }
    
    fetchNewMessages() {
        fetch(`/chat.php?ajax=messages&since=${encodeURIComponent(this.lastMessageTime)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages.length > 0) {
                    data.messages.forEach(message => this.addMessage(message));
                    this.lastMessageTime = data.current_time;
                    this.scrollToBottom();
                }
                this.updateConnectionStatus(true);
            })
            .catch(error => {
                console.error('Error fetching messages:', error);
                this.updateConnectionStatus(false);
            });
    }
    
    addMessage(message) {
        const isOwnMessage = message.user_id == window.BroChatData.currentUserId;
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${isOwnMessage ? 'own-message' : ''}`;
        messageDiv.setAttribute('data-message-id', message.id);
        
        const time = new Date(message.timestamp).toLocaleTimeString('en-US', {
            hour12: false, 
            hour: '2-digit', 
            minute: '2-digit'
        });
        
        const canDelete = window.BroChatData.canModerate || isOwnMessage;
        
        messageDiv.innerHTML = `
            <div class="message-header">
                <span class="message-author role-${message.role ? message.role.toLowerCase() : 'fan'}">
                    ${this.escapeHtml(message.username)}
                </span>
                <span class="message-time">${time}</span>
            </div>
            <div class="message-content">
                ${message.formatted_message}
            </div>
            ${canDelete ? `<div class="message-actions"><button class="action-btn" data-action="delete-message" data-message-id="${message.id}">🗑️</button></div>` : ''}
        `;
        
        this.chatMessages.appendChild(messageDiv);
        
        // Remove old messages if too many
        const messages = this.chatMessages.children;
        if (messages.length > 100) {
            this.chatMessages.removeChild(messages[0]);
        }
    }
    
    startUserUpdates() {
        setInterval(() => {
            fetch('/chat.php?ajax=online_users')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.updateOnlineUsers(data.users);
                        this.onlineCount.textContent = data.count;
                    }
                })
                .catch(error => console.error('Error updating users:', error));
        }, 30000); // Update every 30 seconds
    }
    
    updateOnlineUsers(users) {
        this.onlineUsers.innerHTML = '';
        
        users.forEach(user => {
            const userDiv = document.createElement('div');
            userDiv.className = `online-user status-${user.status}`;
            userDiv.setAttribute('data-action', 'mention-user');
            userDiv.setAttribute('data-username', user.username);
            
            userDiv.innerHTML = `
                <div class="status-dot"></div>
                <div class="username role-${user.role ? user.role.toLowerCase() : 'fan'}">
                    ${this.escapeHtml(user.display_name || user.username)}
                </div>
            `;
            
            this.onlineUsers.appendChild(userDiv);
        });
    }
    
    startTypingUpdates() {
        setInterval(() => {
            fetch('/chat.php?ajax=typing')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.updateTypingIndicator(data.typing_users);
                    }
                })
                .catch(error => console.error('Error updating typing:', error));
        }, 1000); // Update every second
    }
    
    updateTypingIndicator(typingUsers) {
        if (typingUsers.length === 0) {
            this.typingIndicator.innerHTML = '';
            return;
        }
        
        const names = typingUsers.map(user => user.username);
        let text = '';
        
        if (names.length === 1) {
            text = `${names[0]} is typing`;
        } else if (names.length === 2) {
            text = `${names[0]} and ${names[1]} are typing`;
        } else {
            text = `${names[0]} and ${names.length - 1} others are typing`;
        }
        
        this.typingIndicator.innerHTML = `
            ${text}<span class="typing-dots"><span>.</span><span>.</span><span>.</span></span>
        `;
    }
    
    updateConnectionStatus(connected) {
        if (this.isConnected === connected) return;
        
        this.isConnected = connected;
        
        if (connected) {
            this.connectionStatus.className = 'connection-status connected';
            this.connectionStatus.innerHTML = '🟢 Connected to chat';
        } else {
            this.connectionStatus.className = 'connection-status disconnected';
            this.connectionStatus.innerHTML = '🔴 Connection lost - trying to reconnect...';
        }
    }
    
    scrollToBottom() {
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }
    
    markOnline() {
        // Send heartbeat every 60 seconds
        setInterval(() => {
            fetch('/api/online-heartbeat.php', {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            }).catch(error => console.error('Heartbeat error:', error));
        }, 60000);
    }
    
    showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'flash-message flash-error';
        errorDiv.textContent = message;
        
        const flashContainer = document.getElementById('flashMessages') || this.createFlashContainer();
        flashContainer.appendChild(errorDiv);
        
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.style.opacity = '0';
                setTimeout(() => errorDiv.remove(), 500);
            }
        }, 5000);
    }
    
    createFlashContainer() {
        const container = document.createElement('div');
        container.id = 'flashMessages';
        container.className = 'flash-messages';
        document.body.appendChild(container);
        return container;
    }
    
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
}

// Global event handlers
function initializeEventHandlers() {
    // Mention user click handler
    document.addEventListener('click', function(e) {
        const mentionButton = e.target.closest('[data-action="mention-user"]');
        if (mentionButton) {
            const username = mentionButton.getAttribute('data-username');
            if (username) {
                mentionUser(username);
            }
        }
        
        // Emoji button click handler
        const emojiButton = e.target.closest('.emoji-btn');
        if (emojiButton) {
            const emoji = emojiButton.getAttribute('data-emoji');
            if (emoji) {
                insertEmoji(emoji);
            }
        }
        
        // Delete message button handler
        const deleteButton = e.target.closest('[data-action="delete-message"]');
        if (deleteButton) {
            const messageId = deleteButton.getAttribute('data-message-id');
            if (messageId) {
                deleteMessage(messageId);
            }
        }
    });
}

// Global functions
function mentionUser(username) {
    const chatInput = document.getElementById('chatInput');
    const currentValue = chatInput.value;
    const needsSpace = currentValue.length > 0 && !currentValue.endsWith(' ');
    chatInput.value += (needsSpace ? ' ' : '') + '@' + username + ' ';
    chatInput.focus();
}

function insertEmoji(emoji) {
    const chatInput = document.getElementById('chatInput');
    const cursorPos = chatInput.selectionStart;
    const textBefore = chatInput.value.substring(0, cursorPos);
    const textAfter = chatInput.value.substring(cursorPos);
    
    chatInput.value = textBefore + emoji + ' ' + textAfter;
    chatInput.selectionStart = chatInput.selectionEnd = cursorPos + emoji.length + 1;
    chatInput.focus();
    
    // Trigger input event to update character count
    chatInput.dispatchEvent(new Event('input'));
}

function deleteMessage(messageId) {
    if (!confirm('Delete this message?')) return;
    
    fetch('/chat.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            ajax: '1',
            action: 'delete_message',
            message_id: messageId,
            csrf_token: window.BroChatData.csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
            if (messageElement) {
                messageElement.style.opacity = '0.5';
                messageElement.querySelector('.message-content').innerHTML = '<em>Message deleted</em>';
                const actions = messageElement.querySelector('.message-actions');
                if (actions) actions.remove();
            }
        } else {
            alert('Failed to delete message: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error deleting message:', error);
        alert('Failed to delete message');
    });
}

// Keyboard shortcuts
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Alt + C to focus chat input
        if (e.altKey && e.key === 'c') {
            e.preventDefault();
            document.getElementById('chatInput').focus();
        }
        
        // Escape to clear input
        if (e.key === 'Escape') {
            const chatInput = document.getElementById('chatInput');
            chatInput.value = '';
            chatInput.style.height = 'auto';
        }
    });
}

// Auto-hide flash messages
function autoHideFlashMessages() {
    setTimeout(() => {
        const flashMessages = document.querySelectorAll('.flash-message');
        flashMessages.forEach(msg => {
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 500);
        });
    }, 5000);
}

// Handle page visibility for presence updates
function handlePageVisibility() {
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Page is hidden, mark as away after 30 seconds
            setTimeout(() => {
                if (document.hidden) {
                    fetch('/api/mark-away.php', {method: 'POST'});
                }
            }, 30000);
        } else {
            // Page is visible, mark as online
            fetch('/api/mark-online.php', {method: 'POST'});
        }
    });
    
    // Handle page unload
    window.addEventListener('beforeunload', function() {
        // Mark as offline when leaving
        navigator.sendBeacon('/api/mark-offline.php');
    });
}
