<?php
return [
  'db' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'blog_db',
    'user' => 'blog_user',
    'password' => 'CHANGE_ME',
    'charset' => 'utf8mb4'
  ]
]; 

<?php
$config = require __DIR__ . '/../config/db_config.php';
$host = $config['db']['host'];
$port = $config['db']['port'];
$dbName = $config['db']['name'];
$user = $config['db']['user'];
$password = $config['db']['password'];
$charset = $config['db']['charset'];
$dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false
];
try {
  $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
  http_response_code(500);
  exit;
}
return $pdo;
?>