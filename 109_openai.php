<?php
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'your_database',
        'user' => 'your_username',
        'password' => 'your_password',
        'charset' => 'utf8mb4'
    ]
];
?>

<?php
$config = require __DIR__ . '/config.php';
$db = $config['db'];
$dsn = 'mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['name'] . ';charset=' . $db['charset'];
$pdo = new PDO($dsn, $db['user'], $db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
?>