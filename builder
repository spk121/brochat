#!/usr/bin/env php
<?php

// Script version and metadata
const VERSION = '0.1';
const PROGRAM_NAME = 'builder';

// Default configuration
$defaults = [
    'srcdir' => '.',
    'builddir' => 'build',
    'quiet' => false,
];

function parse_arguments() {
    global $argv, $defaults;

    $short_opts = '';
    $long_opts = ['build', 'install', 'uninstall', 'clean', 'clean-config', 'help', 'version', 'wtf', 'fml', 'srcdir:', 'builddir:', 'quiet'];
    $options = getopt($short_opts, $long_opts);

    // Extract all long options from $argv
    $provided_long_opts = [];
    foreach ($argv as $arg) {
        if (preg_match('/^--([a-zA-Z0-9-]+)(?:=.*)?$/', $arg, $matches)) {
            $provided_long_opts[] = $matches[1];
        }
    }

    // Check for unrecognized long options
    foreach ($provided_long_opts as $opt) {
        $valid_opts = array_map(function ($long_opt) {
            return rtrim($long_opt, ':');
        }, $long_opts);
        if (!in_array($opt, $valid_opts)) {
            log_message("Error: Unrecognized option '--$opt'", true);
            exit(1);
        }
    }

    // Handle --wtf
    if (isset($options['wtf'])) {
        echo "¯\\_(ツ)_/¯\n";
        exit(0);
    }

    // Handle --fml
    if (isset($options['fml'])) {
        echo "🔥 This is fine 🔥\n";
        exit(0);
    }

    // Handle --version
    if (isset($options['version'])) {
        echo PROGRAM_NAME . " " . VERSION . "\n";
        exit(0);
    }

    // Handle --help
    if (isset($options['help'])) {
        $help_text = <<<EOT
Usage: builder [options]
This script builds and manages the Bro Chat project.

It supports five actions: build, clean, clean-config, install, and uninstall.

The 'build' action is either development or production, depending on the
configuration in the config.php file. It initializes an empty SQLite database with the
default schema, creates configuration files for the LSNP stack (Linux,
SQLite, Nginx, PHP), and copies the PHP source files to the build directory.

The 'clean' action removes build artifacts in the build directory, except
for the config.php configuration file.

The 'clean-config' action removes the build artifacts and the config.php file.

The 'install' action copies the built files to the configured webroot
directory, copies the configuration files to the Nginx configuration,
and creates a soft link in the Nginx sites-enabled directory.
If the database file already exists, it will error out.

The 'uninstall' action removes the installed files from the webroot,
Nginx configuration, and Nginx sites-enabled soft link, but it will not
remove the database or other stateful directories.

The builder script presumes that a build/config/config.php file
exists, which contains the configuration for the project. You can
create the file by running the 'configure' script in the project root.
The 'clean' action will not remove the config.php file.
Use the 'clean-config' action to execute a clean that also removes the config.php file.

Options:
  --build                build the project (e.g., initialize database, copy files)
  --install              install the project to the configured prefix
  --uninstall            remove installed files
  --clean                remove build artifacts
  --clean-config         remove build artifacts and the config.php file
  --srcdir=DIR           top directory of unpacked source files [{$defaults['srcdir']}]
  --builddir=DIR         build directory for output files [{$defaults['builddir']}]
  --quiet                suppress non-error output
  --version              output version information and exit
  --help                 display this help and exit
  --wtf                  output shrug emoji and exit
  --fml                  output flame emojis with 'This is fine' and exit
EOT;
        echo $help_text;
        exit(0);
    }

    // Set srcdir, builddir, and quiet
    $defaults['srcdir'] = $options['srcdir'] ?? $defaults['srcdir'];
    $defaults['builddir'] = $options['builddir'] ?? $defaults['builddir'];
    $defaults['quiet'] = isset($options['quiet']);

    return $options;
}

function log_message(string $message, bool $is_error = false): void {
    global $defaults;
    if (!$is_error && $defaults['quiet']) {
        return;
    }
    $stream = $is_error ? STDERR : STDOUT;
    fwrite($stream, $message . "\n");
}

function ensure_writable_directory($path) {
    if (file_exists($path)) {
        if (!is_dir($path)) {
            log_message("Error: '{$path}' exists but is not a directory.", true);
            return false;
        }
        if (is_readable($path) && is_writable($path)) {
            return true;
        } else {
            log_message("Error: '{$path}' exists but lacks read/write permissions.", true);
            return false;
        }
    }
    if (!mkdir($path, 0755, true)) {
        log_message("Error: Failed to create directory '{$path}'.", true);
        return false;
    }
    if (!chmod($path, 0755)) {
        log_message("Error: Failed to set permissions for '{$path}'.", true);
        return false;
    }
    return true;
}

function validate_directories() {
    global $defaults;

    // Normalize paths to absolute
    $defaults['srcdir'] = realpath($defaults['srcdir']) ?: $defaults['srcdir'];
    $defaults['builddir'] = realpath($defaults['builddir']) ?: $defaults['builddir'];

    if (!is_dir($defaults['srcdir'])) {
        log_message("Error: Source directory '{$defaults['srcdir']}' does not exist.", true);
        exit(1);
    }
    if (!is_readable($defaults['srcdir'])) {
        log_message("Error: Source directory '{$defaults['srcdir']}' is not readable.", true);
        exit(1);
    }
    if ($defaults['srcdir'] === $defaults['builddir']) {
        log_message("Error: Source directory and build directory cannot be the same.", true);
        exit(1);
    }
}

// What to do when copying files that already exist in the destination directory.
enum CopyOptions {
    case OVERWRITE; // Overwrite existing files
    case SKIP;      // Skip copying if file exists
    case RENAME;    // Rename file if it exists (e.g., append a number)
}

// $extensions is an array of file extensions to copy, without the leading dot,
// e.g., ['php', 'sql'].
function copy_files_by_extension(string $from_dir, string $to_dir, array $extensions, CopyOptions $copy_options): bool {
    // Normalize directory paths (remove trailing slashes)
    $from_dir = rtrim($from_dir, '/');
    $to_dir = rtrim($to_dir, '/');

    if (!is_dir($from_dir)) {
        log_message("Error: Source directory '$from_dir' does not exist.", true);
        return false;
    }
    if (!is_dir($to_dir) && !mkdir($to_dir, 0755, true)) {
        log_message("Error: Could not create destination directory '$to_dir'.", true);
        return false;
    }

    // Build glob pattern for extensions (e.g., *.php,*.txt)
    $patterns = array_map(fn($ext) => "$from_dir/*.$ext", $extensions);
    $files = [];
    // Collect all matching files
    foreach ($patterns as $pattern) {
        $files = array_merge($files, glob($pattern) ?: []);
    }

    // Copy each file to the destination
    foreach ($files as $file) {
        if (!is_readable($file)) {
            log_message("Error: Source file '$file' is not readable.", true);
            return false;
        }
        $filename = basename($file);
        $to_path = "$to_dir/$filename";

        if (file_exists($to_path)) {
            switch ($copy_options) {
                case CopyOptions::SKIP:
                    continue 2; // Skip to next file
                case CopyOptions::RENAME:
                    $base = pathinfo($filename, PATHINFO_FILENAME);
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    $counter = 1;
                    do {
                        $new_filename = "$base-$counter.$ext";
                        $to_path = "$to_dir/$new_filename";
                        $counter++;
                    } while (file_exists($to_path));
                    break;
                case CopyOptions::OVERWRITE:
                    // Proceed with copy (no action needed)
                    break;
            }
        }

        if (!copy($file, $to_path)) {
            log_message("Error: Failed to copy '$file' to '$to_path'.", true);
            return false;
        }
    }
    return true;
}

// $extensions is an array of file extensions to match, without the leading dot,
// e.g., ['php', 'sql'].
function remove_files_by_extension(string $from_dir, string $to_dir, array $extensions, bool $remove_empty_dir = false): bool {
    // Normalize directory paths (remove trailing slashes)
    $from_dir = rtrim($from_dir, '/');
    $to_dir = rtrim($to_dir, '/');

    if (!is_dir($from_dir)) {
        log_message("Error: Source directory '$from_dir' does not exist.", true);
        return false;
    }
    if (!is_dir($to_dir)) {
        log_message("Error: Destination directory '$to_dir' does not exist.", true);
        return false;
    }

    // Build glob pattern for extensions (e.g., *.php,*.txt)
    $patterns = array_map(fn($ext) => "$from_dir/*.$ext", $extensions);
    $files = [];
    foreach ($patterns as $pattern) {
        $files = array_merge($files, glob($pattern) ?: []);
    }

    $files_removed = false;
    foreach ($files as $file) {
        $filename = basename($file);
        $to_path = "$to_dir/$filename";
        if (file_exists($to_path)) {
            if (!unlink($to_path)) {
                log_message("Error: Failed to remove '$to_path'.", true);
                return false;
            }
            $files_removed = true;
        }
    }

    // Optionally remove destination directory if empty
    if ($remove_empty_dir && $files_removed) {
        $dir_iterator = new DirectoryIterator($to_dir);
        $is_empty = true;
        foreach ($dir_iterator as $item) {
            if ($item->isDot()) {
                continue;
            }
            $is_empty = false;
            break;
        }
        if ($is_empty && !rmdir($to_dir)) {
            log_message("Error: Failed to remove empty directory '$to_dir'.", true);
            return false;
        }
    }
    return true;
}

/**
 * Initializes an SQLite database using PDO from an SQL file.
 *
 * @param string $db_path Path to the SQLite database file
 * @param string $init_sql Path to the SQL initialization file
 * @return bool True on success, false on failure
 */
function initialize_sqlite_db(string $db_path, string $init_sql): bool {
    try {
        if (!file_exists($init_sql)) {
            log_message("Error: SQL file '$init_sql' does not exist.", true);
            return false;
        }
        // Read SQL file contents
        $sql = file_get_contents($init_sql);
        if ($sql === false) {
            log_message("Error: Failed to read SQL file '$init_sql'.", true);
            return false;
        }
        // Connect to SQLite database (creates file if it doesn't exist)
        $pdo = new PDO("sqlite:$db_path");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Split SQL into individual statements (handling semicolons)
        $statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => !empty($s));
        // Execute each SQL statement
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        return true;
    } catch (PDOException $e) {
        log_message("Error: Failed to initialize database '$db_path': " . $e->getMessage(), true);
        return false;
    } catch (Exception $e) {
        log_message("Error: " . $e->getMessage(), true);
        return false;
    }
}

function load_config() {
    global $defaults;

    $config_file = rtrim($defaults['builddir'], '/') . '/config/config.php';
    if (!file_exists($config_file)) {
        log_message("Error: $config_file not found. Run './configure' first.", true);
        exit(1);
    }
    require_once $config_file;
}

function validate_config(): void {
    $required = [
        'NGINX_WEBROOT', 'PRIVATE_DIR', 'DB_DIR', 'LOG_DIR', 'NOTES_DIR',
        'SNAPS_DIR', 'SNAPS_PREVIEWS_DIR', 'CONFIG_DIR', 'DB_FILE', 'MODE',
        'NGINX_SITES_AVAILABLE_DIR', 'NGINX_SITES_ENABLED_DIR', 'NGINX_CONF_FILE',
        'DOMAIN', 'PHP_FPM_SOCK', 'PRODUCTION_HTTP_PORT', 'DEVEL_HTTP_PORT',
        'DEVEL_HTTPS_ENABLED', 'DEVEL_HTTPS_PORT', 'PRODUCTION_HTTPS_PORT',
        'SSL_CERT_FILE_PRODUCTION', 'SSL_KEY_FILE_PRODUCTION', 'SSL_CERT_FILE_DEVEL', 'SSL_KEY_FILE_DEVEL'
    ];
    foreach ($required as $const) {
        if (!defined($const)) {
            log_message("Error: $const not defined in config.php", true);
            exit(1);
        }
    }
}

function run_command($command) {
    exec($command, $output, $return_var);
    if ($return_var !== 0) {
        log_message("Error: Command failed: $command", true);
        exit(1);
    }
    return $output;
}

function build($src_dir, $build_dir) {
    global $defaults;

    log_message("Building project...");
    if (!ensure_writable_directory($build_dir)) {
        exit(1);
    }

    // Initialize BroChat database from init.sql 
    $db_path = $build_dir . '/db/' . basename($DB_FILE);
    $init_sql = $src_dir . '/private/init.sql';
    if (!file_exists($init_sql)) {
        log_message("Error: SQL script $init_sql not found.", true);
        exit(1);
    }
    log_message("Initializing BroChat database...");
    $db_dir = dirname($db_path);
    if (!ensure_writable_directory($db_dir)) {
        log_message("Error: Cannot create database directory $db_dir", true);
        exit(1);
    }
    initialize_sqlite_db($db_path, $init_sql) or log_message("Error: Failed to initialize database.", true) && exit(1);
    log_message("Database creation complete!");

    // Copy directories
    copy_files_by_extension($src_dir . '/private', $build_dir . '/private', ['php', 'sql'], CopyOptions::OVERWRITE);
    copy_files_by_extension($src_dir . '/public', $build_dir . '/public', ['php', 'css', 'js', 'html', 'png', 'jpg', 'ico', 'jpeg', 'gif'], CopyOptions::OVERWRITE);
    copy_files_by_extension($src_dir . '/config', $build_dir . '/config', ['php'], CopyOptions::OVERWRITE);
    log_message("Build complete.");
}

function clean($src_dir, $build_dir) {
    log_message("Cleaning build artifacts...");
    remove_files_by_extension($src_dir . '/private', $build_dir . '/private', ['php', 'sql'], true);
    remove_files_by_extension($src_dir . '/public', $build_dir . '/public', ['php', 'css', 'js', 'html', 'png', 'jpg', 'ico', 'jpeg', 'gif'], true);
    remove_files_by_extension($src_dir . '/config', $build_dir . '/config', ['php'], true);
    // Remove the database file if it exists.
    $db_path = '$build_dir/db/' . basename($DB_FILE);
    if (file_exists($db_path)) {
        unlink($db_path);
    }
    // Remove the database directory if it is empty.
    $db_dir = dirname($db_path);
    if (is_dir($db_dir) && count(scandir($db_dir)) === 2) { // Only '.' and '..'
        rmdir($db_dir);
    }
    // Remove the build directory if it is empty.
    if (is_dir($build_dir) && count(scandir($build_dir)) === 2) { // Only '.' and '..'
        rmdir($build_dir);
    }
    log_message("Clean complete.");
}

function install($build_dir) {
    if (file_exists(DB_FILE)) {
        log_message("Error: Database '" . DB_FILE . "' already exists. Remove it or use --uninstall first.", true);
        exit(1);
    }

    log_message("Installing to " . NGINX_WEBROOT . "...");
    if (!ensure_writable_directory(NGINX_WEBROOT)) {
        log_message("Error: Cannot create " . NGINX_WEBROOT, true);
        exit(1);
    }
    copy_files_by_extension($build_dir . '/public', NGINX_WEBROOT, ['php', 'css', 'js', 'html', 'png', 'jpg', 'ico', 'jpeg', 'gif'], CopyOptions::OVERWRITE);
    
    if (!ensure_writable_directory(PRIVATE_DIR)) {
        log_message("Error: Cannot create " . PRIVATE_DIR, true);
        exit(1);
    }
    copy_files_by_extension($build_dir . '/private', PRIVATE_DIR, ['php'], CopyOptions::OVERWRITE);
    
    if (!ensure_writable_directory(DB_DIR)) {
        log_message("Error: Cannot create " . DB_DIR, true);
        exit(1);
    }
    copy_files_by_extension($build_dir . '/db', DB_DIR, ['db'], CopyOptions::OVERWRITE);
    
    foreach ([LOG_DIR, NOTES_DIR, SNAPS_DIR, SNAPS_PREVIEWS_DIR, CONFIG_DIR] as $dir) {
        if (!ensure_writable_directory($dir)) {
            log_message("Error: Cannot create $dir", true);
            exit(1);
        }
    }
    copy_files_by_extension($build_dir . '/config', CONFIG_DIR, ['php'], CopyOptions::OVERWRITE);

    // Generate Nginx configuration
    $nginx_conf = NGINX_CONF_FILE;
    $nginx_link = NGINX_SITES_ENABLED_DIR . '/brochat.conf';
    $listen = (MODE === 'DEVELOPMENT') ? DEVEL_HTTP_PORT : PRODUCTION_HTTPS_PORT;
    $ssl = (MODE === 'PRODUCTION' || (MODE === 'DEVELOPMENT' && DEVEL_HTTPS_ENABLED)) ? 
        "ssl on;\n        ssl_certificate " . (MODE === 'PRODUCTION' ? SSL_CERT_FILE_PRODUCTION : SSL_CERT_FILE_DEVEL) . ";\n        ssl_certificate_key " . (MODE === 'PRODUCTION' ? SSL_KEY_FILE_PRODUCTION : SSL_KEY_FILE_DEVEL) . ";" : "";
    $nginx_content = <<<EOT
server {
    listen $listen;
    server_name {DOMAIN};
    root {NGINX_WEBROOT};
    index index.php;
    $ssl
    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:{PHP_FPM_SOCK};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
}
EOT;
    if (file_exists($nginx_conf)) {
        log_message("Error: Nginx configuration '$nginx_conf' already exists.", true);
        exit(1);
    }
    if (!file_put_contents($nginx_conf, $nginx_content)) {
        log_message("Error: Failed to write $nginx_conf", true);
        exit(1);
    }
    if (file_exists($nginx_link)) {
        log_message("Error: Nginx soft link '$nginx_link' already exists.", true);
        exit(1);
    }
    if (!symlink($nginx_conf, $nginx_link)) {
        log_message("Error: Failed to create soft link '$nginx_link'", true);
        exit(1);
    }
    log_message("Installation complete.");
}

function uninstall($build_dir) {
    log_message("Uninstalling...");
    remove_files_by_extension($build_dir . '/public', NGINX_WEBROOT, ['php', 'css', 'js', 'html', 'png', 'jpg', 'ico', 'jpeg', 'gif'], true);
    remove_files_by_extension($build_dir . '/private', PRIVATE_DIR, ['php'], true);
    remove_files_by_extension($build_dir . '/config', CONFIG_DIR, ['php'], true);

    // Remove Nginx configuration and soft link
    $nginx_conf = NGINX_CONF_FILE;
    $nginx_link = NGINX_SITES_ENABLED_DIR . '/brochat.conf';
    if (file_exists($nginx_link)) {
        unlink($nginx_link) or log_message("Warning: Cannot remove $nginx_link", true);
    }
    if (file_exists($nginx_conf)) {
        unlink($nginx_conf) or log_message("Warning: Cannot remove $nginx_conf", true);
    }

    log_message("Uninstallation complete.");
}

function main() {
    $options = parse_arguments();
    validate_directories();
    load_config();
    validate_config();

    $actions = ['build', 'install', 'uninstall', 'clean', 'clean-config'];
    $action_count = count(array_intersect(array_keys($options), $actions));
    if ($action_count === 0) {
        log_message("Error: No action specified. Use --build, --install, --uninstall, --clean, or --clean-config.", true);
        exit(1);
    } elseif ($action_count > 1) {
        log_message("Error: Only one action can be specified.", true);
        exit(1);
    }

    $src_dir = rtrim($defaults['srcdir'], '/');
    $build_dir = rtrim($defaults['builddir'], '/');

    if (isset($options['build'])) {
        build($src_dir, $build_dir);
    } elseif (isset($options['clean'])) {
        clean($src_dir, $build_dir);
    } elseif (isset($options['clean-config'])) {
        clean($src_dir, $build_dir);
        $config_file = $build_dir . '/config/config.php';
        log_message("Removing $config_file");
        if (file_exists($config_file)) {
            unlink($config_file) or log_message("Warning: Cannot remove $config_file", true);
        }
    } elseif (isset($options['install'])) {
        install($build_dir);
    } elseif (isset($options['uninstall'])) {
        uninstall($build_dir);
    }
}

main();
?>
