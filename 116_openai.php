<?php
declare(strict_types=1);
return [
  'host' => getenv('DB_HOST') ?: '127.0.0.1',
  'port' => (int)(getenv('DB_PORT') ?: 3306),
  'name' => getenv('DB_NAME') ?: 'blog',
  'user' => getenv('DB_USER') ?: 'blog_user',
  'password' => getenv('DB_PASSWORD') ?: '',
  'charset' => 'utf8mb4',
  'options' => [
     PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES => false,
  ],
];<?php
declare(strict_types=1);
class DatabaseConnection {
  private $pdo;
  public function __construct() {
     $config = include __DIR__ . '/../config/database.php';
     $dsn = 'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['name'] . ';charset=' . $config['charset'];
     $this->pdo = new PDO($dsn, $config['user'], $config['password'], $config['options']);
  }
  public function getPdo(): PDO {
     return $this->pdo;
  }
}<?php
require_once __DIR__ . '/../handlers/DatabaseConnection.php';
try {
   $db = new DatabaseConnection();
   $pdo = $db->getPdo();
   $stmt = $pdo->query('SELECT 1');
   $stmt->fetchColumn();
   http_response_code(200);
   echo 'DB_CONNECTION_SUCCESS';
} catch (PDOException $e) {
   http_response_code(500);
   echo 'DB_CONNECTION_ERROR';
}
?>