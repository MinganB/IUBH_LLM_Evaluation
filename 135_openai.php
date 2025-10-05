DB_HOST=localhost
DB_USER=blog_user
DB_PASSWORD=change_me
DB_NAME=blog_db

<?php
header('Content-Type: application/json');

$logDir = __DIR__ . '/../logs';
$logFile = $logDir . '/config_errors.log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
function logConfigError($message) {
    global $logFile;
    error_log($message, 3, $logFile);
}

function loadEnv($path) {
    if (!file_exists($path)) {
        logConfigError("ENV_LOAD: .env file not found at $path");
        return false;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $matches)) continue;
        $key = $matches[1];
        $val = $matches[2];
        if (strlen($val) >= 2 && (($val[0] === '"' && $val[strlen($val)-1] === '"') || ($val[0] === "'" && $val[strlen($val)-1] === "'"))) {
            $val = substr($val, 1, -1);
        }
        putenv("$key=$val");
        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
    }
    return true;
}

$envPath = null;
$possible = [
    __DIR__ . '/../.env',
    __DIR__ . '/.env',
];
foreach ($possible as $p) {
    if (file_exists($p)) { $envPath = $p; break; }
}
if ($envPath) {
    loadEnv($envPath);
} else {
    logConfigError("ENV_LOAD: .env file not found in expected locations.");
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Configuration error."]);
    exit;
}

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');

if ($host === false || $user === false || $password === false || $dbname === false) {
    logConfigError("DB_CONFIG: Missing required environment variables.");
    echo json_encode(["success" => false, "message" => "Configuration error."]);
    exit;
}

$host = (string)$host;
$user = (string)$user;
$password = (string)$password;
$dbname = (string)$dbname;

$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
    $stmt = $pdo->query("SELECT 1");
    $stmt->fetchColumn();
    echo json_encode(["success" => true, "message" => "Database connection established."]);
} catch (PDOException $e) {
    logConfigError("DB_CONNECT: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Unable to connect to the database."]);
    exit;
}
?>