config.php:
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'blog_user');
define('DB_PASSWORD', 'secure_password_123');
define('DB_NAME', 'blog_database');
?>


db_connect.php:
<?php
require_once 'config.php';

$connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

$connection->set_charset("utf8");
?>