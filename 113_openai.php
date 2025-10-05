<?php
define('DB_HOST', (string)(getenv('DB_HOST') ?: '127.0.0.1'));
define('DB_PORT', (string)(getenv('DB_PORT') ?: '3306'));
define('DB_NAME', (string)(getenv('DB_NAME') ?: 'blogdb'));
define('DB_USER', (string)(getenv('DB_USER') ?: 'bloguser'));
define('DB_PASSWORD', (string)(getenv('DB_PASSWORD') ?: ''));
define('DB_CHARSET', 'utf8mb4');
?> 
<?php
require_once __DIR__ . '/config.php';
try {
  $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
  $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);
} catch (PDOException $e) {
  http_response_code(500);
  exit;
}
?>