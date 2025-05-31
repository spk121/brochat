CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('ADMIN', 'USER', 'GUEST'))
);

CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address TEXT NOT NULL,
    attempt_time INTEGER NOT NULL,
    attempt_count INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE user_login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    attempt_time INTEGER NOT NULL,
    attempt_count INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE banned_ips (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address TEXT NOT NULL UNIQUE,
    ban_start INTEGER NOT NULL,
    ban_duration INTEGER NOT NULL
);

CREATE TABLE invitation_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE CHECK (code REGEXP '^[a-z]{3}[0-9]{3}$'),
    expiration_date INTEGER NOT NULL,
    usage_count INTEGER NOT NULL DEFAULT 0,
    max_uses INTEGER NOT NULL DEFAULT 5
);

CREATE TABLE logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_type TEXT NOT NULL,
    username TEXT,
    ip_address TEXT NOT NULL,
    timestamp INTEGER NOT NULL,
    details TEXT
);

CREATE TABLE notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    author_id INTEGER NOT NULL REFERENCES users(id),
    timestamp INTEGER NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('draft', 'visible', 'hidden')),
    filename TEXT NOT NULL UNIQUE
);

CREATE TABLE pics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    author_id INTEGER NOT NULL REFERENCES users(id),
    timestamp INTEGER NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('draft', 'visible', 'hidden')),
    caption_filename TEXT NOT NULL UNIQUE,
    pic1_filename TEXT NOT NULL UNIQUE,
    pic2_filename TEXT UNIQUE,
    pic3_filename TEXT UNIQUE,
    pic4_filename TEXT UNIQUE
);
