<?php
// Bootstrap file contains the build directory paths.
// This file is just used to test the build using the
// command-line version of PHP.
if (!defined('BOOTSTRAP')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=CP437');
    echo "Direct access not allowed.
";
    exit(2);
}
define('ABS_PUBLIC_DIR', '/home/mike/brochat/build/public');
define('ABS_PRIVATE_DIR', '/home/mike/brochat/build/private');
define('ABS_CONFIG_DIR', '/home/mike/brochat/build/config');
define('ABS_MAGIC_UUID', '53f9e544-3f34-11f0-9397-00155d8eea3b');
?>