DB_HOST=localhost
DB_USER=root
DB_PASSWORD=secret
DB_NAME=my_database
<?php
header('Content-Type: application/json');
$envPath = __DIR__ . '/.env';
function loadEnv($path) {
    if (!is_readable($path)) return false;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        $eqPos = strpos($line, '=');
        if ($eqPos === false) continue;
        $key = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));
        if ((strlen($value) > 1) && (($value[0] === '"' && $value[strlen($value) - 1] === '"') || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        $value = str_replace('\\n', PHP_EOL, $value);
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    return true;
}
loadEnv($envPath);
$required = ['DB_HOST','DB_USER','DB_PASSWORD','DB_NAME'];
foreach ($required as $var) {
    if (getenv($var) === false) {
        echo json_encode(['success'=>false,'message'=>"Environment variable $var is not set."]);
        exit;
    }
}
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbname = getenv('DB_NAME');
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $stmt = $pdo->query('SELECT 1');
    $stmt->fetchColumn();
    echo json_encode(['success'=>true,'message'=>'Database connection successful.']);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Database connection failed: ' . $e->getMessage()]);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'Database connection failed: ' . $e->getMessage()]);
}