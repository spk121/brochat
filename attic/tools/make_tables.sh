#!/bin/sh

# Set up error logging
if [ -z "$HBB_LOG_DIR" ]; then
    LOG_FILE="mysql_script_log.txt"
else
    LOG_FILE="$HBB_LOG_DIR/mysql_script_log.txt"
fi
touch "$LOG_FILE"  # Ensure the log file exists

# Function to log errors
log_error() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - ERROR: $1" >> "$LOG_FILE"
    echo "ERROR: $1"
}

# Check if all necessary environment variables are set
if [ -z "$MYSQL_HOST" ]; then
    log_error "MYSQL_HOST environment variable is not set"
    exit 1
fi

if [ -z "$MYSQL_USER" ]; then
    log_error "MYSQL_USER environment variable is not set"
    exit 1
fi

if [ -z "$MYSQL_PASSWORD" ]; then
    log_error "MYSQL_PASSWORD environment variable is not set"
    exit 1
fi

if [ -z "$MYSQL_DATABASE" ]; then
    log_error "MYSQL_DATABASE environment variable is not set"
    exit 1
fi

if [ -z "$HBB_SQL_DIR" ]; then
    log_error "HBB_SQL_DIR environment variable is not set"
    exit 1
fi

# Check if commands.sql exists in the specified directory
if [ ! -f "$SQL_ROOT_DIR/commands.sql" ]; then
    log_error "commands.sql does not exist in $SQL_ROOT_DIR"
    exit 1
fi

# Execute SQL commands from file using environment variables for connection details
if ! cat "$HBB_SQL_DIR/commands.sql" | mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"; then
    log_error "Failed to execute SQL commands from $SQL_ROOT_DIR/commands.sql"
    exit 1
fi

# Log successful execution
echo "$(date '+%Y-%m-%d %H:%M:%S') - INFO: SQL commands executed successfully" >> "$LOG_FILE"
