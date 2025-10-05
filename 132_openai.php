DB_HOST=localhost
DB_USER=blog_user
DB_PASSWORD=change_me
DB_NAME=blog_db

<?php
ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('error_log', __DIR__.'/config_errors.log');

function logSec($message) {
    $logFile = '/var/log/blog_config.log';
    if (!is_writable('/var/log') || !is_writable($logFile)) {
        $logFile = __DIR__.'/config.log';
        if (!is_writable(__DIR__)) {
            return;
        }
    }
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

function loadEnv($path) {
    if (!file_exists($path)) {
        logSec('Env file not found: '.$path);
        return false;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[strlen($value)-1] === '"') || ($value[0] === "'" && $value[strlen($value)-1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv($name.'='.$value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
    return true;
}

loadEnv(__DIR__.'/.env');

$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPassword = getenv('DB_PASSWORD');
$dbName = getenv('DB_NAME');

$missing = [];
if (!$dbHost) $missing[] = 'DB_HOST';
if (!$dbUser) $missing[] = 'DB_USER';
if (!$dbPassword) $missing[] = 'DB_PASSWORD';
if (!$dbName) $missing[] = 'DB_NAME';

if (!empty($missing)) {
    logSec('Missing environment variables: '.implode(', ', $missing).' from .env');
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'Configuration error. Missing required environment variables.']);
    exit;
}

$dsn = 'mysql:host='.$dbHost.';dbname='.$dbName.';charset=utf8mb4';
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_PERSISTENT => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, $options);
    $stmt = $pdo->query('SELECT 1');
    $stmt->fetch();
    header('Content-Type: application/json');
    echo json_encode(['success'=>true,'message'=>'Database connection established.']);
    exit;
} catch (PDOException $e) {
    logSec('Database connection failed: '.$e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'Unable to connect to the database.']);
    exit;
}
?>