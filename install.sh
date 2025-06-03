#!/bin/bash

# BroChat Installation Script
# Target: Ubuntu 24.04 LTS
# Usage: Run from unpacked BroChat directory: sudo ./install.sh

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
APP_NAME="brochat"
APP_USER="www-data"
WEB_ROOT="/var/www/${APP_NAME}"
DB_PATH="/var/lib/${APP_NAME}/${APP_NAME}.db"
DB_DIR="/var/lib/${APP_NAME}"
CONFIG_DIR="/etc/${APP_NAME}"
LOG_DIR="/var/log/${APP_NAME}"
NGINX_SITE="/etc/nginx/sites-available/${APP_NAME}"
NGINX_ENABLED="/etc/nginx/sites-enabled/${APP_NAME}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# SSL Configuration - can be overridden by environment variables or config file
SSL_CERT_NAME="${SSL_CERT_NAME:-brochat.crt}"
SSL_KEY_NAME="${SSL_KEY_NAME:-brochat.key}"
SSL_CERT_PATH="/etc/ssl/certs/${SSL_CERT_NAME}"
SSL_KEY_PATH="/etc/ssl/private/${SSL_KEY_NAME}"
SERVER_NAME="${SERVER_NAME:-localhost}"

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   print_error "This script must be run as root (use sudo)"
   exit 1
fi

# Check if required directories exist
if [[ ! -d "$SCRIPT_DIR/public" || ! -d "$SCRIPT_DIR/private" || ! -d "$SCRIPT_DIR/sql" || ! -d "$SCRIPT_DIR/config" ]]; then
    print_error "Required directories not found. Expected: public, private, sql, config"
    print_error "Make sure you're running this script from the unpacked BroChat directory"
    exit 1
fi

# Load configuration from config file if it exists
if [[ -f "$SCRIPT_DIR/config/ssl.conf" ]]; then
    print_status "Loading SSL configuration from config/ssl.conf..."
    source "$SCRIPT_DIR/config/ssl.conf"
    SSL_CERT_PATH="/etc/ssl/certs/${SSL_CERT_NAME}"
    SSL_KEY_PATH="/etc/ssl/private/${SSL_KEY_NAME}"
fi

print_status "Starting BroChat installation from: $SCRIPT_DIR"
print_status "SSL Certificate: $SSL_CERT_PATH"
print_status "SSL Key: $SSL_KEY_PATH"
print_status "Server Name: $SERVER_NAME"

# Update package list
print_status "Updating package list..."
apt-get update

# Install required packages
print_status "Installing required packages..."
apt-get install -y nginx php8.3-fpm php8.3-sqlite3 php8.3-json php8.3-mbstring \
    sqlite3 nodejs npm curl

# Create application directories
print_status "Creating application directories..."
mkdir -p "$WEB_ROOT"
mkdir -p "$DB_DIR"
mkdir -p "$CONFIG_DIR"
mkdir -p "$LOG_DIR"

# Copy public files to web root
print_status "Copying public files..."
cp -r "$SCRIPT_DIR/public"/* "$WEB_ROOT/"

# Copy private files to web root (outside public access)
print_status "Copying private files..."
mkdir -p "$WEB_ROOT/private"
cp -r "$SCRIPT_DIR/private"/* "$WEB_ROOT/private/"

# Copy configuration files
print_status "Copying configuration files..."
cp -r "$SCRIPT_DIR/config"/* "$CONFIG_DIR/"

# Set proper permissions
print_status "Setting file permissions..."
chown -R $APP_USER:$APP_USER "$WEB_ROOT"
chown -R $APP_USER:$APP_USER "$DB_DIR"
chown -R $APP_USER:$APP_USER "$CONFIG_DIR"
chown -R $APP_USER:$APP_USER "$LOG_DIR"

# Create bootstrap.php configuration file
print_status "Creating bootstrap.php configuration file..."
cat > "$WEB_ROOT/bootstrap.php" << 'EOF'
<?php
/**
 * BroChat Bootstrap Configuration
 * 
 * This file defines application paths and loads core components.
 * Include this file at the top of every public PHP file:
 * require_once __DIR__ . '/bootstrap.php';
 */

// Prevent multiple inclusions
if (!defined('BROCHAT_LOADED')) {
    define('BROCHAT_LOADED', true);
    
    // Application version and info
    define('BROCHAT_VERSION', '1.0.0');
    define('BROCHAT_NAME', 'BroChat');
    
    // Directory constants
    define('BROCHAT_ROOT', __DIR__);
    define('BROCHAT_PRIVATE', BROCHAT_ROOT . '/private');
    define('BROCHAT_CONFIG', '/etc/brochat');
    define('BROCHAT_DB', '/var/lib/brochat/brochat.db');
    define('BROCHAT_LOGS', '/var/log/brochat');
    
    // URL constants (auto-detect protocol and host)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BROCHAT_BASE_URL', $protocol . '://' . $host);
    define('BROCHAT_WS_URL', (($protocol === 'https') ? 'wss' : 'ws') . '://' . $host . '/ws');
    
    // Environment detection
    define('BROCHAT_ENV', getenv('BROCHAT_ENV') ?: 'production');
    define('BROCHAT_DEBUG', BROCHAT_ENV === 'development');
    
    // Error reporting based on environment
    if (BROCHAT_DEBUG) {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ini_set('log_errors', 1);
    } else {
        error_reporting(E_ERROR | E_WARNING | E_PARSE);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
    }
    
    // Set error log location
    ini_set('error_log', BROCHAT_LOGS . '/php_errors.log');
    
    // Set timezone (can be overridden in config)
    date_default_timezone_set('UTC');
    
    // Load configuration file if it exists
    $config_file = BROCHAT_CONFIG . '/app.conf';
    if (file_exists($config_file)) {
        $config = parse_ini_file($config_file, true);
        if ($config !== false) {
            // Apply configuration settings
            if (isset($config['app']['timezone'])) {
                date_default_timezone_set($config['app']['timezone']);
            }
            if (isset($config['app']['debug'])) {
                define('BROCHAT_CONFIG_DEBUG', (bool)$config['app']['debug']);
            }
        }
    }
    
    // Database connection helper function
    function brochat_get_db() {
        static $pdo = null;
        if ($pdo === null) {
            try {
                $pdo = new PDO('sqlite:' . BROCHAT_DB);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                if (BROCHAT_DEBUG) {
                    die('Database connection failed: ' . $e->getMessage());
                } else {
                    die('Database connection failed. Please check the logs.');
                }
            }
        }
        return $pdo;
    }
    
    // Auto-load core files if they exist
    $core_files = [
        'functions.php',
        'auth.php',
        'security.php',
        'utils.php'
    ];
    
    foreach ($core_files as $file) {
        $file_path = BROCHAT_PRIVATE . '/' . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
    
    // Load core directory files if they exist
    $core_dir = BROCHAT_PRIVATE . '/core';
    if (is_dir($core_dir)) {
        $core_files_in_dir = [
            'functions.php',
            'database.php',
            'auth.php',
            'session.php',
            'security.php'
        ];
        
        foreach ($core_files_in_dir as $file) {
            $file_path = $core_dir . '/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    // Session configuration
    if (!session_id()) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', $protocol === 'https' ? 1 : 0);
        ini_set('session.use_strict_mode', 1);
        session_start();
    }
}
?>
EOF

# Set proper file permissions
find "$WEB_ROOT" -type f -exec chmod 644 {} \;
find "$WEB_ROOT" -type d -exec chmod 755 {} \;

# Create SQLite database
print_status "Creating SQLite database..."
if [[ -f "$SCRIPT_DIR/sql/init.sql" ]]; then
    sqlite3 "$DB_PATH" < "$SCRIPT_DIR/sql/init.sql"
    chown $APP_USER:$APP_USER "$DB_PATH"
    chmod 644 "$DB_PATH"
else
    print_error "init.sql file not found in sql directory"
    exit 1
fi

# Check for SSL certificates
print_status "Checking SSL certificates..."
ssl_warning=false

if [[ ! -f "$SSL_CERT_PATH" ]]; then
    print_warning "SSL certificate not found: $SSL_CERT_PATH"
    ssl_warning=true
fi

if [[ ! -f "$SSL_KEY_PATH" ]]; then
    print_warning "SSL private key not found: $SSL_KEY_PATH"
    ssl_warning=true
fi

if [[ "$ssl_warning" == true ]]; then
    print_warning "SSL certificates not found. You will need to:"
    print_warning "1. Obtain SSL certificates for your domain"
    print_warning "2. Place the certificate at: $SSL_CERT_PATH"
    print_warning "3. Place the private key at: $SSL_KEY_PATH"
    print_warning "4. Restart nginx: sudo systemctl restart nginx"
    print_warning ""
    print_warning "For testing, you can create self-signed certificates:"
    print_warning "sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \\"
    print_warning "  -keyout $SSL_KEY_PATH \\"
    print_warning "  -out $SSL_CERT_PATH \\"
    print_warning "  -subj \"/C=US/ST=State/L=City/O=Organization/CN=$SERVER_NAME\""
    echo
    read -p "Continue with installation? (yes/no): " -r
    if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        print_status "Installation cancelled. Please set up SSL certificates first."
        exit 0
    fi
fi

# Create nginx configuration
print_status "Creating nginx configuration..."
cat > "$NGINX_SITE" << EOF
# HTTP server - redirect to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name ${SERVER_NAME};
    
    # Redirect all HTTP requests to HTTPS
    return 301 https://\$server_name\$request_uri;
}

# HTTPS server
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ${SERVER_NAME};
    
    # SSL Configuration
    ssl_certificate ${SSL_CERT_PATH};
    ssl_certificate_key ${SSL_KEY_PATH};
    
    # Modern SSL configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:50m;
    ssl_stapling on;
    ssl_stapling_verify on;
    
    # Security headers
    add_header Strict-Transport-Security "max-age=63072000" always;
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
    
    root /var/www/${APP_NAME};
    index index.php index.html index.htm;
    
    # Logging
    access_log /var/log/${APP_NAME}/access.log;
    error_log /var/log/${APP_NAME}/error.log;
    
    # Main location block
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    # PHP-FPM configuration
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param HTTPS on;
        include fastcgi_params;
    }
    
    # Deny access to private directory
    location /private {
        deny all;
        return 404;
    }
    
    # Deny access to configuration files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # Deny access to database files
    location ~ \.db\$ {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # WebSocket proxy for Node.js (secure)
    location /ws {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header X-Forwarded-Ssl on;
        proxy_redirect off;
    }
    
    # Static file handling with compression
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)\$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        gzip_static on;
    }
}
EOF

# Enable the site
print_status "Enabling nginx site..."
ln -sf "$NGINX_SITE" "$NGINX_ENABLED"

# Test nginx configuration
print_status "Testing nginx configuration..."
nginx -t

# Remove default nginx site if it exists
if [[ -L "/etc/nginx/sites-enabled/default" ]]; then
    print_status "Removing default nginx site..."
    rm -f /etc/nginx/sites-enabled/default
fi

# Create systemd service for Node.js WebSocket server
print_status "Creating systemd service for WebSocket server..."
cat > "/etc/systemd/system/${APP_NAME}-websocket.service" << EOF
[Unit]
Description=BroChat WebSocket Server
After=network.target

[Service]
Type=simple
User=$APP_USER
Group=$APP_USER
WorkingDirectory=$WEB_ROOT
ExecStart=/usr/bin/node websocket-server.js
Restart=always
RestartSec=10
StandardOutput=syslog
StandardError=syslog
SyslogIdentifier=${APP_NAME}-websocket
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
EOF

# Install Node.js dependencies if package.json exists
if [[ -f "$SCRIPT_DIR/package.json" ]]; then
    print_status "Installing Node.js dependencies..."
    cp "$SCRIPT_DIR/package.json" "$WEB_ROOT/"
    if [[ -f "$SCRIPT_DIR/package-lock.json" ]]; then
        cp "$SCRIPT_DIR/package-lock.json" "$WEB_ROOT/"
    fi
    cd "$WEB_ROOT"
    sudo -u $APP_USER npm install --production
fi

# Copy WebSocket server if it exists, otherwise create a basic one
if [[ -f "$SCRIPT_DIR/websocket-server.js" ]]; then
    print_status "Copying WebSocket server..."
    cp "$SCRIPT_DIR/websocket-server.js" "$WEB_ROOT/"
    chown $APP_USER:$APP_USER "$WEB_ROOT/websocket-server.js"
else
    print_status "Creating basic WebSocket server..."
    cat > "$WEB_ROOT/websocket-server.js" << 'EOF'
const WebSocket = require('ws');
const http = require('http');

const server = http.createServer();
const wss = new WebSocket.Server({ server });

wss.on('connection', function connection(ws) {
    console.log('New WebSocket connection established');
    
    ws.on('message', function incoming(data) {
        console.log('Received:', data.toString());
        
        // Broadcast to all connected clients
        wss.clients.forEach(function each(client) {
            if (client !== ws && client.readyState === WebSocket.OPEN) {
                client.send(data);
            }
        });
    });
    
    ws.on('close', function close() {
        console.log('WebSocket connection closed');
    });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, '127.0.0.1', function() {
    console.log(`WebSocket server listening on port ${PORT}`);
});
EOF
    chown $APP_USER:$APP_USER "$WEB_ROOT/websocket-server.js"
fi

# Install ws package if not in package.json
if [[ ! -f "$WEB_ROOT/package.json" ]]; then
    print_status "Installing WebSocket package..."
    cd "$WEB_ROOT"
    sudo -u $APP_USER npm init -y
    sudo -u $APP_USER npm install ws
fi

# Reload systemd and start services
print_status "Starting services..."
systemctl daemon-reload
systemctl enable php8.3-fpm
systemctl enable nginx
systemctl enable ${APP_NAME}-websocket

systemctl start php8.3-fpm
systemctl restart nginx
systemctl start ${APP_NAME}-websocket

# Clean up - no temporary files to clean since we're working from source directory
print_status "Installation files processed from source directory"

# Copy install script for reference
print_status "Copying scripts for future reference..."
cp "$SCRIPT_DIR/install.sh" "$CONFIG_DIR/" 2>/dev/null || true
cp "$SCRIPT_DIR/uninstall.sh" "$CONFIG_DIR/" 2>/dev/null || true

# Create SSL configuration file template
print_status "Creating SSL configuration template..."
cat > "$CONFIG_DIR/ssl.conf.example" << EOF
# SSL Configuration for BroChat
# Copy this file to ssl.conf and modify as needed
# 
# SSL_CERT_NAME="your-domain.crt"
# SSL_KEY_NAME="your-domain.key"
# SERVER_NAME="your-domain.com"

SSL_CERT_NAME="${SSL_CERT_NAME}"
SSL_KEY_NAME="${SSL_KEY_NAME}"
SERVER_NAME="${SERVER_NAME}"
EOF

# Create application configuration file template
print_status "Creating application configuration template..."
cat > "$CONFIG_DIR/app.conf.example" << 'EOF'
# BroChat Application Configuration
# Copy this file to app.conf and modify as needed

[app]
# Application environment: development, staging, production
environment = production

# Enable debug mode (shows detailed errors)
debug = false

# Default timezone
timezone = UTC

# Maximum file upload size (in bytes)
max_upload_size = 10485760

[database]
# Database path (automatically set by installer)
# path = /var/lib/brochat/brochat.db

[websocket]
# WebSocket server port (must match websocket-server.js)
port = 3000

# WebSocket server host
host = 127.0.0.1

[security]
# Session lifetime in seconds (default: 24 hours)
session_lifetime = 86400

# Password minimum length
password_min_length = 8

# Enable CSRF protection
csrf_protection = true

[logging]
# Log level: debug, info, warning, error
log_level = warning

# Maximum log file size in MB
max_log_size = 10
EOF

# Create uninstall script
print_status "Creating uninstall script..."
cat > "/usr/local/bin/${APP_NAME}-uninstall" << 'EOF'
#!/bin/bash

# BroChat Uninstall Script
# Usage: sudo brochat-uninstall

set -e

APP_NAME="brochat"
WEB_ROOT="/var/www/${APP_NAME}"
DB_DIR="/var/lib/${APP_NAME}"
CONFIG_DIR="/etc/${APP_NAME}"
LOG_DIR="/var/log/${APP_NAME}"
NGINX_SITE="/etc/nginx/sites-available/${APP_NAME}"
NGINX_ENABLED="/etc/nginx/sites-enabled/${APP_NAME}"

echo "Uninstalling BroChat..."

# Stop and disable services
systemctl stop ${APP_NAME}-websocket 2>/dev/null || true
systemctl disable ${APP_NAME}-websocket 2>/dev/null || true
systemctl stop nginx 2>/dev/null || true

# Remove systemd service
rm -f "/etc/systemd/system/${APP_NAME}-websocket.service"
systemctl daemon-reload

# Remove nginx configuration
rm -f "$NGINX_SITE"
rm -f "$NGINX_ENABLED"

# Remove application directories
rm -rf "$WEB_ROOT"
rm -rf "$DB_DIR"
rm -rf "$CONFIG_DIR"
rm -rf "$LOG_DIR"

# Restart nginx
systemctl start nginx 2>/dev/null || true

echo "BroChat has been uninstalled successfully"
echo "Note: nginx, PHP-FPM, and Node.js packages were not removed"
EOF

chmod +x "/usr/local/bin/${APP_NAME}-uninstall"

# Final status check
print_status "Performing final status check..."

# Check if services are running
if systemctl is-active --quiet nginx; then
    print_status "✓ Nginx is running"
else
    print_warning "✗ Nginx is not running"
fi

if systemctl is-active --quiet php8.3-fpm; then
    print_status "✓ PHP-FPM is running"
else
    print_warning "✗ PHP-FPM is not running"
fi

if systemctl is-active --quiet ${APP_NAME}-websocket; then
    print_status "✓ WebSocket server is running"
else
    print_warning "✗ WebSocket server is not running"
fi

# Check if database exists
if [[ -f "$DB_PATH" ]]; then
    print_status "✓ Database created successfully"
else
    print_warning "✗ Database was not created"
fi

print_status "BroChat installation completed successfully!"
print_status ""
if [[ "$ssl_warning" == true ]]; then
    print_warning "⚠️  SSL certificates not found - HTTPS will not work until certificates are installed"
    print_status "Application will be available at: http://$SERVER_NAME (redirects to HTTPS)"
    print_status "After installing SSL certificates, it will be available at: https://$SERVER_NAME"
else
    print_status "✓ SSL certificates found"
    print_status "Application is available at: https://$SERVER_NAME"
    print_status "HTTP requests will automatically redirect to HTTPS"
fi
print_status "Database location: $DB_PATH"
print_status "Configuration directory: $CONFIG_DIR"
print_status "Log directory: $LOG_DIR"
print_status "SSL configuration template: $CONFIG_DIR/ssl.conf.example"
print_status "App configuration template: $CONFIG_DIR/app.conf.example"
print_status ""
print_status "✓ Bootstrap configuration created at: $WEB_ROOT/bootstrap.php"
print_status ""
print_status "To use in your PHP files, add this at the top:"
print_status "  <?php require_once __DIR__ . '/bootstrap.php'; ?>"
print_status ""
print_status "Available constants after including bootstrap.php:"
print_status "  BROCHAT_ROOT, BROCHAT_PRIVATE, BROCHAT_CONFIG"
print_status "  BROCHAT_DB, BROCHAT_LOGS, BROCHAT_BASE_URL, BROCHAT_WS_URL"
print_status ""
print_status "To customize application settings:"
print_status "  1. Copy $CONFIG_DIR/app.conf.example to $CONFIG_DIR/app.conf"
print_status "  2. Edit the settings as needed"
print_status ""
print_status "To customize SSL settings:"
print_status "  1. Copy $CONFIG_DIR/ssl.conf.example to $CONFIG_DIR/ssl.conf"
print_status "  2. Edit the SSL certificate names and server name"
print_status "  3. Reinstall or manually update nginx configuration"
print_status ""
print_status "To uninstall, run: sudo ${APP_NAME}-uninstall"
print_status ""
print_status "Service management commands:"
print_status "  sudo systemctl start/stop/restart ${APP_NAME}-websocket"
print_status "  sudo systemctl start/stop/restart nginx"
print_status "  sudo systemctl start/stop/restart php8.3-fpm"
