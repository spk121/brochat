#!/bin/bash

# BroChat Uninstall Script
# Target: Ubuntu 24.04 LTS
# Usage: Run from unpacked BroChat directory: sudo ./uninstall.sh
# Or use the system-installed version: sudo brochat-uninstall

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
APP_NAME="brochat"
WEB_ROOT="/var/www/${APP_NAME}"
DB_DIR="/var/lib/${APP_NAME}"
CONFIG_DIR="/etc/${APP_NAME}"
LOG_DIR="/var/log/${APP_NAME}"
NGINX_SITE="/etc/nginx/sites-available/${APP_NAME}"
NGINX_ENABLED="/etc/nginx/sites-enabled/${APP_NAME}"

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

print_status "Starting BroChat uninstallation..."

# Function to prompt for confirmation
confirm_uninstall() {
    echo
    print_warning "This will completely remove BroChat and all its data!"
    print_warning "This includes:"
    print_warning "  - All application files"
    print_warning "  - Database and all chat data"
    print_warning "  - Configuration files"
    print_warning "  - Log files"
    echo
    read -p "Are you sure you want to continue? (yes/no): " -r
    if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        print_status "Uninstallation cancelled."
        exit 0
    fi
}

# Ask for confirmation unless --force flag is used
if [[ "$1" != "--force" ]]; then
    confirm_uninstall
fi

# Stop services
print_status "Stopping services..."

if systemctl is-active --quiet ${APP_NAME}-websocket; then
    print_status "Stopping WebSocket server..."
    systemctl stop ${APP_NAME}-websocket
else
    print_status "WebSocket server is not running"
fi

# Disable services
print_status "Disabling BroChat services..."
systemctl disable ${APP_NAME}-websocket 2>/dev/null || print_status "WebSocket service was not enabled"

# Remove systemd service file
print_status "Removing systemd service file..."
if [[ -f "/etc/systemd/system/${APP_NAME}-websocket.service" ]]; then
    rm -f "/etc/systemd/system/${APP_NAME}-websocket.service"
    systemctl daemon-reload
    print_status "SystemD service file removed"
else
    print_status "SystemD service file not found"
fi

# Remove nginx configuration
print_status "Removing nginx configuration..."
if [[ -f "$NGINX_SITE" ]]; then
    rm -f "$NGINX_SITE"
    print_status "Nginx site configuration removed"
else
    print_status "Nginx site configuration not found"
fi

if [[ -L "$NGINX_ENABLED" ]]; then
    rm -f "$NGINX_ENABLED"
    print_status "Nginx enabled site link removed"
else
    print_status "Nginx enabled site link not found"
fi

# Test nginx configuration and restart
print_status "Restarting nginx..."
if nginx -t 2>/dev/null; then
    systemctl restart nginx
    print_status "Nginx restarted successfully"
else
    print_warning "Nginx configuration test failed, please check manually"
fi

# Remove application directories
print_status "Removing application files and directories..."

if [[ -d "$WEB_ROOT" ]]; then
    rm -rf "$WEB_ROOT"
    print_status "Web root directory removed: $WEB_ROOT"
else
    print_status "Web root directory not found: $WEB_ROOT"
fi

if [[ -d "$DB_DIR" ]]; then
    rm -rf "$DB_DIR"
    print_status "Database directory removed: $DB_DIR"
else
    print_status "Database directory not found: $DB_DIR"
fi

if [[ -d "$CONFIG_DIR" ]]; then
    rm -rf "$CONFIG_DIR"
    print_status "Configuration directory removed: $CONFIG_DIR"
else
    print_status "Configuration directory not found: $CONFIG_DIR"
fi

if [[ -d "$LOG_DIR" ]]; then
    rm -rf "$LOG_DIR"
    print_status "Log directory removed: $LOG_DIR"
else
    print_status "Log directory not found: $LOG_DIR"
fi

# Remove uninstall script
print_status "Removing uninstall script..."
if [[ -f "/usr/local/bin/${APP_NAME}-uninstall" ]]; then
    rm -f "/usr/local/bin/${APP_NAME}-uninstall"
    print_status "Uninstall script removed"
fi

# Check for any remaining files
print_status "Checking for remaining files..."
remaining_files=()

# Check common locations
locations=(
    "/var/www/${APP_NAME}*"
    "/etc/${APP_NAME}*" 
    "/var/lib/${APP_NAME}*"
    "/var/log/${APP_NAME}*"
    "/etc/nginx/sites-*/${APP_NAME}*"
    "/etc/systemd/system/${APP_NAME}*"
)

for location in "${locations[@]}"; do
    if ls $location 2>/dev/null | grep -q .; then
        remaining_files+=("$location")
    fi
done

if [[ ${#remaining_files[@]} -gt 0 ]]; then
    print_warning "Some files may still remain:"
    for file in "${remaining_files[@]}"; do
        print_warning "  $file"
    done
    echo
    read -p "Would you like to remove these files? (yes/no): " -r
    if [[ $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        for file in "${remaining_files[@]}"; do
            rm -rf $file 2>/dev/null || print_warning "Could not remove: $file"
        done
        print_status "Remaining files cleaned up"
    fi
fi

# Final status check
print_status "Performing final cleanup check..."

# Check if services are still running
if systemctl list-units --all | grep -q "${APP_NAME}-websocket"; then
    print_warning "WebSocket service may still be in systemd"
else
    print_status "✓ WebSocket service completely removed"
fi

# Check nginx status
if systemctl is-active --quiet nginx; then
    print_status "✓ Nginx is running"
else
    print_warning "✗ Nginx is not running - you may need to start it manually"
fi

# Summary
print_status ""
print_status "BroChat uninstallation completed successfully!"
print_status ""
print_status "What was removed:"
print_status "  ✓ All application files and directories"
print_status "  ✓ Database and all stored data"
print_status "  ✓ Configuration files"
print_status "  ✓ Log files"
print_status "  ✓ Nginx site configuration"
print_status "  ✓ SystemD service configuration"
print_status ""
print_status "What was NOT removed:"
print_status "  - nginx package and service"
print_status "  - PHP-FPM package and service"
print_status "  - Node.js package"
print_status "  - SQLite3 package"
print_status ""
print_status "If you want to remove these packages completely, run:"
print_status "  sudo apt-get remove --purge nginx php8.3-fpm nodejs sqlite3"
print_status "  sudo apt-get autoremove"

