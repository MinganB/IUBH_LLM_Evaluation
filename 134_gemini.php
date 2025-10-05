.env
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_database_password
DB_NAME=blog_db

db_connect.php
<?php

header('Content-Type: application/json');

// Path to Composer's autoloader relative to this script
require_once __DIR__ . '/vendor/autoload.php';

try {
    // Load the .env file from the project root directory
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Configuration file (.env) not found or unreadable. Please ensure it exists in the project root.'
    ]);
    exit();
} catch (Exception $e) {
    // Catch any other exceptions during dotenv loading
    echo json_encode([
        'success' => false,
        'message' => 'Error loading environment variables: ' . $e->getMessage()
    ]);
    exit();
}

// Retrieve database credentials from environment variables
$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASSWORD');
$dbName = getenv('DB_NAME');

// Validate that environment variables are set
if (!$dbHost || !$dbUser || !$dbName) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing one or more database environment variables (DB_HOST, DB_USER, DB_NAME). Please check your .env file.'
    ]);
    exit();
}

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful.'
    ]);
} catch (PDOException $e) {
    // In a production environment, avoid exposing raw PDO error messages.
    // Log the error for debugging, but return a generic message to the client.
    error_log('Database connection failed: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Unable to connect to the database. Please check your configuration.'
    ]);
}

exit();