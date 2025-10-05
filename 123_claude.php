config.php
<?php
define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '');
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '');
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '');

function validateConfig() {
    $required = ['DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_NAME'];
    $missing = [];
    
    foreach ($required as $constant) {
        if (!defined($constant) || empty(constant($constant))) {
            $missing[] = $constant;
        }
    }
    
    if (!empty($missing)) {
        error_log('Configuration Error: Missing required database configuration constants: ' . implode(', ', $missing), 3, '/var/log/app_errors.log');
        return false;
    }
    
    return true;
}

if (!validateConfig()) {
    http_response_code(500);
    die('Application configuration error. Please contact administrator.');
}
?>


db_connect.php
<?php
require_once 'config.php';

function getDatabaseConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        return $pdo;
        
    } catch (PDOException $e) {
        error_log('Database Connection Error: ' . $e->getMessage(), 3, '/var/log/app_errors.log');
        http_response_code(500);
        die('Database connection failed. Please contact administrator.');
    }
}

$db = getDatabaseConnection();
?>