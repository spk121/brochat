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
        echo "Usage: build [options]\n";
        echo "   This script builds and manages the Bro Chat project.\n";
        echo "\n";
        echo "    It supports five actions: build, clean, clean-config, install, and uninstall.\n";
        echo "\n";
        echo "   The 'build' action is either development or production, depending on the\n";
        echo "configuration in the config.php file. It initializes an empty SQLite database with the\n";
        echo "default schema, creates configuration files for the LSNP stack (Linux,\n";
        echo "SQLite, Nginx, PHP), and copies the PHP source files to the build directory.\n";
        echo "\n"
        echo "   The 'clean' action removes build artifacts in the build directory, except\n";
        echo "for the config.php configuration file.\n";
        echo "\n";
        echo "   The 'clean-config' action removes the build artifacts and the config.php file.\n";
        echo "\n";
        echo "   The 'install' action copies the built files to the configured webroot\n";
        echo "directory and copies the configuration files to the Nginx configuration.\n";
        echo "If there is an existing data directory, it will not overwrite it, and will\n";
        echo "error out if it exists.\n";
        echo "\n";
        echo "  The 'uninstall' action removes the installed files from the webroot\n";
        echo "and Nginx configuration, but, it will not remove the 'data' directory contents.\n";
        echo "\n";
        echo "   The build script presumes that a build/config/config.php file\n";
        echo "exists, which contains the configuration for the project.  You can\n";
        echo "create the file by running the 'configure' script in the project root.\n";
        echo "The 'clean' action will not remove the config.php file.\n";
        echo "Use the 'clean-config' action to execute a clean that also removes the config.php file.\n";
        echo "\n";
        echo "Options:\n";
        echo "  --build                build the project (e.g., initialize database, copy files)\n";
        echo "  --install              install the project to the configured prefix\n";
        echo "  --uninstall            remove installed files\n";
        echo "  --clean                remove build artifacts\n";
        echo "  --clean-config         remove build artifacts and the config.php file\n";
        echo "  --srcdir=DIR           source directory containing PHP source files [{$defaults['srcdir']}]\n";
        echo "  --builddir=DIR         build directory for output files [{$defaults['builddir']}]\n";
        echo "  --version              output version information and exit\n";
        echo "  --help                 display this help and exit\n";
        echo "  --wtf                  output shrug emoji and exit\n";
        echo "  --fml                  output flame emojis with 'This is fine' and exit\n";
        exit(0);
    }

    // Set srcdir and builddir
    $defaults['srcdir'] = $options['srcdir'] ?? $defaults['srcdir'];
    $defaults['builddir'] = $options['builddir'] ?? $defaults['builddir'];

    return $options;
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

function load_config() {
    global $defaults;

    $config_file = rtrim($defaults['builddir'], '/') . '/config/config.php';
    if (!file_exists($config_file)) {
        fwrite(STDERR, "Error: $config_file not found. Run './configure' first.\n");
        exit(1);
    }
    require_once $config_file;
}

function run_command($command) {
    exec($command, $output, $return_var);
    if ($return_var !== 0) {
        fwrite(STDERR, "Error: Command failed: $command\n");
        exit(1);
    }
    return $output;
}

function build() {
    global $defaults;

    echo "Building project...\n";

    // Initialize BroChat database
    $db_path = rtrim($defaults['builddir'], '/') . '/data/brochat.db';
    $init_sql = defined('INIT_SQL') ? INIT_SQL : 'init.sql';
    if (!file_exists($init_sql)) {
        fwrite(STDERR, "Error: SQL script $init_sql not found.\n");
        exit(1);
    }
    echo "Initializing BroChat database...\n";
    $data_dir = dirname($db_path);
    if (!is_dir($data_dir)) {
        mkdir($data_dir, 0755, true) or die("Error: Cannot create data directory $data_dir\n");
    }
    run_command("sqlite3 $db_path < $init_sql");
    echo "Database creation complete!\n";

    // Create build directory and copy PHP source files
    $build_dir = rtrim($defaults['builddir'], '/');
    if (!is_dir($build_dir)) {
        mkdir($build_dir, 0755, true) or die("Error: Cannot create build directory $build_dir\n");
    }
    $src_dir = rtrim($defaults['srcdir'], '/');
    if (is_dir($src_dir)) {
        run_command("cp -r $src_dir/*.php $build_dir/");
    }
    echo "Build complete.\n";
}

function install() {
    if (!defined('NGINX_WEBROOT')) {
        fwrite(STDERR, "Error: NGINX_WEBROOT not defined in config.php\n");
        exit(1);
    }
    global $defaults;

    $webroot = NGINX_WEBROOT;
    echo "Installing to $webroot...\n";
    if (!is_dir($webroot)) {
        mkdir($webroot, 0755, true) or die("Error: Cannot create $webroot\n");
    }
    $build_dir = rtrim($defaults['builddir'], '/');
    if (is_dir($build_dir)) {
        run_command("cp -r $build_dir/*.php $webroot/");
    }
    run_command("chmod -R u+rwX,go+rX $webroot");
    echo "Installation complete.\n";
}

function uninstall() {
    if (!defined('NGINX_WEBROOT')) {
        fwrite(STDERR, "Error: NGINX_WEBROOT not defined in config.php\n");
        exit(1);
    }
    global $defaults;

    $webroot = NGINX_WEBROOT;
    echo "Uninstalling from $webroot...\n";
    $build_dir = rtrim($defaults['builddir'], '/');
    if (is_dir($build_dir)) {
        foreach (glob("$build_dir/*.php") as $file) {
            $basename = basename($file);
            if (file_exists("$webroot/$basename")) {
                unlink("$webroot/$basename") or fwrite(STDERR, "Warning: Cannot remove $webroot/$basename\n");
            }
        }
    }
    echo "Uninstallation complete.\n";
}

function clean() {
    global $defaults;

    echo "Cleaning build artifacts...\n";
    $build_dir = rtrim($defaults['builddir'], '/');
    if (is_dir($build_dir)) {
        run_command("rm -rf $build_dir/*");
    }
    $db_path = "$build_dir/data/brochat.db";
    if (file_exists($db_path)) {
        unlink($db_path) or fwrite(STDERR, "Warning: Cannot remove $db_path\n");
    }
    echo "Clean complete.\n";
}

function main() {
    // Parse arguments and validate directories
    $options = parse_arguments();
    validate_directories();

    // Load config.php after determining builddir
    load_config();

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

    // Execute the requested action
    if (isset($options['build'])) {
        build();
    } elseif (isset($options['install'])) {
        install();
    } elseif (isset($options['uninstall'])) {
        uninstall();
    } elseif (isset($options['clean'])) {
        clean();
    } elseif (isset($options['clean-config'])) {
        clean();
        $config_file = rtrim($defaults['builddir'], '/') . '/config/config.php';
        echo "Removing $config_file\n";
        if (file_exists($config_file)) {
            unlink($config_file) or fwrite(STDERR, "Warning: Cannot remove $config_file\n");
        }
    }
}

main();
