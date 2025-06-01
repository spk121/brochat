#!/usr/bin/env php
<?php

// Script version and metadata
const VERSION = '0.1';
const PROGRAM_NAME = 'builder';

// Default configuration
$defaults = [
    'srcdir' => '.',
    'builddir' => 'build',
];

function parse_arguments() {
    global $argv, $defaults;

    $short_opts = '';
    $long_opts = ['build', 'install', 'uninstall', 'clean', 'help', 'version', 'wtf', 'fml', 'srcdir:', 'builddir:'];
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
Usage: build [options]
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
directory and copies the configuration files to the Nginx configuration.
If there is an existing data directory, it will not overwrite it, and will
error out if it exists.

  The 'uninstall' action removes the installed files from the webroot
and Nginx configuration, but, it will not remove the 'data' directory contents.

   The build script presumes that a build/config/config.php file
exists, which contains the configuration for the project.  You can
create the file by running the 'configure' script in the project root.
The 'clean' action will not remove the config.php file.
Use the 'clean-config' action to execute a clean that also removes the config.php file.

Options:
  --build                build the project (e.g., initialize database, copy files)
  --install              install the project to the configured prefix
  --uninstall            remove installed files
  --clean                remove build artifacts
  --clean-config         remove build artifacts and the config.php file
  --srcdir=DIR           top directory of unpacked source files
  --builddir=DIR         build directory for output files
  --version              output version information and exit
  --help                 display this help and exit
  --wtf                  output shrug emoji and exit
  --fml                  output flame emojis with 'This is fine' and exit
EOT;  
        echo $help_text;    
        exit(0);
    }

    // Set srcdir and builddir
    $defaults['srcdir'] = $options['srcdir'] ?? $defaults['srcdir'];
    $defaults['builddir'] = $options['builddir'] ?? $defaults['builddir'];

    return $options;
}

function ensure_writable_directory($path) {
    // Check if path exists
    if (file_exists($path)) {
        // If it exists but isn't a directory, return error
        if (!is_dir($path)) {
            echo "Error: '{$path}' exists but is not a directory.\n";
            return false;
        }

        // Check read/write permissions
        if (is_readable($path) && is_writable($path)) {
            return true; // Directory is good to go
        } else {
            echo "Error: '{$path}' exists but lacks read/write permissions.\n";
            return false;
        }
    }

    // Attempt to create directory
    if (!mkdir($path, 0755, true)) {
        echo "Error: Failed to create directory '{$path}'.\n";
        return false;
    }

    // Attempt to set read/write permissions
    if (!chmod($path, 0755)) {
        echo "Error: Failed to set permissions for '{$path}'.\n";
        return false;
    }

    return true; // Directory successfully created and set up
}

function validate_directories() {
    global $defaults;

    // Normalize paths to absolute
    $defaults['srcdir'] = realpath($defaults['srcdir']) ?: $defaults['srcdir'];
    $defaults['builddir'] = realpath($defaults['builddir']) ?: $defaults['builddir'];

    // Validate srcdir
    if (!is_dir($defaults['srcdir'])) {
        fwrite(STDERR, "Error: Source directory '{$defaults['srcdir']}' does not exist.\n");
        exit(1);
    }
    if (!is_readable($defaults['srcdir'])) {
        fwrite(STDERR, "Error: Source directory '{$defaults['srcdir']}' is not readable.\n");
        exit(1);
    }

    // Check if srcdir and builddir are the same
    if ($defaults['srcdir'] === $defaults['builddir']) {
        fwrite(STDERR, "Error: Source directory and build directory cannot be the same.\n");
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

    // Validate source directory
    if (!is_dir($from_dir)) {
        fwrite(STDERR, "Error: Source directory '$from_dir' does not exist.\n");
        return false;
    }

    // Create destination directory if it doesn't exist
    if (!is_dir($to_dir) && !mkdir($to_dir, 0755, true)) {
        fwrite(STDERR, "Error: Could not create destination directory '$to_dir'.\n");
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
        $filename = basename($file);
        $to_path = "$to_dir/$filename";

        // Check if file exists in destination
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
            fwrite(STDERR, "Error: Failed to copy '$file' to '$to_path'.\n");
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

    // Validate source directory
    if (!is_dir($from_dir)) {
        fwrite(STDERR, "Error: Source directory '$from_dir' does not exist.\n");
        return false;
    }

    // Validate destination directory
    if (!is_dir($to_dir)) {
        fwrite(STDERR, "Error: Destination directory '$to_dir' does not exist.\n");
        return false;
    }

    // Build glob pattern for extensions (e.g., *.php,*.txt)
    $patterns = array_map(fn($ext) => "$from_dir/*.$ext", $extensions);
    $files = [];

    // Collect all matching files from source directory
    foreach ($patterns as $pattern) {
        $files = array_merge($files, glob($pattern) ?: []);
    }

    // Track if any files were removed
    $files_removed = false;

    // Remove matching files from destination directory
    foreach ($files as $file) {
        $filename = basename($file);
        $to_path = "$to_dir/$filename";

        if (file_exists($to_path)) {
            if (!unlink($to_path)) {
                fwrite(STDERR, "Error: Failed to remove '$to_path'.\n");
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
            fwrite(STDERR, "Error: Failed to remove empty directory '$to_dir'.\n");
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
        // Validate input files
        if (!file_exists($init_sql)) {
            fwrite(STDERR, "Error: SQL file '$init_sql' does not exist.\n");
            return false;
        }

        // Read SQL file contents
        $sql = file_get_contents($init_sql);
        if ($sql === false) {
            fwrite(STDERR, "Error: Failed to read SQL file '$init_sql'.\n");
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
        fwrite(STDERR, "Error: Failed to initialize database '$db_path': " . $e->getMessage() . "\n");
        return false;
    } catch (Exception $e) {
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        return false;
    }
}

function load_config() {
    global $defaults;

    $config_file = rtrim($defaults['builddir'], '/') . '/config/config.php';
    if (!file_exists($config_file)) {
        fwrite(STDERR, "Error: $config_file not found. Run './configure' first.\n");
        exit(1);
    }
    require_once $config_file;
}

function validate_config(): void {
    $required = [
        'NGINX_WEBROOT', 'PRIVATE_DIR', 'DB_DIR', 'LOG_DIR', 'NOTES_DIR',
        'SNAPS_DIR', 'SNAPS_PREVIEWS_DIR', 'CONFIG_DIR', 'DB_FILE', 'MODE'
    ];
    foreach ($required as $const) {
        if (!defined($const)) {
            fwrite(STDERR, "Error: $const not defined in config.php\n");
            exit(1);
        }
    }
}

function run_command($command) {
    exec($command, $output, $return_var);
    if ($return_var !== 0) {
        fwrite(STDERR, "Error: Command failed: $command\n");
        exit(1);
    }
    return $output;
}

function build($src_dir, $build_dir) {
    global $defaults;

    echo "Building project...\n";

    // Ensure build directory exists and is writable
    if (!ensure_writable_directory($build_dir)) {
        exit(1);
    }

    // Initialize BroChat database from init.sql 
    $db_path = $build_dir . '/db/' . basename($DB_FILE);
    $init_sql = $src_dir .  '/private/init.sql';
    if (!file_exists($init_sql)) {
        fwrite(STDERR, "Error: SQL script $init_sql not found.\n");
        exit(1);
    }
    echo "Initializing BroChat database...\n";
    $db_dir = dirname($db_path);
    if (!ensure_writable_directory($db_dir)) {
        die("Error: Cannot create database directory $db_dir\n");
    }
    initialize_sqlite_db($db_path, $init_sql) or die("Error: Failed to initialize database.\n");
    echo "Database creation complete!\n";

    // Copy the private and public directories.
    copy_files_by_extension($src_dir . '/private', $build_dir . '/private', ['php', 'sql'], CopyOptions::OVERWRITE);
    copy_files_by_extension($src_dir . '/public', $build_dir . '/public', ['php', 'css', 'js', 'html', 'png', 'jpg', 'ico', 'jpeg', 'gif'], CopyOptions::OVERWRITE);
    copy_files_by_extension($src_dir . '/config', $build_dir . '/config', ['php'], CopyOptions::OVERWRITE);
    echo "Build complete.\n";
}

function clean($src_dir, $build_dir) {
    global $defaults;

    echo "Cleaning build artifacts...\n";
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
    echo "Clean complete.\n";
}

function install($build_dir) {
    global $defaults;
    echo "Installing to $NGINX_WEBROOT...\n";
    if (!ensure_writable_directory($NGINX_WEBROOT)) {
        die("Error: Cannot create $NGINX_WEBROOT\n");
    }
    copy_files_by_extension($build_dir . '/public', $NGINX_WEBROOT, ['php', 'css', 'js', 'html', 'png', 'jpg', 'ico', 'jpeg', 'gif'], CopyOptions::OVERWRITE);
    if (!ensure_writeable_directory($PRIVATE_DIR)) {
        die("Error: Cannot create " . $PRIVATE_DIR . "\n");
    }
    copy_files_by_extension($build_dir . '/private', $PRIVATE_DIR, ['php'], CopyOptions::OVERWRITE);
    if (!ensure_writeable_directory($DB_DIR)) {
        die("Error: Cannot create " . $DB_DIR . "\n");
    }
    copy_files_by_extension($build_dir . '/db', $DB_DIR, ['db'], CopyOptions::OVERWRITE);
    if (!ensure_writeable_directory($LOG_DIR)) {
        die("Error: Cannot create " . $LOG_DIR . "\n");
    }
    if (!ensure_writeable_directory($NOTES_DIR)) {
        die("Error: Cannot create " . $NOTES_DIR . "\n");
    }
    if (!ensure_writeable_directory($SNAPS_DIR)) {
        die("Error: Cannot create " . $SNAPS_DIR . "\n");
    }
    if (!ensure_writeable_directory($SNAPS_PREVIEWS_DIR)) {
        die("Error: Cannot create " . $SNAPS_PREVIEWS_DIR . "\n");
    }
    if (!ensure_writeable_directory($CONFIG_DIR)) {
        die("Error: Cannot create " . $CONFIG_DIR . "\n");
    }
    copy_files_by_extension($build_dir . '/config', $CONFIG_DIR, ['php'], CopyOptions::OVERWRITE);

    $nginx_conf = NGINX_SITES_AVAILABLE_DIR . '/brochat.conf';
    $nginx_content = <<<EOT
server {
    listen {PRODUCTION_HTTP_PORT};
    server_name {DOMAIN};
    root {NGINX_WEBROOT};
    index index.php;
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
    file_put_contents($nginx_conf, $nginx_content) or die("Error: Failed to write $nginx_conf\n");   
    echo "Installation complete.\n";
}

function uninstall($build_dir) {
    global $defaults;

    # Remove read-only files that were installed and were not generated by
    # or modified by the application.
    echo "Uninstalling...\n";
    remove_files_by_extension($build_dir . '/public', $NGINX_WEBROOT,
        ['php', 'css', 'js', 'html', 'png', 'jpg', 'ico', 'jpeg', 'gif'], true);
    remove_files_by_extension($build_dir . '/private', $PRIVATE_DIR, ['php'], true);
    remove_files_by_extension($build_dir . '/config', $CONFIG_DIR, ['php'], true);

    # We do not remove the database directory or any of the other
    # localstatedir directories, since it contains data generated by or
    # modified by the application.

    $nginx_conf = NGINX_SITES_AVAILABLE_DIR . '/brochat.conf';
    if (file_exists($nginx_conf)) {
        unlink($nginx_conf) or fwrite(STDERR, "Warning: Cannot remove $nginx_conf\n");
    }
    echo "Uninstallation complete.\n";
}

function main() {
    // Parse arguments and validate directories
    $options = parse_arguments();
    validate_directories();

    // Load config.php after determining builddir.
    load_config();
    // Validate that the configuration has all the required filename and directory constants.
    validate_config();

    // Check for valid action
    $actions = ['build', 'install', 'uninstall', 'clean', 'clean-config'];
    $action_count = count(array_intersect(array_keys($options), $actions));
    if ($action_count === 0) {
        fwrite(STDERR, "Error: No action specified. Use --build, --install, --uninstall, --clean, or --clean-config.\n");
        exit(1);
    } elseif ($action_count > 1) {
        fwrite(STDERR, "Error: Only one action can be specified.\n");
        exit(1);
    }

    $src_dir = rtrim($defaults['srcdir'], '/');
    $build_dir = rtrim($defaults['builddir'], '/');

    // Execute the requested action
    if (isset($options['build'])) {
        build($src_dir, $build_dir);
    } elseif (isset($options['clean'])) {
        clean($src_dir, $build_dir);
    } elseif (isset($options['clean-config'])) {
        clean($src_dir, $build_dir);
        $config_file = $build_dir . '/config/config.php';
        echo "Removing $config_file\n";
        if (file_exists($config_file)) {
            unlink($config_file) or fwrite(STDERR, "Warning: Cannot remove $config_file\n");
        }
    } elseif (isset($options['install'])) {
        install($build_dir);
    } elseif (isset($options['uninstall'])) {
        uninstall($build_dir);
    }
}

main();
?>