#!/usr/bin/env php
<?php

// Script version and metadata
const VERSION = '0.1';
const PROGRAM_NAME = 'configure';

// Default configuration values
$config = [
    'prefix' => '/usr/local',
    'exec_prefix' => '@prefix@',
    'bindir' => '@exec_prefix@/bin',
    'libdir' => '@exec_prefix@/lib',
    'includedir' => '@prefix@/include',
    'datarootdir' => '@prefix@/share',
    'datadir' => '@datarootdir@',
    'localstatedir' => '@prefix@/var',
    'sysconfdir' => '@prefix@/etc',
    'pkgdatadir' => '@datadir@/brochat',
    'pkglocalstatedir' => '@localstatedir@/brochat',
    'pkgsysconfdir' => '@sysconfdir@/brochat',
    'php_fpm' => 'php-fpm',
    'php_fpm_version' => '',
    'nginx_version' => '',
    'sqlite_version' => '',
    'init_sql' => 'private/init.sql',
    'srcdir' => '.',
    'builddir' => 'build',
    'mode' => 'DEVELOPMENT',
    'domain' => 'yourdomain.com',
    'public_dir' => '@pkgdatadir@/public',
    'private_dir' => '@pkgdatadir@/private',
    'db_dir' => '@pkglocalstatedir@/db',
    'log_dir' => '@pkglocalstatedir@/logs',
    'notes_dir' => '@pkglocalstatedir@/notes',
    'snaps_dir' => '@pkglocalstatedir@/snaps',
    'snaps_previews_dir' => '@snaps_dir@/previews',
    'config_dir' => '@pkgsysconfdir@',
];

function parse_arguments() {
    global $config, $argv;

    $short_opts = '';
    $long_opts = [
        'prefix:', 'exec-prefix:', 'bindir:', 'libdir:', 'includedir:',
        'datarootdir:', 'datadir:', 'localstatedir:', 'sysconfdir:',
        'srcdir:', 'builddir:', 'mode:', 'version', 'help', 'wtf', 'fml'
    ];
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
        echo PROGRAM_NAME . " configure " . VERSION . "\n";
        exit(0);
    }

    // Handle --help
    if (isset($options['help'])) {
        echo "Usage: configure [options] [VAR=VALUE...]\n";
        echo "Options:\n";
        echo "  --prefix=PREFIX        installation prefix directory [{$config['prefix']}]\n";
        echo "  --exec-prefix=EPREFIX  prefix for machine-specific files [{$config['exec_prefix']}]\n";
        echo "  --bindir=DIR           directory for executable programs [{$config['bindir']}]\n";
        echo "  --libdir=DIR           directory for object code libraries [{$config['libdir']}]\n";
        echo "  --includedir=DIR       directory for PHP include files [{$config['includedir']}]\n";
        echo "  --datarootdir=DIR      read-only data root directory [{$config['datarootdir']}]\n";
        echo "  --datadir=DIR          read-only data directory [{$config['datadir']}]\n";
        echo "  --localstatedir=DIR    modifiable data directory [{$config['localstatedir']}]\n";
        echo "  --sysconfdir=DIR       system configuration directory [{$config['sysconfdir']}]\n";
        echo "  --srcdir=DIR           source directory containing config/config.php.in [{$config['srcdir']}]\n";
        echo "  --builddir=DIR         build directory for output config/config.php [{$config['builddir']}]\n";
        echo "  --mode=MODE            development or production mode [{$config['mode']}]\n";
        echo "  --version              output version information and exit\n";
        echo "  --help                 display this help and exit\n";
        echo "  --wtf                  output shrug emoji and exit\n";
        echo "  --fml                  output flame emojis with 'This is fine' and exit\n";
        echo "\nEnvironment variables:\n";
        echo "  VAR=VALUE              set configuration variables (e.g., PHP_VERSION=8.2, DOMAIN=example.com)\n";
        echo "\nThe 'configure' script generates a 'config.status' shell script that can be run\n";
        echo "to recreate the current configuration.\n";
        echo "\nReport bugs to <your-contact-info>.\n";
        exit(0);
    }

    // Update config with options
    foreach (['prefix', 'exec_prefix', 'bindir', 'libdir', 'includedir', 'datarootdir', 'datadir', 'localstatedir', 'sysconfdir', 'srcdir', 'builddir', 'mode', 'domain'] as $key) {
        $config[$key] = $options[$key] ?? $config[$key];
    }

    // Validate mode
    if (!in_array($config['mode'], ['DEVELOPMENT', 'PRODUCTION'])) {
        fwrite(STDERR, "Error: --mode must be DEVELOPMENT or PRODUCTION\n");
        exit(1);
    }

    // Parse VAR=VALUE arguments
    foreach ($argv as $arg) {
        if (preg_match('/^([A-Z_]+)=(.+)$/', $arg, $matches)) {
            $config[strtolower($matches[1])] = $matches[2];
        }
    }

    // Parse environment variables
    foreach ($_ENV as $key => $value) {
        if (preg_match('/^[A-Z_]+$/', $key)) {
            $config[strtolower($key]) = $value;
        }
    }

    return $argv;
}

function validate_directories() {
    global $config;

    // Normalize paths
    $config['srcdir'] = realpath($config['srcdir']) ?: $config['srcdir'];
    $config['builddir'] = realpath($config['builddir']) ?: $config['builddir'];

    // Validate srcdir
    if (!is_dir($config['srcdir'])) {
        fwrite(STDERR, "Error: Source directory '{$config['srcdir']}' does not exist.\n");
        exit(1);
    }
    if (!is_readable($config['srcdir'])) {
        fwrite(STDERR, "Error: Source directory '{$config['srcdir']}' is not readable.\n");
        exit(1);
    }

    // Check if srcdir and builddir are the same
    if ($config['srcdir'] === $config['builddir']) {
        fwrite(STDERR, "Error: Source directory and build directory cannot be the same.\n");
        exit(1);
    }
}

function check_sqlite() {
    global $config;

    $command = "sqlite3 -version 2>&1";
    exec($command, $output, $return_var);
    if ($return_var !== 0 || empty($output)) {
        fwrite(STDERR, "Error: sqlite3 not found or not working.\n");
        exit(1);
    }
    $version_string = implode("\n", $output);
    if (preg_match('/^(\d+\.\d+\.\d+)/', $version_string, $matches)) {
        $config['sqlite_version'] = $matches[1];
    } else {
        fwrite(STDERR, "Error: Could not determine sqlite3 version.\n");
        exit(1);
    }
}

function check_nginx() {
    global $config;

    $command = "nginx -v 2>&1";
    exec($command, $output, $return_var);
    if ($return_var !== 0 || empty($output)) {
        fwrite(STDERR, "Error: nginx not found or not working.\n");
        exit(1);
    }
    $version_string = implode("\n", $output);
    if (preg_match('/nginx\/(\d+\.\d+\.\d+)/', $version_string, $matches)) {
        $config['nginx_version'] = $matches[1];
    } else {
        fwrite(STDERR, "Error: Could not determine nginx version.\n");
        exit(1);
    }
}

function find_highest_php_fpm_version() {
    global $config;

    // Use specified PHP_VERSION if provided
    if (isset($config['php_version'])) {
        $php_fpm_bin = "php-fpm" . preg_replace('/^(\d+\.\d+).*/', '$1', $config['php_version']);
        $command = escapeshellcmd($php_fpm_bin) . " -v 2>&1";
        exec($command, $output, $return_var);
        if ($return_var !== 0 || empty($output)) {
            fwrite(STDERR, "Error: $php_fpm_bin not found or not working.\n");
            exit(1);
        }
        $version_string = implode("\n", $output);
        if (preg_match('/PHP\s+(\d+\.\d+\.\d+)/', $version_string, $matches)) {
            $config['php_fpm_version'] = $matches[1];
            $config['php_fpm'] = $php_fpm_bin;
            if ($matches[1] !== $config['php_version']) {
                fwrite(STDERR, "Warning: Specified PHP_VERSION {$config['php_version']} does not match $php_fpm_bin version {$matches[1]}.\n");
            }
            return true;
        } else {
            fwrite(STDERR, "Error: Could not determine $php_fpm_bin version.\n");
            exit(1);
        }
    }

    // Find highest php-fpm version
    $php_fpm_bin = 'php-fpm';
    $possible_bins = [$php_fpm_bin];

    for ($major = 5; $major <= 8; $major++) {
        for ($minor = 0; $minor <= 9; $minor++) {
            $possible_bins[] = "php-fpm{$major}.{$minor}";
        }
    }

    $highest_version = null;
    $selected_bin = null;

    foreach ($possible_bins as $bin_name) {
        $command = "command -v " . escapeshellarg($bin_name);
        exec($command, $output, $return_var);
        if ($return_var === 0 && !empty($output)) {
            $bin_path = $output[0];
            $version_command = escapeshellcmd($bin_path) . " -v 2>&1";
            exec($version_command, $version_output, $version_return_var);
            if ($version_return_var === 0) {
                $version_string = implode("\n", $version_output);
                if (preg_match('/PHP\s+(\d+\.\d+\.\d+)/', $version_string, $matches)) {
                    $version = $matches[1];
                    if ($highest_version === null || version_compare($version, $highest_version, '>')) {
                        $highest_version = $version;
                        $selected_bin = $bin_name;
                    }
                }
            }
        }
        $output = []; // Reset output
    }

    if ($selected_bin && $highest_version) {
        $config['php_fpm'] = $selected_bin;
        $config['php_fpm_version'] = $highest_version;
        return true;
    } else {
        fwrite(STDERR, "Error: No php-fpm binary found in PATH.\n");
        exit(1);
    }
}

function substitute_template($input_file, $output_file, $config) {
    if (!file_exists($input_file)) {
        fwrite(STDERR, "Error: {$input_file} not found.\n");
        exit(1);
    }
    $content = @file_get_contents($input_file);
    if ($content === false) {
        fwrite(STDERR, "Error: Failed to read {$input_file}.\n");
        exit(1);
    }

    // Replace @variable@ placeholders
    $content = preg_replace_callback('/@(\w+)@/', function ($matches) use ($config) {
        $key = strtolower($matches[1]);
        $value = $config[$key] ?? $matches[0];
        while (preg_match('/@(\w+)@/', $value, $nested)) {
            $nested_key = strtolower($nested[1]);
            $nested_value = $config[$nested_key] ?? $nested[0];
            $value = str_replace($nested[0], $nested_value, $value);
        }
        return $value;
    }, $content);

    // Replace defined constants
    $content = preg_replace_callback('/define\(\'([A-Z_]+)\',/', function ($matches) use ($config) {
        $key = strtolower($matches[1]);
        if (isset($config[$key])) {
            return "define('{$matches[1]}',";
        }
        return $matches[0];
    }, $content);

    // Add header
    $header = "<?php\n";
    $header .= "# Generated by " . PROGRAM_NAME . " (version " . VERSION . ") from " . basename($input_file) . "\n";
    $header .= "# Generated on Sun, 01 Jun 2025 02:08:00 -0700\n";
    $header .= "# DO NOT EDIT (changes will be lost when configure is rerun)\n";
    $header .= "?>\n";
    $content = $header . $content;

    // Write to output file
    if (@file_put_contents($output_file, $content) === false) {
        fwrite(STDERR, "Error: Failed to write {$output_file}.\n");
        exit(1);
    }
}

function generate_config_status($argv) {
    $content = "#!/bin/sh\n";
    $content .= "# Generated by " . PROGRAM_NAME . " (version " . VERSION . ")\n";
    $content .= "# Run this to recreate the current configuration\n\n";

    $script_name = './' . basename($argv[0]);
    array_shift($argv);
    $quoted_args = array_map(function ($arg) {
        if (preg_match('/[\s|&;<>()$`\\"\']/', $arg)) {
            return '"' . str_replace('"', '\"', $arg) . '"';
        }
        return $arg;
    }, $argv);
    $command = $script_name . ' ' . implode(' ', $quoted_args);
    $content .= "exec $command\n";

    if (@file_put_contents('config.status', $content) === false) {
        fwrite(STDERR, "Error: Failed to write config.status.\n");
        exit(1);
    }
    chmod('config.status', 0755) or fwrite(STDERR, "Warning: Could not make config.status executable.\n");
}

function main() {
    global $config, $argv;

    // Step 1: Process command-line arguments and environment variables
    $original_argv = parse_arguments();

    // Step 2: Validate srcdir and builddir
    validate_directories();

    // Step 3: Check required tools
    check_sqlite();
    check_nginx();
    find_highest_php_fpm_version();

    // Set PHP_VERSION to PHP_FPM_VERSION if not provided
    if (!isset($config['php_version'])) {
        $config['php_version'] = $config['php_fpm_version'];
    }

    // Step 4: Generate config.php from config.php.in
    $input_file = rtrim($config['srcdir'], '/') . '/config/config.php.in';
    $output_file = rtrim($config['builddir'], '/') . '/config/config.php';
    if (!is_dir(dirname($output_file))) {
        mkdir(dirname($output_file), 0755, true) or die("Error: Cannot create directory for $output_file\n");
    }
    substitute_template($input_file, $output_file, $config);

    // Step 5: Generate config.status
    generate_config_status($original_argv);

    echo "Configuration complete. Run './builder --build' to build.\n";
    echo "Using php-fpm: {$config['php_fpm']} (version {$config['php_fpm_version']})\n";
    echo "Using nginx: version {$config['nginx_version']}\n";
    echo "Using sqlite3: version {$config['sqlite_version']}\n";
}

main();

?>
