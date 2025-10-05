<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_username');
define('DB_PASSWORD', 'your_db_password');
define('DB_NAME', 'your_blog_database_name');
?>
<?php
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    die('An unexpected database error occurred. Please try again later.');
}

$conn->set_charset("utf8mb4");
?>