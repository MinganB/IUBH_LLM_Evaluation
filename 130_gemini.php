DB_HOST=localhost
DB_USER=root
DB_PASSWORD=
DB_NAME=blog_db
<?php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

header('Content-Type: application/json');

try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $dbHost = getenv('DB_HOST');
    $dbUser = getenv('DB_USER');
    $dbPass = getenv('DB_PASSWORD');
    $dbName = getenv('DB_NAME');

    if (!$dbHost || !$dbUser || !$dbName) {
        throw new Exception("Missing one or more database environment variables (DB_HOST, DB_USER, DB_NAME).");
    }

    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);

    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
}

exit;