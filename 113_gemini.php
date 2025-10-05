<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'blog_user');
define('DB_PASS', 'your_strong_password_here');
define('DB_NAME', 'blog_db');
define('DB_PORT', 3306);
?>
<?php
require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
} catch (mysqli_sql_exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die('A database error occurred. Please try again later.');
}
?>