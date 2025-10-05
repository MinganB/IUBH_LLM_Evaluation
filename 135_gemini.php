<?php

require __DIR__ . '/vendor/autoload.php';

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    error_log('Configuration Error: .env file not found or unreadable. ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'System configuration error. Please try again later.']);
    exit();
} catch (\Exception $e) {
    error_log('Configuration Error: Unable to load .env file. ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'System configuration error. Please try again later.']);
    exit();
}

$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASSWORD');
$dbName = getenv('DB_NAME');

if (!$dbHost || !$dbUser || !$dbName) {
    error_log('Configuration Error: One or more critical database environment variables are missing (DB_HOST, DB_USER, DB_NAME).');
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'System configuration error. Please try again later.']);
    exit();
}

$dbPass = ($dbPass !== false) ? $dbPass : '';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Successfully connected to the database.']);
} catch (\PDOException $e) {
    error_log('Database Connection Error: ' . $e->getMessage() . ' Code: ' . $e->getCode());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Could not connect to the database. Please try again later.']);
}
exit();
?>