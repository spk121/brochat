#!/usr/bin/env php
<?php

// Script version and metadata
const VERSION = '0.1';
const PROGRAM_NAME = 'builder';
const VERBOSITY_QUIET = 0;
const VERBOSITY_NORMAL = 1;
const VERBOSITY_VERBOSE = 2;

// Default configuration
$defaults = [
    'srcdir' => '.',
    'builddir' => 'build',
    'verbosity' => VERBOSITY_NORMAL,
];

function log_to_file($message) {
    $log_file = 'build.log';
    if (!is_writable(dirname($log_file)) || (file_exists($log_file) && !is_writable($log_file))) {
        log_message("Warning: Cannot write to $log_file.", VERBOSITY_NORMAL, true);
        return;
    }
    file_put_contents($log_file, date('Y-m-d H:i:s') . ": $message\n", FILE_APPEND);
}

function log_message(string $message, int $level = VERBOSITY_NORMAL, bool $to_stderr = false): void {
    global $defaults;
    if ($defaults['verbosity'] >= $level) {
        $stream = $to_stderr ? STDERR : STDOUT;
        fwrite($stream, $message . "\n");
    }
    log_to_file($message);
}

function parse_arguments() {
    global $argv, $defaults;

    $short_opts = '';
    $long_opts = ['build', 'install', 'uninstall', 'clean', 'clean-config', 'help', 'version', 'wtf', 'fml', 'srcdir:', 'builddir:', 'verbose', 'quiet'];
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
            log_message("Error: Unrecognized option '--$opt'", VERBOSITY_QUIET, true);
            exit(1);
        }
    }

    if (isset($options['verbose']) && isset($options['quiet'])) {
        log_message("Error: Cannot use both --verbose and --quiet options.", VERBOSITY_QUIET, true);
        exit(1);
    } elseif (isset($options['verbose'])) {
        $defaults['verbosity'] = VERBOSITY_VERBOSE;
    } elseif (isset($options['quiet'])) {
        $defaults['verbosity'] = VERBOSITY_QUIET;
    }

    if (isset($options['wtf'])) {
        log_message("¯\\_(ツ)_/¯", VERBOSITY_QUIET);
        exit(0);
    }

    if (isset($options['fml'])) {
        log_message("🔥 This is fine 🔥", VERBOSITY_QUIET);
        exit(0);
    }

    if (isset($options['version'])) {
        log_message(PROGRAM_NAME . " " . VERSION, VERBOSITY_QUIET);
        exit(0);
    }

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
  --verbose              increase output verbosity
  --quiet                suppress non-error output
  --version              output version information and exit
  --help                 display this help and exit
  --wtf                  output shrug emoji and exit
  --fml                  output flame emojis with 'This is fine' and exit
EOT;
        log_message($help_text, VERBOSITY_QUIET);
        exit(0);
    }

    $defaults['srcdir'] = $options['srcdir'] ?? $defaults['srcdir'];
    $defaults['builddir'] = $options['builddir'] ?? $defaults['builddir'];

    return $options;
}

function ensure_writable_directory($path) {
    log_message("Ensuring directory '$path' is writable...", VERBOSITY_VERBOSE);
    if (file_exists($path)) {
        if (!is_dir($path)) {
            log_message("Error: '$path' exists but is not a directory.", VERBOSITY_QUIET, true);
            return false;
        }
        if (is_readable($path) && is_writable($path)) {
            return true;
        } else {
            log_message("Error: '$path' exists but lacks read/write permissions.", VERBOSITY_QUIET, true);
            return false;
        }
    }
    if (!mkdir($path, 0755, true)) {
        log_message("Error: Failed to create directory '$path'.", VERBOSITY_QUIET, true);
        return false;
    }
    if (!chmod($path, 0755)) {
        log_message("Error: Failed to set permissions for '$path'.", VERBOSITY_QUIET, true);
        return false;
    }
    return true;
}

function validate_directories() {
    global $defaults;

    log_message("Validating directories...", VERBOSITY_VERBOSE);
    $defaults['srcdir'] = realpath($defaults['srcdir']) ?: $defaults['srcdir'];
    $defaults['builddir'] = realpath($defaults['builddir']) ?: $defaults['builddir'];

    if (!is_dir($defaults['srcdir'])) {
        log_message("Error: Source directory '{$defaults['srcdir']}' does not exist.", VERBOSITY_QUIET, true);
        exit(1);
    }
    if (!is_readable($defaults['srcdir'])) {
        log_message("Error: Source directory '{$defaults['srcdir']}' is not readable.", VERBOSITY_QUIET, true);
        exit(1);
    }
    if ($defaults['srcdir'] === $defaults['builddir']) {
        log_message("Error: Source directory and build directory cannot be the same.", VERBOSITY_QUIET, true);
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

    log_message("Copying files from '$from_dir' to '$to_dir' (extensions: " . implode(', ', $extensions) . ")", VERBOSITY_VERBOSE);
    if (!is_dir($from_dir)) {
        log_message("Source directory '$from_dir' does not exist.", VERBOSITY_QUIET);
        return false;
    }
    if (!is_dir($to_dir) && !mkdir($to_dir, 0755, true)) {
        log_message("Could not create destination directory '$to_dir'.", VERBOSITY_QUIET);
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
            log_message("Error: Source file '$file' is not readable.", VERBOSITY_QUIET, true);
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

        if (copy($file, $to_path)) {
            log_message("Copied '$file' to '$to_path'", VERBOSITY_VERBOSE);
        } else {
            log_message("Failed to copy '$file' to '$to_path'.", VERBOSITY_QUIET, true);
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

    log_message("Removing files from '$to_dir' matching '$from_dir' (extensions: " . implode(', ', $extensions) . ")", VERBOSITY_NORMAL);
    if (!is_dir($from_dir)) {
        log_message("Source directory '$from_dir' does not exist.", VERBOSITY_NORMAL);
        return false;
    }
    if (!is_dir($to_dir)) {
        log_message("Destination directory '$to_dir' does not exist.", VERBOSITY_NORMAL);
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
            if (unlink($to_path)) {
                log_message("Removed file: '$to_path'", VERBOSITY_VERBOSE);
                $files_removed = true;
            } else {
                log_message("Error: Failed to remove '$to_path'.", VERBOSITY_QUIET, true);
            }
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
        if ($is_empty) {
            if (rmdir($to_dir)) {
                log_message("Removed empty directory: '$to_dir'", VERBOSITY_VERBOSE);
            } else {
                log_message("Error: Failed to remove empty directory '$to_dir'.", VERBOSITY_QUIET, true);
            }
        }
    }
    return true;
}

function set_directory_permissions($dir_path, $user, $group, $dir_perms, $file_perms) {
    // Validate input
    if (!is_dir($dir_path)) {
        log_message("Error: '$dir_path' is not a valid directory", VERBOSITY_QUIET, true);
        return false;
    }

    // Convert permissions to octal if provided as string
    $dir_perms = is_string($dir_perms) ? octdec($dir_perms) : $dir_perms;
    $file_perms = is_string($file_perms) ? octdec($file_perms) : $file_perms;

    try {
        // Set directory ownership
        if (!chown($dir_path, $user)) {
            log_message("Error: Failed to set user '$user' for directory '$dir_path'", VERBOSITY_QUIET, true);
            return false;
        }
        if (!chgrp($dir_path, $group)) {
            log_message("Error: Failed to set group '$group' for directory '$dir_path'", VERBOSITY_QUIET, true);
            return false;
        }
        if (!chmod($dir_path, $dir_perms)) {
            log_message("Error: Failed to set permissions for directory '$dir_path'", VERBOSITY_QUIET, true);
            return false;
        }

        // Create iterator for recursive processing
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        // Process all files and directories
        $retval = true;
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            
            // Set ownership
            if (!chown($path, $user)) {
                log_message("Error: Failed to set user '$user' for '$path'", VERBOSITY_QUIET, true);
                $retval = false;
            }
            if (!chgrp($path, $group)) {
                log_message("Error: Failed to set group '$group' for '$path'", VERBOSITY_QUIET, true);
                $retval = false;
            }

            // Set permissions based on whether it's a directory or file
            $perms = $item->isDir() ? $dir_perms : $file_perms;
            if (!chmod($path, $perms)) {
                log_message("Error: Failed to set permissions for '$path'", VERBOSITY_QUIET, true);
                $retval = false;
            }
        }

        if (!$retval) {
            log_message("Error: Some files or directories could not be processed.", VERBOSITY_QUIET, true);
            return false;
        }
        log_message("Successfully set permissions and ownership for '$dir_path' and its contents", VERBOSITY_VERBOSE);
        return true;
    } catch (Exception $e) {
        log_message("Error for '$path'" . $e->getMessage(), VERBOSITY_QUIET, true);
        return false;
    }
    // Should never reach here.
    return true;
}

function initialize_sqlite_db(string $db_path, string $init_sql): bool {
    log_message("Initializing SQLite database at '$db_path' from '$init_sql'...", VERBOSITY_VERBOSE);
    try {
        if (!file_exists($init_sql)) {
            log_message("Error: SQL file '$init_sql' does not exist.", VERBOSITY_QUIET, true);
            return false;
        }
        // Read SQL file contents
        $sql = file_get_contents($init_sql);
        if ($sql === false) {
            log_message("Error: Failed to read SQL file '$init_sql'.", VERBOSITY_QUIET, true);
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
        log_message("Error: Failed to initialize database '$db_path': " . $e->getMessage(), VERBOSITY_QUIET, true);
        return false;
    } catch (Exception $e) {
        log_message("Error: " . $e->getMessage(), VERBOSITY_QUIET, true);
        return false;
    }
}

function load_config() {
    global $defaults;

    $config_file = rtrim($defaults['builddir'], '/') . '/config/config.php';
    if (!file_exists($config_file)) {
        log_message("Error: $config_file not found. Run './configure' first.", VERBOSITY_QUIET, true);
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
        'SSL_CERT_FILE_PRODUCTION', 'SSL_KEY_FILE_PRODUCTION', 'SSL_CERT_FILE_DEVEL', 'SSL_KEY_FILE_DEVEL',
        'WEBSOCKET_HOST', 'WEBSOCKET_PORT'
    ];
    foreach ($required as $const) {
        if (!defined($const)) {
            log_message("Error: $const not defined in config.php", VERBOSITY_QUIET, true);
            exit(1);
        }
    }
    if (!file_exists(PHP_FPM_SOCK)) {
        log_message("Error: PHP_FPM_SOCK '" . PHP_FPM_SOCK . "' does not exist.", VERBOSITY_QUIET, true);
        exit(1);
    }
}

function build($src_dir, $build_dir) {
    global $defaults;

    log_message("Building project...");
    if (!ensure_writable_directory($build_dir)) {
        exit(1);
    }

    // Initialize BroChat database from init.sql 
    $db_path = $build_dir . '/db/' . basename(DB_FILE);
    $init_sql = $src_dir . '/private/init.sql';
    if (!file_exists($init_sql)) {
        log_message("Error: SQL script $init_sql not found.", VERBOSITY_QUIET, true);
        exit(1);
    }
    log_message("Initializing BroChat database...");
    $db_dir = dirname($db_path);
    if (!ensure_writable_directory($db_dir)) {
        log_message("Error: Directory $db_dir failure", VERBOSITY_QUIET, true);
        exit(1);
    }
    if (file_exists($db_path)) {
        log_message("Warning: Database file '$db_path' already exists. It will be overwritten.", VERBOSITY_NORMAL);
        unlink($db_path) or log_message("Warning: Cannot remove existing database file '$db_path'", VERBOSITY_NORMAL);
    }
    if (!initialize_sqlite_db($db_path, $init_sql)) {
        log_message("Error: Failed to initialize database.", VERBOSITY_QUIET, true);
        exit(1);
    }
    log_message("Database creation complete!");

    copy_files_by_extension($src_dir . '/public', $build_dir . '/public', ['php', 'css', 'js', 'html', 'png', 'jpg', 'ico', 'jpeg', 'gif'], CopyOptions::OVERWRITE);
    // Create a bootstrap file that holds the main paths for the installed application.
    log_message("Creating build-directory bootstrap file in " . $build_dir . '/public', VERBOSITY_VERBOSE);
    $bootstrap_file = $build_dir . '/public/bootstrap.php';
    $bootstrap_public_dir = $build_dir . '/public';
    $bootstrap_private_dir = $build_dir . '/private';
    $bootstrap_config_dir = $build_dir . '/config';
    $bootstrap_content = <<<EOT
<?php
// Bootstrap file contains the build directory paths.
// This file is just used to test the build using the
// command-line version of PHP.
if (!defined('BOOTSTRAP')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=CP437');
    echo "Direct access not allowed.\n";
    exit(2);
}
define('ABS_PUBLIC_DIR', '$bootstrap_public_dir');
define('ABS_PRIVATE_DIR', '$bootstrap_private_dir');
define('ABS_CONFIG_DIR', '$bootstrap_config_dir');
define('ABS_MAGIC_UUID', '53f9e544-3f34-11f0-9397-00155d8eea3b');
?>
EOT;
    file_put_contents($bootstrap_file, $bootstrap_content);
    copy_files_by_extension($src_dir . '/private', $build_dir . '/private', ['php', 'sql'], CopyOptions::OVERWRITE);
    copy_files_by_extension($src_dir . '/config', $build_dir . '/config', ['php'], CopyOptions::OVERWRITE);
    log_message("Build complete.");
}

function clean($src_dir, $build_dir) {
    log_message("Cleaning build artifacts...");
    remove_files_by_extension($src_dir . '/public', $build_dir . '/public', ['php', 'css', 'js', 'html', 'png', 'jpg', 'ico', 'jpeg', 'gif'], true);
    remove_files_by_extension($src_dir . '/private', $build_dir . '/private', ['php', 'sql'], true);
    remove_files_by_extension($src_dir . '/config', $build_dir . '/config', ['php'], true);
    $bootstrap_file = $build_dir . '/public/bootstrap.php';
    if (file_exists($bootstrap_file)) {
        if (unlink($bootstrap_file)) {
            log_message("Removed build-directory bootstrap file: $bootstrap_file", VERBOSITY_VERBOSE);
        } else {
            log_message("Warning: Cannot remove " . bootstrap_file, VERBOSITY_NORMAL, true);
        }
    }
    $db_path = '$build_dir/db/' . basename(DB_FILE);
    if (file_exists($db_path)) {
        unlink($db_path) or log_message("Warning: Cannot remove " . DB_FILE, VERBOSITY_NORMAL, true);
    }
    // Remove the database directory if it is empty.
    $db_dir = dirname($db_path);
    if (is_dir($db_dir) && count(scandir($db_dir)) === 2) { // Only '.' and '..'
        rmdir($db_dir);
    }
    // Remove the build directory if it is empty.
    if (is_dir($build_dir) && count(scandir($build_dir)) === 2) {
        rmdir($build_dir);
    }
    log_message("Clean complete.");
}

function nginx_http_to_https_upgrade_block($http_port, $https_port, $domain) {
    $https_port_string = $https_port !== 443 ? ':' . $https_port : '';
    // Note that we're mixing PHP variables a nginx configuration file
    // variables in the heredoc string below.
    return <<<EOT
# Redirect HTTP to HTTPS
server {
    listen $http_port;
    listen [::]:$http_port;
    server_name $domain www.$domain;
    return 301 https://\$server_name$https_port_string\$request_uri;
}
EOT;
}

function install($build_dir) {
    if (file_exists(DB_FILE)) {
        log_message("Error: Database '" . DB_FILE . "' already exists. Remove it or use --uninstall first.", VERBOSITY_QUIET, true);
        exit(1);
    }

    log_message("Using " . PUBLIC_DIR . " as the public webroot directory, instead of " . NGINX_WEBROOT, VERBOSITY_VERBOSE);
    if (!ensure_writable_directory(PUBLIC_DIR)) {
        log_message("Error: Directory failure for " . PUBLIC_DIR, VERBOSITY_QUIET, true);
        exit(1);
    }
    copy_files_by_extension($build_dir . '/public', PUBLIC_DIR, ['php', 'css', 'js', 'html', 'png', 'jpg', 'ico', 'jpeg', 'gif'], CopyOptions::OVERWRITE);
    // Create a bootstrap file that holds the main paths for the installed application.
    log_message("Creating bootstrap file in " . PUBLIC_DIR, VERBOSITY_VERBOSE);
    $bootstrap_file = PUBLIC_DIR . '/bootstrap.php';
    $bootstrap_public_dir = PUBLIC_DIR;
    $bootstrap_private_dir = PRIVATE_DIR;
    $bootstrap_config_dir = CONFIG_DIR;
    $bootstrap_content = <<<EOT
<?php
// Bootstrap file contains the installation paths and UUID for the application.
// Each PHP script should
// - define BOOTSTRAP before including this file
// - include this file to access the path constants
// After that the application can include php scripts
//   via `require_once PRIVATE_DIR . '/some_script.php'`
//   or `require_once CONFIG_DIR . '/config.php'`
if (!defined('BOOTSTRAP')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=CP437');
    echo "Direct access not allowed.\n";
    exit(2);
}
define('ABS_PUBLIC_DIR', '$bootstrap_public_dir');
define('ABS_PRIVATE_DIR', '$bootstrap_private_dir');
define('ABS_CONFIG_DIR', '$bootstrap_config_dir');
define('ABS_MAGIC_UUID', 'c90ef91c-3f22-11f0-b5e4-00155d8eea3b');
?>
EOT;
    file_put_contents($bootstrap_file, $bootstrap_content);
    log_message("Created bootstrap file at $bootstrap_file", VERBOSITY_VERBOSE);
    set_directory_permissions(PUBLIC_DIR, NGINX_USER, NGINX_GROUP, '0755', '0644');

    if (!ensure_writable_directory(PRIVATE_DIR)) {
        log_message("Error: Directory failure for " . PRIVATE_DIR, VERBOSITY_QUIET, true);
        exit(1);
    }
    copy_files_by_extension($build_dir . '/private', PRIVATE_DIR, ['php'], CopyOptions::OVERWRITE);
    set_directory_permissions(PRIVATE_DIR, PHP_FPM_USER, PHP_FPM_GROUP, '0755', '0644');
    
    if (!ensure_writable_directory(DB_DIR)) {
        log_message("Error: Directory failure for " . DB_DIR, VERBOSITY_QUIET, true);
        exit(1);
    }
    copy_files_by_extension($build_dir . '/db', DB_DIR, ['db'], CopyOptions::OVERWRITE);
    // For production, these permission should probably be more restrictive: 0700 and 0600.
    set_directory_permissions(DB_DIR, PHP_FPM_USER, PHP_FPM_GROUP, '0755', '0644');
    
    foreach ([LOG_DIR, NOTES_DIR, SNAPS_DIR, SNAPS_PREVIEWS_DIR, CONFIG_DIR] as $dir) {
        if (!ensure_writable_directory($dir)) {
            log_message("Error: Directory failure for $dir", VERBOSITY_QUIET, true);
            exit(1);
        }
        // FIXME: Check these permissions. Can they be more restrictive?
        set_directory_permissions($dir, PHP_FPM_USER, PHP_FPM_GROUP, '0775', '0664');
    }
    copy_files_by_extension($build_dir . '/config', CONFIG_DIR, ['php'], CopyOptions::OVERWRITE);

    // Generate Nginx configuration
    /*
server {
    listen 8080;
	listen [::]:8080;    
    server_name localhost;

    # Root directory for the project
    root /var/www/brochat/public;
    index index.php index.html;

    # Disable caching for development
    add_header Cache-Control "no-store, no-cache, must-revalidate, max-age=0";
    add_header Pragma "no-cache";

    # Debugging Headers
    add_header X-Debug "Development Mode";

    # Basic security headers (still useful in development)
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

	location / {
		# First attempt to serve request as file, then
		# as directory, then fall back to displaying a 404.
		try_files $uri $uri/ =404;
	}

    ##
    # Pass PHP scripts to FastCGI server
	#
    location ~ ^/public/.*\.php$ {
		include snippets/fastcgi-php.conf; 

		# With php-fpm:
        # N.B. This Unix socket location has to match the
        # one given in the PHP FPM configuration file. On
        # Ubuntu, search for it at 
        # /etc/php/8.3/fpm/pool.d/www.conf
		fastcgi_pass unix:/run/php/php8.3-fpm.sock;

        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Allow browsing private files for debugging (DO NOT use in production)
    location /private/ {
        root /var/www/brochat/private;
        autoindex on;
    }

    # Static files (CSS, JS, images)
    location /assets/ {
        root /var/www/brochat/assets;
        autoindex on;
    }

    # WebSocket proxy for local development
    location /ws/ {
        proxy_pass http://localhost:11080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
    }

    # Logging
    access_log /var/log/brochat/access.log;
    error_log /var/log/brochat/error.log;    
}

    */
    $nginx_conf = NGINX_CONF_FILE;
    $nginx_link = NGINX_SITES_ENABLED_DIR . '/brochat.conf';

    $config_text = '';
    if (MODE === 'PRODUCTION') {
        $config_text .= nginx_http_to_https_upgrade_block(PRODUCTION_HTTP_PORT, PRODUCTION_HTTPS_PORT, DOMAIN);
        $main_port = PRODUCTION_HTTPS_PORT;
        $server_name = DOMAIN;
    } elseif (MODE === 'DEVELOPMENT' && DEVEL_HTTPS_ENABLED) {
        $config_text .= nginx_http_to_https_upgrade_block(DEVEL_HTTP_PORT, DEVEL_HTTPS_PORT, 'localhost');
        $main_port = DEVEL_HTTPS_PORT;
        $server_name = 'localhost';
    } else {
        // No upgrade block for development without HTTPS.
        $main_port = DEVEL_HTTP_PORT;
        $server_name = 'localhost';
    }
    $config_body = '';
    $config_body .= <<<EOT

    listen $main_port;
    listen [::]:$main_port;
    server_name $server_name;
EOT;

    ////////////////////
    // SSL configuration and security headers
    if (MODE === 'PRODUCTION') {
        $cert = SSL_CERT_FILE_PRODUCTION;
        $key = SSL_KEY_FILE_PRODUCTION;

        $config_body .= <<<EOT
    # SSL configuration for production
    ssl_certificate $cert;
    ssl_certificate_key $key;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self'; connect-src 'self' ws://your-websocket-server.com;" always;

EOT;
    } elseif (MODE === 'DEVELOPMENT' && DEVEL_HTTPS_ENABLED) {
        $cert = SSL_CERT_FILE_DEVEL;
        $key = SSL_KEY_FILE_DEVEL;

        $config_body .= <<<EOT

    # SSL configuration for development
    ssl_certificate $cert;
    ssl_certificate_key $key;

    # Basic security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

EOT;
    } else {
        $config_body .= <<<EOT

    # Basic security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

EOT;
    }

    ////////////////////
    // Root Directory and Index Files
    {
        $public_dir = PUBLIC_DIR;
        $config_body .= <<<EOT

    # Root directory for the project
    root $public_dir;
    index index.php index.html;

EOT;
    }

    ////////////////////
    // Caching and debugging headers
    if (MODE === 'DEVELOPMENT') {
        $config_body .= <<<EOT

    # Disable caching for development
    add_header Cache-Control "no-store, no-cache, must-revalidate, max-age=0";
    add_header Pragma "no-cache";

    # Debugging Headers
    add_header X-Debug "Development Mode";

EOT;
    }

    /////////////////////
    // PHP Configuration
    {
        $sock = PHP_FPM_SOCK;
        $config_body .= <<<EOT

    # Deny direct access to bootstrap.php
    # Does a case-sensitive match for the exact file name.
    location = /bootstrap.php {
        deny all;
        return 403; # Forbidden
    }
        
    # Pass PHP scripts to FastCGI server
    # Does a case-sensitive match for files ending with .php.
    location ~ \\.php$ {
        include snippets/fastcgi-php.conf;
        include fastcgi_params;
        fastcgi_pass unix:{$sock};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }

    # Deny direct access to private and config directories
    # Does a case-sensitive match for URLs that begin with /private/ or /config/.
    # FIXME: this is probably not needed, as the PHP scripts are
    # not in the webroot.
    # location ~ ^/(private|config)/ {
    #     deny all;
    #     return 403;
    # }

    location / {
        # First attempt to serve request as file, then
        # as directory, then fall back to displaying a 404.
        try_files \$uri \$uri/ =404;
    }


EOT;
    }

    /////////////////////
    // Websocket Proxy and Logging
    {
        $host = WEBSOCKET_HOST;
        $port = WEBSOCKET_PORT;
        $logdir = LOG_DIR;
        $config_body .= <<<EOT

    # WebSocket is always local to the server.
    location /ws/ {
        proxy_pass http://{$host}:{$port};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "Upgrade";
    }

    # Logging
    access_log {$logdir}/access.log;
    error_log {$logdir}/error.log;

EOT;
    }

    $config_text .= <<<EOT

server {
{$config_body}
}

EOT;

    if (file_exists($nginx_conf)) {
        log_message("Error: Nginx configuration '$nginx_conf' already exists.", VERBOSITY_QUIET, true);
        exit(1);
    }
    if (file_put_contents($nginx_conf, $config_text)) {
        log_message("Nginx configuration written to $nginx_conf", VERBOSITY_NORMAL);
    } else {
        log_message("Failed to write $nginx_conf", VERBOSITY_QUIET, true);
        exit(1);
    }
    if (file_exists($nginx_link)) {
        log_message("Nginx soft link '$nginx_link' already exists.", VERBOSITY_QUIET, true);
        exit(1);
    }
    if (symlink($nginx_conf, $nginx_link)) {
        log_message("Created soft link '$nginx_link' to '$nginx_conf'", VERBOSITY_NORMAL);
    } else {
        log_message("Failed to create soft link '$nginx_link'", VERBOSITY_QUIET, true);
        exit(1);
    }
    log_message("Installation complete.");
}

function uninstall($build_dir) {
    log_message("Uninstalling...");
    remove_files_by_extension($build_dir . '/public', PUBLIC_DIR, ['php', 'css', 'js', 'html', 'png', 'jpg', 'ico', 'jpeg', 'gif'], true);
    $bootstrap_file = PUBLIC_DIR . '/bootstrap.php';
    if (file_exists($bootstrap_file)) {
        if (unlink($bootstrap_file)) {
            log_message("Removed bootstrap file $bootstrap_file", VERBOSITY_VERBOSE);
        } else {
            log_message("Cannot remove $bootstrap_file", VERBOSITY_NORMAL);
        }
    }
    remove_files_by_extension($build_dir . '/private', PRIVATE_DIR, ['php'], true);
    remove_files_by_extension($build_dir . '/config', CONFIG_DIR, ['php'], true);

    // Remove Nginx configuration and soft link
    $nginx_conf = NGINX_CONF_FILE;
    $nginx_link = NGINX_SITES_ENABLED_DIR . '/brochat.conf';
    if (file_exists($nginx_link)) {
        if (unlink($nginx_link)) {
            log_message("Removed soft link $nginx_link", VERBOSITY_NORMAL);
        } else {
            log_message("Cannot remove $nginx_link", VERBOSITY_NORMAL, true);
        }
    } else {
        log_message("Soft link $nginx_link does not exist", VERBOSITY_NORMAL);
    }
    if (file_exists($nginx_conf)) {
        if (unlink($nginx_conf)) {
            log_message("Removed Nginx configuration $nginx_conf", VERBOSITY_NORMAL);
        } else {
            log_message("Cannot remove $nginx_conf", VERBOSITY_NORMAL, true);
        }
    } else {
        log_message("Nginx configuration $nginx_conf does not exist", VERBOSITY_NORMAL);
    }

    log_message("Uninstallation complete.");
}

function main() {
    global $defaults;

    if (is_file('build.log')) {
        unlink('build.log');
    }
    $options = parse_arguments();
    validate_directories();
    load_config();
    validate_config();

    $actions = ['build', 'install', 'uninstall', 'clean', 'clean-config'];
    $action_count = count(array_intersect(array_keys($options), $actions));
    if ($action_count === 0) {
        log_message("Error: No action specified. Use --build, --install, --uninstall, --clean, or --clean-config.", VERBOSITY_QUIET, true);
        exit(1);
    } elseif ($action_count > 1) {
        log_message("Error: Only one action can be specified.", VERBOSITY_QUIET, true);
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
            unlink($config_file) or log_message("Warning: Cannot remove $config_file", VERBOSITY_NORMAL, true);
        }
    } elseif (isset($options['install'])) {
        install($build_dir);
    } elseif (isset($options['uninstall'])) {
        uninstall($build_dir);
    }
}

main();
?>
