**config.php**

<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'blog_user');
define('DB_PASSWORD', 'secure_password_here');
define('DB_NAME', 'blog_database');
define('DB_CHARSET', 'utf8mb4');

if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASSWORD') || !defined('DB_NAME')) {
    die('Database configuration constants are not properly defined.');
}

?>


**db_connect.php**

<?php

require_once 'config.php';

function getDatabaseConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die('Database connection failed. Please try again later.');
    }
}

$db = getDatabaseConnection();

?>