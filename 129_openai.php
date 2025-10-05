DB_HOST=localhost
DB_USER=your_db_user
DB_PASSWORD=your_db_password
DB_NAME=your_db_name

<?php
function logError($message, $context = []) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $logFile = $logDir . '/config.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = '';
    if (!empty($context) && is_array($context)) {
        $parts = [];
        foreach ($context as $k => $v) {
            $parts[] = $k . '=' . var_export($v, true);
        }
        if (!empty($parts)) {
            $contextStr = ' | ' . implode(', ', $parts);
        }
    }
    file_put_contents($logFile, "[$timestamp] $message$contextStr" . PHP_EOL, FILE_APPEND);
}
function loadEnv($path) {
    if (!is_readable($path)) {
        logError("Environment file not readable: " . $path, []);
        return false;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }
        $name = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));
        if ($name === '') {
            continue;
        }
        if ((strlen($value) >= 2) && (($value[0] === '"' && $value[strlen($value) - 1] === '"') || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
    return true;
}

$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    loadEnv($envPath);
} else {
    logError("Environment file not found: " . $envPath, []);
}

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');

$missing = [];
if (!$host) $missing[] = 'DB_HOST';
if (!$user) $missing[] = 'DB_USER';
if (!$password) $missing[] = 'DB_PASSWORD';
if (!$dbname) $missing[] = 'DB_NAME';

if (!empty($missing)) {
    logError("Missing required DB configuration keys: " . implode(', ', $missing), []);
    $response = ['success' => false, 'message' => 'Database configuration is invalid. Please check configuration file.'];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $stmt = $pdo->query("SELECT 1");
    $stmt->fetch();
    $response = ['success' => true, 'message' => 'Connection established.'];
} catch (PDOException $e) {
    logError("Database connection failed: " . $e->getMessage(), ['host' => $host, 'dbname' => $dbname]);
    $response = ['success' => false, 'message' => 'Could not connect to the database.'];
} catch (Exception $e) {
    logError("Unexpected error: " . $e->getMessage(), []);
    $response = ['success' => false, 'message' => 'Could not connect to the database.'];
}
header('Content-Type: application/json');
echo json_encode($response);