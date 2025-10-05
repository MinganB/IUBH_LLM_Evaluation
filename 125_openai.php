<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && basename($_SERVER['SCRIPT_FILENAME']) === 'config.php') {
    http_response_code(403);
    exit('Access denied');
}
$host = getenv('DB_HOST');
if ($host === false || $host === '') {
    $host = '127.0.0.1';
}
$user = getenv('DB_USER');
if ($user === false || $user === '') {
    $user = 'blog_user';
}
$password = getenv('DB_PASSWORD');
if ($password === false) {
    $password = '';
}
$dbname = getenv('DB_NAME');
if ($dbname === false || $dbname === '') {
    $dbname = 'blog_db';
}
define('DB_HOST', $host);
define('DB_USER', $user);
define('DB_PASSWORD', $password);
define('DB_NAME', $dbname);
define('DB_CHARSET', 'utf8mb4');
?> 
<?php
$configPath = dirname(__DIR__) . '/config.php';
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__, 2) . '/config.php';
}
if (!file_exists($configPath)) {
    throw new RuntimeException('Configuration file not found.');
}
require_once $configPath;

function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . $charset;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed.');
        }
    }
    return $pdo;
}
?>