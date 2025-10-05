<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0700, true);
}
$logFile = $logDir . '/config_errors.log';
if (!function_exists('logConfigError')) {
    function logConfigError(string $message, string $logPath = null): void {
        $path = $logPath ?: __DIR__ . '/logs/config_errors.log';
        $timestamp = (new DateTime())->format('Y-m-d H:i:s');
        file_put_contents($path, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    }
}
$host = getenv('DB_HOST');
$portVal = getenv('DB_PORT');
$dbName = getenv('DB_DATABASE');
$user = getenv('DB_USERNAME');
$passwordVal = getenv('DB_PASSWORD');
$charsetVal = getenv('DB_CHARSET');
$port = ($portVal !== false && $portVal !== '') ? (int)$portVal : 3306;
$charset = ($charsetVal !== false && $charsetVal !== '') ? $charsetVal : 'utf8mb4';
$missing = [];
if (!$host) $missing[] = 'DB_HOST';
if (!$dbName) $missing[] = 'DB_DATABASE';
if (!$user) $missing[] = 'DB_USERNAME';
if ($passwordVal === false) $missing[] = 'DB_PASSWORD';
$password = ($passwordVal !== false) ? $passwordVal : null;

$config = [
 'db' => [
  'host' => $host,
  'port' => $port,
  'dbname' => $dbName,
  'user' => $user,
  'password' => $password,
  'charset' => $charset,
  'ok' => empty($missing)
 ],
 'security' => [
  'log_path' => $logFile
 ]
];

if (!empty($missing)) {
    logConfigError('Missing environment variables: ' . implode(', ', $missing), $logFile);
}

return $config;
?> 

<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');

$config = require __DIR__ . '/config.php';
if (!isset($config['db']) || $config['db']['ok'] !== true) {
    http_response_code(500);
    echo 'Database configuration error.';
    exit;
}

$host = $config['db']['host'] ?? '';
$port = $config['db']['port'] ?? 3306;
$dbname = $config['db']['dbname'] ?? '';
$user = $config['db']['user'] ?? '';
$password = $config['db']['password'] ?? '';
$charset = $config['db']['charset'] ?? 'utf8mb4';
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
];

function logSecure(string $message): void {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0700, true);
    }
    $logFile = $logDir . '/db_errors.log';
    $ts = (new DateTime())->format('Y-m-d H:i:s');
    file_put_contents($logFile, "[$ts] $message" . PHP_EOL, FILE_APPEND);
}

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    logSecure('DB_CONNECTION_ERROR: ' . $e->getMessage());
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}
?>