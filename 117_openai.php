<?php
declare(strict_types=1);
function secure_log($message){
  $logDir = __DIR__ . '/../logs';
  if (!is_dir($logDir)) { @mkdir($logDir, 0700, true); }
  $logFile = $logDir . '/config_errors.log';
  $timestamp = date('Y-m-d H:i:s');
  error_log("[$timestamp] ".$message.PHP_EOL, 3, $logFile);
}
$dbConfig = [
  'host' => trim((string) getenv('DB_HOST')),
  'name' => trim((string) getenv('DB_NAME')),
  'user' => trim((string) getenv('DB_USER')),
  'password' => getenv('DB_PASSWORD'),
  'port' => trim((string) getenv('DB_PORT'))
];
$missing = [];
foreach (['host','name','user','password'] as $k) {
  if (!isset($dbConfig[$k]) || $dbConfig[$k] === '') {
    $missing[] = strtoupper('DB_'. $k);
  }
}
if (!empty($missing)) {
  secure_log('Missing required database configuration: '.implode(', ', $missing));
  $CONFIG_READY = false;
} else {
  $CONFIG_READY = true;
  if (empty($dbConfig['port'])) {
    $dbConfig['port'] = 3306;
  } else {
    $dbConfig['port'] = (int) $dbConfig['port'];
  }
}
function getDatabaseConfig(): ?array {
  global $CONFIG_READY;
  global $dbConfig;
  if (!$CONFIG_READY) return null;
  return [
    'host' => $dbConfig['host'],
    'name' => $dbConfig['name'],
    'user' => $dbConfig['user'],
    'password' => $dbConfig['password'],
    'port' => $dbConfig['port']
  ];
}
?> 

<?php
require_once __DIR__ . '/../config/database.php';
$config = getDatabaseConfig();
if ($config === null) {
  http_response_code(500);
  echo 'Cannot connect to database';
  exit;
}
$host = $config['host'];
$dbname = $config['name'];
$user = $config['user'];
$password = $config['password'];
$port = $config['port'] ?? 3306;
$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false
];
try {
  $pdo = new PDO($dsn, $user, $password, $options);
  $stmt = $pdo->query('SELECT 1');
  $stmt->fetchColumn();
  header('Content-Type: application/json');
  echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
  $logDir = __DIR__ . '/../logs';
  if (!is_dir($logDir)) mkdir($logDir, 0700, true);
  $logFile = $logDir . '/db_connect.log';
  $timestamp = date('Y-m-d H:i:s');
  error_log("[$timestamp] Database connection failed: ".$e->getMessage().PHP_EOL, 3, $logFile);
  http_response_code(500);
  echo 'Unable to connect to database';
  exit;
}
?>