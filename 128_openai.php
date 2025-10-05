DB_HOST=localhost
DB_USER=root
DB_PASSWORD=changeme
DB_NAME=exampledb

<?php
header('Content-Type: application/json');

function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        $parts = explode('=', $line, 2);
        $name = trim($parts[0] ?? '');
        $value = isset($parts[1]) ? trim($parts[1]) : '';
        if ($name === '') continue;
        $first = substr($value, 0, 1);
        $last = substr($value, -1);
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $value = substr($value, 1, -1);
        }
        putenv($name.'='.$value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

$envFound = false;
$paths = [__DIR__.'/.env', __DIR__.'/.env.local', __DIR__.'/.env.dist'];
foreach ($paths as $p) {
    if (file_exists($p)) {
        loadEnv($p);
        $envFound = true;
        break;
    }
}
if (!$envFound) {
    $default = __DIR__.'/.env';
    if (file_exists($default)) {
        loadEnv($default);
        $envFound = true;
    }
}
if (!$envFound) {
    echo json_encode(['success'=>false,'message'=>'Environment file not found.']);
    exit;
}

$host = getenv('DB_HOST') ?: '';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASSWORD') ?: '';
$db   = getenv('DB_NAME') ?: '';

if (empty($host) || empty($user) || empty($db)) {
    echo json_encode(['success'=>false,'message'=>'Database configuration is incomplete.']);
    exit;
}

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo = null;
    echo json_encode(['success'=>true,'message'=>'Database connection successful.']);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Database connection failed: '.$e->getMessage()]);
}
?>