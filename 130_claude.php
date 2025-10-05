**.env**

DB_HOST=localhost
DB_USER=blog_user
DB_PASSWORD=secure_password123
DB_NAME=blog_database


**db_connect.php**
<?php
header('Content-Type: application/json');

function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

$response = array();

if (!loadEnv('.env')) {
    $response['success'] = false;
    $response['message'] = 'Failed to load environment file';
    echo json_encode($response);
    exit;
}

$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPassword = getenv('DB_PASSWORD');
$dbName = getenv('DB_NAME');

if (!$dbHost || !$dbUser || !$dbPassword || !$dbName) {
    $response['success'] = false;
    $response['message'] = 'Missing required database environment variables';
    echo json_encode($response);
    exit;
}

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $dbUser, $dbPassword, $options);
    
    $stmt = $pdo->query('SELECT 1');
    if ($stmt !== false) {
        $response['success'] = true;
        $response['message'] = 'Database connection successful';
    } else {
        $response['success'] = false;
        $response['message'] = 'Database connection verification failed';
    }
    
} catch (PDOException $e) {
    $response['success'] = false;
    $response['message'] = 'Database connection failed: ' . $e->getMessage();
}

echo json_encode($response);
?>