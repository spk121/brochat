<?php
/**
 * Database Connection (Singleton)
 *
 * Ensures a single PDO instance is used throughout the application.
 */

#define('BOOTSTRAP', true);
#require_once __DIR__ . '/bootstrap.php';
require_once ABS_CONFIG_DIR . '/config.php';

/*
  Consider using environment variables for sensitive data instead of config.php.
  $dsn = getenv('DB_DSN') ?: DB_DSN;
  $user = getenv('DB_USER') ?: DB_USER;
  $pass = getenv('DB_PASS') ?: DB_PASS;
  self::$instance = new PDO($dsn, $user, $pass);
*/

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (!self::$instance) {
            try {
                self::$instance = new PDO(DB_DSN, NULL, NULL);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->exec('PRAGMA foreign_keys = ON'); // SQLite specific
            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}

?>
