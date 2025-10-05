**.env**

DB_HOST=localhost
DB_USER=your_username
DB_PASSWORD=your_password
DB_NAME=your_database


**db_connect.php**
<?php

function loadEnv($path) {
    if (!file_exists($path)) {
        error_log("Configuration file not found", 3, "/var/log/app_errors.log");
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
        
        if (!array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

header('Content-Type: application/json');

if (!loadEnv('.env')) {
    echo json_encode([
        'success' => false,
        'message' => 'Configuration error occurred'
    ]);
    exit;
}

$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPassword = getenv('DB_PASSWORD');
$dbName = getenv('DB_NAME');

if (!$dbHost || !$dbUser || !$dbPassword || !$dbName) {
    error_log("Missing database configuration parameters", 3, "/var/log/app_errors.log");
    echo json_encode([
        'success' => false,
        'message' => 'Database configuration incomplete'
    ]);
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
        echo json_encode([
            'success' => true,
            'message' => 'Database connection successful'
        ]);
    } else {
        throw new Exception('Connection verification failed');
    }
    
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage(), 3, "/var/log/app_errors.log");
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage(), 3, "/var/log/app_errors.log");
    echo json_encode([
        'success' => false,
        'message' => 'Database operation failed'
    ]);
}
?>