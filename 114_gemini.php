<?php

// config.php
// This file defines the database configuration by retrieving sensitive data
// from environment variables and sets up basic error logging.

$logFile = getenv('APP_LOG_FILE') ?: __DIR__ . '/logs/app_errors.log';

$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    // Attempt to create the log directory if it doesn't exist.
    // Permissions 0755 are generally suitable for directories.
    mkdir($logDir, 0755, true);
}

function logConfigurationError(string $message): void
{
    global $logFile;
    error_log(date('[Y-m-d H:i:s]') . ' Configuration Error: ' . $message . PHP_EOL, 3, $logFile);
}

$databaseConfig = [
    'host' => getenv('DB_HOST'),
    'name' => getenv('DB_NAME'),
    'user' => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD'),
    'port' => getenv('DB_PORT') ?: 3306,
    'charset' => 'utf8mb4'
];

$requiredEnvVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
$missingVars = [];

foreach ($requiredEnvVars as $var) {
    if (empty(getenv($var))) {
        $missingVars[] = $var;
    }
}

if (!empty($missingVars)) {
    $errorMessage = 'Missing required database environment variables: ' . implode(', ', $missingVars);
    logConfigurationError($errorMessage);
    $databaseConfig = false;
}

<?php

// db_connect.php
// This script uses the configuration defined in config.php to establish a secure
// PDO connection to the MySQL database. It handles connection errors gracefully
// by logging them and providing a generic error message to the user.

require_once __DIR__ . '/config.php';

if ($databaseConfig === false) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('An internal error occurred. Please try again later.');
}

try {
    $dsn = "mysql:host={$databaseConfig['host']};port={$databaseConfig['port']};dbname={$databaseConfig['name']};charset={$databaseConfig['charset']}";
    $pdo = new PDO(
        $dsn,
        $databaseConfig['user'],
        $databaseConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;

} catch (PDOException $e) {
    logConfigurationError('Database connection failed: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('An internal error occurred. Please try again later.');
}
?>