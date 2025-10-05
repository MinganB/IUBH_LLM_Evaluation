DB_HOST=localhost
DB_USER=root
DB_PASSWORD=secret
DB_NAME=blogdb
<?php
$envLoaded = false;
$envPaths = [
    __DIR__ . '/.env',
    __DIR__ . '/../.env',
    dirname(__DIR__) . '/.env',
    dirname(dirname(__DIR__)) . '/.env'
];
foreach ($envPaths as $path) {
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $name = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ((strlen($value) >= 2) && (($value[0] === '"' && $value[strlen($value) - 1] === '"') || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))) {
                $value = substr($value, 1, -1);
            }
            $_ENV[$name] = $value;
            putenv($name . '=' . $value);
            $_SERVER[$name] = $value;
        }
        $envLoaded = true;
        break;
    }
}
if (!$envLoaded) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Environment file not found.']);
    exit;
}
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');
if (!$host || !$user || !$dbname) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Database credentials are not properly set.']);
    exit;
}
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT => false
    ]);
    $stmt = $pdo->query('SELECT 1');
    $stmt->fetch();
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Database connection established.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
}