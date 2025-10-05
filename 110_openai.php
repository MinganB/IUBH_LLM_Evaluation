<?php
return [
  'db_host' => 'localhost',
  'db_port' => 3306,
  'db_name' => 'my_database',
  'db_user' => 'db_user',
  'db_password' => 'strong_password_here',
  'db_charset' => 'utf8mb4',
  'pdo_options' => [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
  ]
];
<?php
$configPath = __DIR__ . '/../config.php';
if (!is_file($configPath)) {
  $configPath = __DIR__ . '/config.php';
}
$dbConfig = require $configPath;
$envMap = [
  'db_host' => 'DB_HOST',
  'db_port' => 'DB_PORT',
  'db_name' => 'DB_NAME',
  'db_user' => 'DB_USER',
  'db_password' => 'DB_PASSWORD',
  'db_charset' => 'DB_CHARSET'
];
foreach ($envMap as $key => $envKey) {
  $val = getenv($envKey);
  if ($val !== false && $val !== '') {
    $dbConfig[$key] = $val;
  }
}
$required = ['db_host','db_port','db_name','db_user','db_password','db_charset','pdo_options'];
foreach ($required as $k) {
  if (!array_key_exists($k, $dbConfig)) {
    http_response_code(500);
    exit;
  }
}
$dsn = 'mysql:host=' . $dbConfig['db_host'] . ';port=' . (int)$dbConfig['db_port'] . ';dbname=' . $dbConfig['db_name'] . ';charset=' . $dbConfig['db_charset'];
try {
  $pdo = new PDO($dsn, $dbConfig['db_user'], $dbConfig['db_password'], $dbConfig['pdo_options']);
} catch (PDOException $e) {
  error_log('Database connection failed: ' . $e->getMessage());
  http_response_code(500);
  exit;
}
?>