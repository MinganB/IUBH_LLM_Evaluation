<?php
class Database {
  private static $instance = null;
  private $pdo;

  private function __construct() {
    $dsn = getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=inventory';
    $user = getenv('DB_USER') ?: 'inventory_user';
    $pass = getenv('DB_PASSWORD') ?: '';
    $options = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_PERSISTENT => true
    ];
    $this->pdo = new PDO($dsn, $user, $pass, $options);
  }

  public static function getInstance() {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance->pdo;
  }
}
?><?php
class Logger {
  private static $logFile;
  public static function init($path) {
    self::$logFile = $path;
    $dir = dirname($path);
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }
  }
  public static function log($level, $message, $context = []) {
    if (!self::$logFile) return;
    $ts = date('Y-m-d H:i:s');
    $ctx = json_encode($context);
    $line = "[$ts] [$level] $message $ctx";
    file_put_contents(self::$logFile, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
  }
}
?><?php
class SafeDeserializer {
  public static function deserialize(string $serialized) {
    if (stripos($serialized, 'O:') !== false) {
      throw new \Exception('Object deserialization is not allowed');
    }
    $data = @unserialize($serialized);
    if ($data === false && $serialized !== 'b:0;' && $serialized !== 'b:1;' && $serialized !== '' ) {
      throw new \Exception('Deserialization failed');
    }
    if (!is_array($data)) {
      throw new \Exception('Invalid data structure');
    }
    if (!isset($data['sku'], $data['name'], $data['quantity'])) {
      throw new \Exception('Missing required item fields');
    }
    $item = new stdClass();
    $item->sku = (string)$data['sku'];
    $item->name = (string)$data['name'];
    $item->quantity = (int)$data['quantity'];
    return $item;
  }
}
?><?php
class InventoryUpdater {
  private $pdo;
  public function __construct($pdo) {
    $this->pdo = $pdo;
  }
  public function saveItem($item) {
    $sql = "INSERT INTO inventory (sku, name, quantity) VALUES (:sku, :name, :qty)
            ON DUPLICATE KEY UPDATE name = :name2, quantity = :qty2";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':sku' => $item->sku,
      ':name' => $item->name,
      ':qty' => $item->quantity,
      ':name2' => $item->name,
      ':qty2' => $item->quantity
    ]);
  }
}
?><?php
$root = dirname(__DIR__);
require_once $root.'/classes/Database.php';
require_once $root.'/handlers/SafeDeserializer.php';
require_once $root.'/handlers/InventoryUpdater.php';
require_once $root.'/handlers/Logger.php';

$logDir = $root.'/logs';
Logger::init($logDir.'/inventory_processor.log');
$source = $_SERVER['REMOTE_ADDR'] ?? 'CLI';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  header('Content-Type: text/html');
  echo '<!DOCTYPE html><html><body>';
  echo '<form method="POST" action="process.php">';
  echo '<textarea name="payload" rows="8" cols="100" placeholder="Paste JSON string here.."></textarea><br />';
  echo '<input type="submit" value="Submit" />';
  echo '</form>';
  echo '</body></html>';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  try {
    if (!isset($_POST['payload'])) {
      throw new \Exception('Payload missing');
    }
    $payloadJson = $_POST['payload'];
    $payload = json_decode($payloadJson, true);
    if (!is_array($payload) || !isset($payload['serialized']) || !is_string($payload['serialized'])) {
      throw new \Exception('Invalid payload structure');
    }
    $serialized = $payload['serialized'];
    $item = SafeDeserializer::deserialize($serialized);
    $db = Database::getInstance();
    $updater = new InventoryUpdater($db);
    $updater->saveItem($item);
    Logger::log('INFO','Processed inventory item', ['source'=>$source, 'sku'=>$item->sku, 'name'=>$item->name, 'quantity'=>$item->quantity]);
    echo json_encode(['status'=>'success','sku'=>$item->sku,'name'=>$item->name,'quantity'=>$item->quantity]);
    exit;
  } catch (Exception $e) {
    Logger::log('ERROR','Processing failed', ['source'=>$source, 'error'=>$e->getMessage()]);
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Invalid data']);
    exit;
  }
}

http_response_code(405);
echo json_encode(['status'=>'error','message'=>'Method not allowed']);
?>