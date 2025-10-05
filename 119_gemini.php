<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASSWORD', 'your_db_password');
define('DB_NAME', 'your_db_name');

?>
<?php

require_once 'config.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($mysqli->connect_errno) {
    error_log("Failed to connect to MySQL: " . $mysqli->connect_error);
    http_response_code(500);
    die("Database connection failed. Please try again later.");
}

?>