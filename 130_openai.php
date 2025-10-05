<?php
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    $defaultEnv = "DB_HOST=localhost\nDB_USER=root\nDB_PASSWORD=\nDB_NAME=blog\n";
    file_put_contents($envPath, $defaultEnv);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Created .env with placeholder values. Please update DB_HOST, DB_USER, DB_PASSWORD, DB_NAME and re-run.'
    ]);
    exit;
}
function loadEnv($path) {
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        $nameValue = explode('=', $line, 2);
        $name = trim($nameValue[0]);
        $value = isset($nameValue[1]) ? trim($nameValue[1]) : '';
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[strlen($value)-1] === '"') || ($value[0] === "'" && $value[strlen($value)-1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        if ($name !== '') {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
loadEnv($envPath);

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');

if (empty($host) || empty($user) || empty($dbname)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Missing required environment variables. Ensure DB_HOST, DB_USER, and DB_NAME are set in the .env file.'
    ]);
    exit;
}

$dsn = 'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8mb4';
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_PERSISTENT => false
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
    $pdo->query('SELECT 1');
    $response = [
        'success' => true,
        'message' => 'Successfully connected to the MySQL database.'
    ];
} catch (PDOException $e) {
    $response = [
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ];
}
header('Content-Type: application/json');
echo json_encode($response);
?>