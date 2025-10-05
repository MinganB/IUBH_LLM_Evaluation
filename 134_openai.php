DB_HOST=localhost
DB_USER=root
DB_PASSWORD=secret
DB_NAME=blog_db

<?php
$envLoaded = false;
$envPaths = [
    dirname(__DIR__) . '/.env',
    dirname(__DIR__, 2) . '/.env',
    dirname(__DIR__, 3) . '/.env',
    dirname(__DIR__, 4) . '/.env',
    dirname(__DIR__, 5) . '/.env',
    __DIR__ . '/../.env',
    __DIR__ . '/.env',
];

foreach ($envPaths as $path) {
    if (file_exists($path) && is_readable($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
                $value = stripcslashes(substr($value, 1, -1));
            } elseif (strlen($value) >= 2 && $value[0] === "'" && $value[strlen($value) - 1] === "'") {
                $value = substr($value, 1, -1);
            }
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
        $envLoaded = true;
        break;
    }
}
if (!$envLoaded) {
    $response = ['success' => false, 'message' => 'Environment file (.env) not found.'];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');

if (!$host || !$user || !$dbname) {
    $response = ['success' => false, 'message' => 'Database credentials are not fully defined in environment.'];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$dsn = 'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8mb4';
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
    $response = ['success' => true, 'message' => 'Database connection established.'];
} catch (PDOException $e) {
    $response = ['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($response);
exit;