<?php
$ENV_HOST = getenv('DB_HOST') ?: '';
$ENV_USER = getenv('DB_USER') ?: '';
$ENV_PASSWORD = getenv('DB_PASSWORD') ?: '';
$ENV_NAME = getenv('DB_NAME') ?: '';

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0700, true);
}
$CONFIG_LOG_FILE = $logDir . '/config_errors.log';
function logConfigError($message, $logFile) {
    error_log(date('Y-m-d H:i:s') . " - " . $message . PHP_EOL, 3, $logFile);
}
if (empty($ENV_HOST) || empty($ENV_USER) || empty($ENV_PASSWORD) || empty($ENV_NAME)) {
    logConfigError('Missing DB credentials in environment. Host: ' . var_export($ENV_HOST, true) . ', User: ' . var_export($ENV_USER, true) . ', DB: ' . var_export($ENV_NAME, true), $CONFIG_LOG_FILE);
}
define('DB_HOST', $ENV_HOST);
define('DB_USER', $ENV_USER);
define('DB_PASSWORD', $ENV_PASSWORD);
define('DB_NAME', $ENV_NAME);
?> 
<?php
require_once __DIR__ . '/config.php';
$pdo = null;
if (empty(DB_HOST) || empty(DB_USER) || empty(DB_PASSWORD) || empty(DB_NAME)) {
    http_response_code(500);
    echo 'A database error occurred.';
    exit;
}
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    $logsDir = __DIR__ . '/logs';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0700, true);
    }
    $logPath = $logsDir . '/db_connect.log';
    error_log(date('Y-m-d H:i:s') . " - DB connect failed: " . $e->getMessage() . " - DSN: " . $dsn . PHP_EOL, 3, $logPath);
    http_response_code(500);
    echo 'A database error occurred.';
    exit;
}
?>