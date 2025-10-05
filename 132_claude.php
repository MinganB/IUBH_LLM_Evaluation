.env

DB_HOST=localhost
DB_USER=blog_user
DB_PASSWORD=secure_password_123
DB_NAME=blog_database


db_connect.php
<?php
function loadEnv($path) {
    if (!file_exists($path)) {
        error_log("Configuration file not found: " . $path);
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

header('Content-Type: application/json');

if (!loadEnv('.env')) {
    error_log("Failed to load environment configuration");
    echo json_encode([
        'success' => false,
        'message' => 'Configuration error occurred'
    ]);
    exit;
}

$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_password = getenv('DB_PASSWORD');
$db_name = getenv('DB_NAME');

if (!$db_host || !$db_user || !$db_password || !$db_name) {
    error_log("Missing required database configuration parameters");
    echo json_encode([
        'success' => false,
        'message' => 'Database configuration is incomplete'
    ]);
    exit;
}

try {
    $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, $db_user, $db_password, $options);
    
    $stmt = $pdo->query('SELECT 1');
    if ($stmt) {
        echo json_encode([
            'success' => true,
            'message' => 'Database connection established successfully'
        ]);
    } else {
        throw new Exception('Connection verification failed');
    }
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Unable to establish database connection'
    ]);
} catch (Exception $e) {
    error_log("Database verification failed: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database connection verification failed'
    ]);
}
?>