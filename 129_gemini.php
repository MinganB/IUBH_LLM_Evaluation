env
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=your_database

<?php

header('Content-Type: application/json');

require __DIR__ . '/vendor/autoload.php';

function logError($message) {
    $logFile = __DIR__ . '/application_error.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] ERROR: {$message}" . PHP_EOL, FILE_APPEND);
}

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
    logError("Dotenv: .env file not found or unreadable. Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Configuration error.']);
    exit();
} catch (Exception $e) {
    logError("Dotenv: Failed to load .env file. Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Configuration error.']);
    exit();
}

$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASSWORD');
$dbName = getenv('DB_NAME');

if (!$dbHost || !$dbUser || !$dbPass || !$dbName) {
    logError("Missing one or more database environment variables (DB_HOST, DB_USER, DB_PASSWORD, DB_NAME).");
    echo json_encode(['success' => false, 'message' => 'Configuration error.']);
    exit();
}

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    echo json_encode(['success' => true, 'message' => 'Database connection successful.']);
} catch (PDOException $e) {
    logError("Database connection failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}