<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'blog_user');
define('DB_PASSWORD', 'your_secure_password');
define('DB_NAME', 'blog_db');
?>
<?php
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>