<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'blog_user');
define('DB_PASS', 'secure_password_123');
define('DB_NAME', 'blog_db');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');
?>
<?php
require_once __DIR__ . '/../config/database.php';

class DatabaseConnection {
    private static $connection = null;

    public static function getConnection(): mysqli {
        if (self::$connection === null) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            try {
                self::$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
                self::$connection->set_charset(DB_CHARSET);
            } catch (mysqli_sql_exception $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                throw new Exception('A database connection error occurred.');
            }
        }
        return self::$connection;
    }

    public static function closeConnection(): void {
        if (self::$connection !== null) {
            self::$connection->close();
            self::$connection = null;
        }
    }
}
?>