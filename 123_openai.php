<?php
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0700, true); }
$logFilePath = $logDir . '/config_errors.log';
function log_config_error($message) {
    global $logFilePath;
    if (!$logFilePath) { return; }
    $date = date('Y-m-d H:i:s');
    error_log("[$date] $message", 3, $logFilePath);
}
function getEnvOrLog($name) {
    $value = getenv($name);
    if ($value === false || $value === '') {
        log_config_error("Missing environment variable: {$name}");
        return null;
    }
    return $value;
}
$host = getEnvOrLog('DB_HOST');
$user = getEnvOrLog('DB_USER');
$password = getEnvOrLog('DB_PASSWORD');
$dbName = getEnvOrLog('DB_NAME');
if ($host === null || $user === null || $password === null || $dbName === null) {
    define('DB_HOST', '');
    define('DB_USER', '');
    define('DB_PASSWORD', '');
    define('DB_NAME', '');
} else {
    define('DB_HOST', $host);
    define('DB_USER', $user);
    define('DB_PASSWORD', $password);
    define('DB_NAME', $dbName);
}
?><?php
require_once __DIR__ . '/config.php';
$pdo = null;
if (empty(DB_HOST) || empty(DB_USER) || empty(DB_PASSWORD) || empty(DB_NAME)) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0700, true); }
    $logFile = $logDir . '/db_errors.log';
    $date = date('Y-m-d H:i:s');
    error_log("[$date] Incomplete database configuration.", 3, $logFile);
    exit;
}
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];
try {
  $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
} catch (PDOException $e) {
  $logDir = __DIR__ . '/logs';
  if (!is_dir($logDir)) { @mkdir($logDir, 0700, true); }
  $logFile = $logDir . '/db_errors.log';
  $date = date('Y-m-d H:i:s');
  error_log("[$date] Database connection failed: " . $e->getMessage(), 3, $logFile);
  exit;
}
?>