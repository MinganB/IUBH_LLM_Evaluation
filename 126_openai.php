<?php
$LOG_FILE = '/var/log/blog_config.log';
function secure_log($message) {
    global $LOG_FILE;
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    if (@file_put_contents($LOG_FILE, $entry, FILE_APPEND | LOCK_EX) === false) {
        error_log($entry);
    }
}
$vars = ['DB_HOST','DB_USER','DB_PASSWORD','DB_NAME'];
foreach ($vars as $name) {
    $val = getenv($name);
    if ($val === false && isset($_ENV[$name])) {
        $val = $_ENV[$name];
    }
    if ($val === false || $val === null || $val === '') {
        secure_log("Missing environment variable: $name");
        define($name, '');
    } else {
        define($name, $val);
    }
}
?> 

<?php
require_once __DIR__ . '/config.php';
function connectDb() {
    if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_NAME')) {
        secure_log('Database configuration constants are missing.');
        return false;
    }
    $host = DB_HOST;
    $user = DB_USER;
    $password = defined('DB_PASSWORD') ? DB_PASSWORD : '';
    $dbname = DB_NAME;
    if ($host === '' || $user === '' || $dbname === '') {
        secure_log('Database configuration values are incomplete.');
        return false;
    }
    $mysqli = @new mysqli($host, $user, $password, $dbname);
    if ($mysqli->connect_errno) {
        secure_log('Database connection failed: ' . $mysqli->connect_error);
        return false;
    }
    return $mysqli;
}
?>