<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'blog_user');
define('DB_PASSWORD', 'your_strong_password');
define('DB_NAME', 'blog_db');
?>
<?php
require_once 'config.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($mysqli->connect_errno) {
    error_log("Failed to connect to MySQL: " . $mysqli->connect_error);
    http_response_code(500);
    exit("Database connection failed. Please try again later.");
}
?>