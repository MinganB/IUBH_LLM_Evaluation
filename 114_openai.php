<?php
function logConfigError(string $message): void {
    $logPath = '/var/log/blog_config.log';
    $datetime = date('Y-m-d H:i:s');
    $entry = "[$datetime] CONFIG: $message" . PHP_EOL;
    if (is_writable(dirname($logPath)) || !file_exists($logPath)) {
        file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
    } else {
        $fallback = __DIR__ . '/config.log';
        file_put_contents($fallback, $entry, FILE_APPEND | LOCK_EX);
    }
}
function getDbConfig(): ?array {
    $required = ['DB_HOST','DB_NAME','DB_USER','DB_PASSWORD'];
    $missing = [];
    foreach ($required as $key) {
        $val = getenv($key);
        if ($val === false || $val === null || $val === '') {
            $missing[] = $key;
        }
    }
    if (!empty($missing)) {
        logConfigError('Missing required environment variables: ' . implode(', ', $missing));
        return null;
    }
    $host = getenv('DB_HOST');
    $dbname = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');
    $portRaw = getenv('DB_PORT');
    $port = ($portRaw !== false && $portRaw !== null && $portRaw !== '') ? (int)$portRaw : 3306;
    return [
        'host' => $host,
        'dbname' => $dbname,
        'user' => $user,
        'password' => $password,
        'port' => $port
    ];
}
?>


<?php
require_once 'config.php';
$config = getDbConfig();
if ($config === null) {
    http_response_code(500);
    echo 'Database configuration is unavailable.';
    exit;
}
$host = $config['host'];
$dbname = $config['dbname'];
$user = $config['user'];
$password = $config['password'];
$port = $config['port'];
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];
try {
    $pdo = new PDO($dsn, $user, $password, $options);
    $stmt = $pdo->query('SELECT 1');
    $result = $stmt->fetchColumn();
    echo 'Database connection established';
} catch (PDOException $e) {
    $logPath = '/var/log/blog_db_connect.log';
    $datetime = date('Y-m-d H:i:s');
    $entry = "[$datetime] DB_CONN_ERROR: " . $e->getMessage() . PHP_EOL;
    if (is_writable(dirname($logPath)) || !file_exists($logPath)) {
        file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
    } else {
        $fallback = __DIR__ . '/db_connect.log';
        file_put_contents($fallback, $entry, FILE_APPEND | LOCK_EX);
    }
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}
?>