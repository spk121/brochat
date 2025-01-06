-- The table of users.  There is probably only ever going
-- to be one users. ME!

CREATE TABLE Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role INT DEFAULT 0, -- in case I want to have admins vs users
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    profile_picture VARCHAR(255),  -- Path to profile picture
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- No one can register without a registration key.
-- Easier than doing a CAPCHA

CREATE TABLE Registration_Keys (
    key_id INT AUTO_INCREMENT PRIMARY KEY,
    key VARCHAR(6) UNIQUE NOT NULL,
    used BOOLEAN DEFAULT 0,
    expiration_date DATETIME NOT NULL
);

-- Covers all 3 types of posts
-- micro: body only
-- photo: image_path, optional body
-- blog: title, body.  Images are in html.

CREATE TABLE Posts (
    post_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT DEFAULT 0, -- maybe I'll add categories some day
    visibility INT DEFAULT 0, -- in case I want drafts or hiding posts
    title VARCHAR(255),
    body TEXT,
    image_path VARCHAR(255),  -- Path to the image file
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    views INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Guestbook comments

CREATE TABLE Chats (
    chat_id INT AUTO_INCREMENT PRIMARY KEY,
    user_name TINYTEXT,
    visibility INT DEFAULT 0,
    chat_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
);


-- In case I want to have users and comments some day

CREATE TABLE Comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES Posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- In case I want an engagement system.
-- Since almost all visitor will not have an account
-- and will be the 'anonymous coward' user, we'll allow
-- a user_id to make multiple engagements per post
CREATE TABLE Likes (
    like_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    mood_id INT NOT NULL,   -- the type of like: like, dislike
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES Posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

