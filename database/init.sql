-- BroChat Database Schema
-- SQLite initialization script for the punk rock community platform
-- Created for Ubuntu 24.04 LTS deployment

-- Enable foreign key constraints
PRAGMA foreign_keys = ON;

-- =============================================================================
-- USER MANAGEMENT TABLES
-- =============================================================================

-- Core users table
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(50),
    bio TEXT,
    role VARCHAR(20) DEFAULT 'fan' CHECK (role IN ('fan', 'regular', 'roadie', 'dj', 'admin')),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'suspended')),
    failed_login_attempts INTEGER DEFAULT 0,
    last_failed_login DATETIME,
    last_login DATETIME,
    banned_until DATETIME,
    ban_reason TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

-- Create indexes for users table
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);

-- User activity log
CREATE TABLE user_activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    action VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details TEXT, -- JSON data
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_activity_user_id ON user_activity_log(user_id);
CREATE INDEX idx_activity_action ON user_activity_log(action);
CREATE INDEX idx_activity_created ON user_activity_log(created_at);

-- User presence tracking (online/offline status)
CREATE TABLE user_presence (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    session_id VARCHAR(128),
    status VARCHAR(20) DEFAULT 'online' CHECK (status IN ('online', 'away', 'offline')),
    last_seen DATETIME NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_presence_user_id ON user_presence(user_id);
CREATE INDEX idx_presence_session ON user_presence(session_id);
CREATE INDEX idx_presence_last_seen ON user_presence(last_seen);

-- =============================================================================
-- AUTHENTICATION & SECURITY TABLES
-- =============================================================================

-- Remember me tokens
CREATE TABLE remember_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_remember_tokens_token ON remember_tokens(token);
CREATE INDEX idx_remember_tokens_expires ON remember_tokens(expires_at);

-- Password reset tokens
CREATE TABLE password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_password_resets_token ON password_resets(token);
CREATE INDEX idx_password_resets_expires ON password_resets(expires_at);

-- Login attempts tracking
CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(255),
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    success BOOLEAN DEFAULT 0,
    failure_reason VARCHAR(100),
    attempted_at DATETIME NOT NULL
);

CREATE INDEX idx_login_attempts_ip ON login_attempts(ip_address);
CREATE INDEX idx_login_attempts_username ON login_attempts(username);
CREATE INDEX idx_login_attempts_attempted ON login_attempts(attempted_at);

-- Security events log
CREATE TABLE security_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_type VARCHAR(50) NOT NULL,
    severity VARCHAR(20) DEFAULT 'medium' CHECK (severity IN ('low', 'medium', 'high', 'critical')),
    ip_address VARCHAR(45),
    user_id INTEGER,
    user_agent TEXT,
    details TEXT, -- JSON data
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_security_events_type ON security_events(event_type);
CREATE INDEX idx_security_events_severity ON security_events(severity);
CREATE INDEX idx_security_events_created ON security_events(created_at);

-- Blocked IP addresses
CREATE TABLE blocked_ips (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    reason TEXT,
    blocked_by INTEGER,
    expires_at DATETIME,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (blocked_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_blocked_ips_address ON blocked_ips(ip_address);
CREATE INDEX idx_blocked_ips_expires ON blocked_ips(expires_at);

-- Rate limiting attempts
CREATE TABLE rate_limit_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key VARCHAR(255) NOT NULL,
    action VARCHAR(50),
    ip_address VARCHAR(45),
    user_id INTEGER,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_rate_limit_key ON rate_limit_attempts(key);
CREATE INDEX idx_rate_limit_created ON rate_limit_attempts(created_at);

-- =============================================================================
-- BLOG SYSTEM TABLES
-- =============================================================================

-- Blog posts
CREATE TABLE blog_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    content TEXT NOT NULL, -- Max 1K UTF-8 characters
    slug VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_blog_posts_user_id ON blog_posts(user_id);
CREATE INDEX idx_blog_posts_slug ON blog_posts(slug);
CREATE INDEX idx_blog_posts_created ON blog_posts(created_at);

-- Tags for blog posts
CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL
);

CREATE INDEX idx_tags_name ON tags(name);

-- Blog post to tags relationship
CREATE TABLE blog_post_tags (
    post_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);

-- Blog post photos (up to 4 per post)
CREATE TABLE blog_photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    filename VARCHAR(255) NOT NULL,
    preview_filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    file_size INTEGER,
    position INTEGER DEFAULT 0, -- Order of photos (0-3)
    created_at DATETIME NOT NULL,
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
);

CREATE INDEX idx_blog_photos_post_id ON blog_photos(post_id);
CREATE INDEX idx_blog_photos_position ON blog_photos(position);

-- Blog mentions (@username in posts)
CREATE TABLE blog_mentions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    mentioned_user_id INTEGER NOT NULL,
    mentioning_user_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (mentioned_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mentioning_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_blog_mentions_post_id ON blog_mentions(post_id);
CREATE INDEX idx_blog_mentions_mentioned_user ON blog_mentions(mentioned_user_id);

-- =============================================================================
-- CHAT SYSTEM TABLES
-- =============================================================================

-- Chat messages
CREATE TABLE chat_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    message TEXT NOT NULL, -- Original message
    formatted_message TEXT, -- Formatted with colors, emojis, etc.
    message_type VARCHAR(20) DEFAULT 'message' CHECK (message_type IN ('message', 'action', 'system')),
    timestamp DATETIME NOT NULL,
    deleted_at DATETIME, -- Soft delete for moderation
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_chat_messages_user_id ON chat_messages(user_id);
CREATE INDEX idx_chat_messages_timestamp ON chat_messages(timestamp);
CREATE INDEX idx_chat_messages_deleted ON chat_messages(deleted_at);

-- Typing indicators
CREATE TABLE typing_indicators (
    user_id INTEGER NOT NULL,
    context VARCHAR(50) DEFAULT 'chat',
    started_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, context),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_typing_indicators_started ON typing_indicators(started_at);

-- =============================================================================
-- MODERATION SYSTEM TABLES
-- =============================================================================

-- User mutes (temporary chat restrictions)
CREATE TABLE user_mutes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    muted_by INTEGER NOT NULL,
    reason TEXT,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (muted_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_user_mutes_user_id ON user_mutes(user_id);
CREATE INDEX idx_user_mutes_expires ON user_mutes(expires_at);

-- Moderation action log
CREATE TABLE moderation_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    moderator_id INTEGER NOT NULL,
    target_user_id INTEGER NOT NULL,
    action VARCHAR(50) NOT NULL, -- 'mute', 'ban', 'warn', 'promote', etc.
    reason TEXT,
    duration VARCHAR(50), -- Human readable duration
    created_at DATETIME NOT NULL,
    FOREIGN KEY (moderator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_moderation_log_moderator ON moderation_log(moderator_id);
CREATE INDEX idx_moderation_log_target ON moderation_log(target_user_id);
CREATE INDEX idx_moderation_log_action ON moderation_log(action);

-- =============================================================================
-- AUDIO STREAM TABLES
-- =============================================================================

-- Stream listeners tracking
CREATE TABLE stream_listeners (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id VARCHAR(128),
    user_id INTEGER,
    ip_address VARCHAR(45),
    user_agent TEXT,
    connected_at DATETIME NOT NULL,
    last_seen DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_stream_listeners_session ON stream_listeners(session_id);
CREATE INDEX idx_stream_listeners_user_id ON stream_listeners(user_id);
CREATE INDEX idx_stream_listeners_connected ON stream_listeners(connected_at);
CREATE INDEX idx_stream_listeners_last_seen ON stream_listeners(last_seen);

-- =============================================================================
-- COMMUNITY FEATURES TABLES
-- =============================================================================

-- User milestones/achievements
CREATE TABLE user_milestones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    milestone VARCHAR(100) NOT NULL,
    achieved_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_user_milestones_user_id ON user_milestones(user_id);
CREATE INDEX idx_user_milestones_achieved ON user_milestones(achieved_at);

-- =============================================================================
-- ANALYTICS & TRACKING TABLES
-- =============================================================================

-- Page views tracking
CREATE TABLE page_views (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    page VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(128),
    viewed_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_page_views_user_id ON page_views(user_id);
CREATE INDEX idx_page_views_page ON page_views(page);
CREATE INDEX idx_page_views_session ON page_views(session_id);
CREATE INDEX idx_page_views_viewed ON page_views(viewed_at);

-- User actions tracking
CREATE TABLE user_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT, -- JSON data
    session_id VARCHAR(128),
    ip_address VARCHAR(45),
    performed_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_user_actions_user_id ON user_actions(user_id);
CREATE INDEX idx_user_actions_action ON user_actions(action);
CREATE INDEX idx_user_actions_performed ON user_actions(performed_at);

-- =============================================================================
-- SESSION MANAGEMENT (Optional - for database session storage)
-- =============================================================================

-- Sessions table (optional, for database session storage)
CREATE TABLE sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INTEGER,
    data TEXT,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_sessions_expires ON sessions(expires_at);
CREATE INDEX idx_sessions_user_id ON sessions(user_id);

-- =============================================================================
-- INITIAL DATA SETUP
-- =============================================================================

-- Create default admin user (password: 'punk4ever' - should be changed after setup)
INSERT INTO users (
    username, 
    email, 
    password_hash, 
    display_name, 
    role, 
    status, 
    created_at, 
    updated_at
) VALUES (
    'admin',
    'admin@brochat.local',
    '$2y$10$8K1p/a0nFGOIqM4EqE3qV.A7aFqTY1XZA8.x9mC8rX7jL5uQ9pZw2', -- punk4ever
    'BroChat Admin',
    'admin',
    'active',
    datetime('now'),
    datetime('now')
);

-- Create some default tags
INSERT INTO tags (name, created_at) VALUES 
    ('punk', datetime('now')),
    ('hardcore', datetime('now')),
    ('metal', datetime('now')),
    ('indie', datetime('now')),
    ('live', datetime('now')),
    ('review', datetime('now')),
    ('news', datetime('now')),
    ('opinion', datetime('now'));

-- Create a welcome blog post
INSERT INTO blog_posts (user_id, content, slug, created_at, updated_at) VALUES (
    1,
    '# Welcome to BroChat! 🤘

This is the punk rock community platform where music meets attitude. Share your thoughts, post photos, chat with fellow punks, and listen to our continuous stream of raw, unfiltered punk rock.

**What you can do here:**
- Write blog posts with photos (up to 1K characters)
- Chat with the community in real-time
- Listen to our 24/7 punk rock audio stream
- Use @mentions and #hashtags to connect

Remember: Keep it real, keep it punk, and respect the community.

*Fuck the system, but respect each other.*

#welcome #punk #community',
    'welcome-to-brochat',
    datetime('now'),
    datetime('now')
);

-- Link the welcome post to relevant tags
INSERT INTO blog_post_tags (post_id, tag_id) VALUES 
    (1, 1), -- punk
    (1, 7), -- news
    (1, 8); -- opinion

-- =============================================================================
-- DATABASE OPTIMIZATION
-- =============================================================================

-- Analyze tables for query optimization
ANALYZE;

-- Create additional composite indexes for common queries
CREATE INDEX idx_blog_posts_user_created ON blog_posts(user_id, created_at DESC);
CREATE INDEX idx_chat_messages_timestamp_deleted ON chat_messages(timestamp DESC, deleted_at);
CREATE INDEX idx_user_activity_user_created ON user_activity_log(user_id, created_at DESC);
CREATE INDEX idx_stream_listeners_last_seen_session ON stream_listeners(last_seen DESC, session_id);

-- =============================================================================
-- VIEWS FOR COMMON QUERIES
-- =============================================================================

-- Active users view
CREATE VIEW active_users AS
SELECT 
    u.id,
    u.username,
    u.display_name,
    u.role,
    u.last_login,
    COUNT(DISTINCT bp.id) as blog_posts,
    COUNT(DISTINCT cm.id) as chat_messages
FROM users u
LEFT JOIN blog_posts bp ON u.id = bp.user_id
LEFT JOIN chat_messages cm ON u.id = cm.user_id AND cm.deleted_at IS NULL
WHERE u.status = 'active'
GROUP BY u.id, u.username, u.display_name, u.role, u.last_login;

-- Recent activity view
CREATE VIEW recent_activity AS
SELECT 
    'blog_post' as activity_type,
    bp.id as item_id,
    u.username,
    bp.created_at as activity_time,
    SUBSTR(bp.content, 1, 100) || '...' as preview
FROM blog_posts bp
JOIN users u ON bp.user_id = u.id
WHERE bp.created_at > datetime('now', '-7 days')

UNION ALL

SELECT 
    'chat_message' as activity_type,
    cm.id as item_id,
    u.username,
    cm.timestamp as activity_time,
    SUBSTR(cm.message, 1, 100) || '...' as preview
FROM chat_messages cm
JOIN users u ON cm.user_id = u.id
WHERE cm.timestamp > datetime('now', '-1 day')
AND cm.deleted_at IS NULL

ORDER BY activity_time DESC;

-- Stream statistics view
CREATE VIEW stream_stats AS
SELECT 
    DATE(connected_at) as date,
    COUNT(DISTINCT session_id) as unique_listeners,
    COUNT(*) as total_connections,
    AVG(julianday(last_seen) - julianday(connected_at)) * 24 * 60 as avg_listen_minutes
FROM stream_listeners
WHERE connected_at > datetime('now', '-30 days')
GROUP BY DATE(connected_at)
ORDER BY date DESC;

-- =============================================================================
-- TRIGGERS FOR DATA INTEGRITY
-- =============================================================================

-- Update blog_posts.updated_at when content changes
CREATE TRIGGER update_blog_post_timestamp 
AFTER UPDATE OF content ON blog_posts
BEGIN
    UPDATE blog_posts SET updated_at = datetime('now') WHERE id = NEW.id;
END;

-- Update users.updated_at when user data changes
CREATE TRIGGER update_user_timestamp 
AFTER UPDATE ON users
BEGIN
    UPDATE users SET updated_at = datetime('now') WHERE id = NEW.id;
END;

-- Clean up related data when user is deleted
CREATE TRIGGER cleanup_user_data
AFTER DELETE ON users
BEGIN
    -- Clean up orphaned tags if no posts reference them
    DELETE FROM tags WHERE id NOT IN (
        SELECT DISTINCT tag_id FROM blog_post_tags
    );
END;

-- Automatically clean up expired tokens
CREATE TRIGGER cleanup_expired_tokens
AFTER INSERT ON remember_tokens
BEGIN
    DELETE FROM remember_tokens WHERE expires_at < datetime('now');
    DELETE FROM password_resets WHERE expires_at < datetime('now');
END;

-- =============================================================================
-- FINAL SETUP
-- =============================================================================

-- Vacuum database to optimize storage
VACUUM;

-- Update statistics
ANALYZE;

-- Set database version for migration tracking
PRAGMA user_version = 1;
