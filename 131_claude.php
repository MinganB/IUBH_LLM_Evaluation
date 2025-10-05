**.env**

DB_HOST=localhost
DB_USER=blog_user
DB_PASSWORD=secure_password_123
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

try {
    if (!loadEnv('.env')) {
        throw new Exception('Unable to load environment file');
    }
    
    $host = getenv('DB_HOST');
    $user = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');
    $database = getenv('DB_NAME');
    
    if (!$host || !$user || !$database) {
        throw new Exception('Missing required database configuration');
    }
    
    $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $user, $password, $options);
    
    $pdo->query('SELECT 1');
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
}
?>