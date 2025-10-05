DB_HOST=localhost
DB_USER=your_db_user
DB_PASSWORD=your_db_password
DB_NAME=your_db_name
---
<?php

if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (empty(trim($line)) || strpos(trim($line), '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

try {
    $dbHost = getenv('DB_HOST');
    $dbUser = getenv('DB_USER');
    $dbPass = getenv('DB_PASSWORD');
    $dbName = getenv('DB_NAME');

    if (!$dbHost || !$dbUser || !$dbPass || !$dbName) {
        throw new Exception("One or more database environment variables are missing.");
    }

    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);

    $response = ['success' => true, 'message' => 'Database connection successful.'];

} catch (PDOException $e) {
    $response = ['success' => false, 'message' => 'Database connection failed.'];
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Configuration error: ' . $e->getMessage()];
}

echo json_encode($response);
exit();