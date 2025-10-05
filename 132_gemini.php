DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=your_database
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Dotenv\Exception\ValidationException;

header('Content-Type: application/json');

try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $dotenv->required(['DB_HOST', 'DB_USER', 'DB_PASSWORD', 'DB_NAME'])->notEmpty();

    $dbHost = getenv('DB_HOST');
    $dbUser = getenv('DB_USER');
    $dbPass = getenv('DB_PASSWORD');
    $dbName = getenv('DB_NAME');

    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);

    echo json_encode([
        'success' => true,
        'message' => 'Successfully connected to the database.'
    ]);
    exit();

} catch (ValidationException $e) {
    error_log("Configuration error: Missing or empty environment variable. Details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Configuration error: One or more required settings are missing or invalid.'
    ]);
    exit();
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to connect to the database. Please try again later.'
    ]);
    exit();
} catch (Exception $e) {
    error_log("An unexpected error occurred: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
    exit();
}