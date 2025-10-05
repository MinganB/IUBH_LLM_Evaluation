DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=blogdb
<?php
header('Content-Type: application/json; charset=utf-8');

$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        $first = $value[0] ?? '';
        $last = substr($value, -1);
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$name] = $value;
        putenv("$name=$value");
    }
}
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');
$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false,
];
$response = ['success' => false, 'message' => 'Unknown error'];
try {
    $pdo = new PDO($dsn, $user, $password, $options);
    $pdo->query('SELECT 1');
    $response['success'] = true;
    $response['message'] = 'Connection established successfully';
} catch (PDOException $e) {
    $response['success'] = false;
    $response['message'] = 'Connection failed: ' . $e->getMessage();
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'An error occurred: ' . $e->getMessage();
} finally {
    if (isset($pdo)) {
        $pdo = null;
    }
}
echo json_encode($response);