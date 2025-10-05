<?php
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'db_user');
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', 'db_password');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'db_name');
}
?> 

<?php
require_once __DIR__ . '/config.php';
function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
    }
    return $pdo;
}
?>