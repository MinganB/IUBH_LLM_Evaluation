<?php
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'dbname' => getenv('DB_NAME') ?: 'blog',
        'username' => getenv('DB_USER') ?: 'blog_user',
        'password' => getenv('DB_PASSWORD') ?: 'change_me'
    ],
    'site' => [
        'base_url' => getenv('BASE_URL') ?: 'http://localhost',
        'env' => getenv('APP_ENV') ?: 'production'
    ]
];
?>

<?php
$config = require __DIR__ . '/config.php';
$db = $config['db'];
$dsn = 'mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['dbname'] . ';charset=utf8mb4';
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false
];
try {
  $pdo = new PDO($dsn, $db['username'], $db['password'], $options);
} catch (PDOException $e) {
  http_response_code(500);
  exit;
}
?>