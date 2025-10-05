<?php
session_start();
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html>
<head>
<title>Inventory Serialised Data Ingest</title>
</head>
<body>
<h1>Inventory Serialised Data Ingest</h1>
<form action="/handlers/processSerializedData.php" method="post" autocomplete="off">
<textarea name="payload" rows="12" cols="90" placeholder="Insert PHP serialized payload here"></textarea><br>
<input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
<input type="submit" value="Process Payload">
</form>
</body>
</html>


<?php
session_start();
$base = dirname(__DIR__);
require_once $base . '/classes/InventoryManager.php';
require_once $base . '/classes/InventoryItem.php';
$payload = $_POST['payload'] ?? null;
$token = $_POST['token'] ?? null;
$csrf = $_SESSION['csrf_token'] ?? '';
if (!$payload) {
  echo "<!DOCTYPE html><html><body><h2>Error</h2><p>No payload provided</p></body></html>";
  exit;
}
if ($csrf && $token !== $csrf) {
  echo "<!DOCTYPE html><html><body><h2>Error</h2><p>Invalid CSRF token</p></body></html>";
  exit;
}
$data = @unserialize($payload);
if ($data === false && $payload !== serialize(false)) {
  echo "<!DOCTYPE html><html><body><h2>Error</h2><p>Failed to unserialize payload</p></body></html>";
  exit;
}
$manager = InventoryManager::bootstrap();
$result = $manager->updateInventoryFromPayload($data);
$items = $manager->getAllItems();
?><!DOCTYPE html><html><head><title>Serialized Data Processing</title></head><body><h1>Serialized Data Processing Result</h1><h2>Payload Summary</h2><pre><?php echo htmlspecialchars(print_r($data, true)); ?></pre><h2>Update Result</h2><pre><?php echo htmlspecialchars(print_r($result, true)); ?></pre><h2>Current Inventory</h2><table border="1" cellpadding="4" cellspacing="0"><tr><th>ID</th><th>SKU</th><th>Name</th><th>Quantity</th><th>Location</th><th>Price</th></tr><?php foreach ($items as $row) { ?><tr><td><?php echo htmlspecialchars((string)$row['id']); ?></td><td><?php echo htmlspecialchars($row['sku']); ?></td><td><?php echo htmlspecialchars($row['name']); ?></td><td><?php echo htmlspecialchars((string)$row['quantity']); ?></td><td><?php echo htmlspecialchars($row['location']); ?></td><td><?php echo htmlspecialchars((string)$row['price']); ?></td></tr><?php } ?></table></body></html>


<?php
class InventoryItem {
  public $id;
  public $sku;
  public $name;
  public $quantity;
  public $location;
  public $price;

  public function __construct($id = null, $sku = null, $name = null, $quantity = 0, $location = null, $price = 0.0) {
    $this->id = $id;
    $this->sku = $sku;
    $this->name = $name;
    $this->quantity = $quantity;
    $this->location = $location;
    $this->price = $price;
  }
}


<?php
class InventoryManager {
  private $db;
  public function __construct(PDO $db) { $this->db = $db; }
  public static function createConnection(): PDO {
    $dsn = getenv('DB_DSN');
    if ($dsn) {
      return new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    $driver = getenv('DB_DRIVER') ?: 'mysql';
    if ($driver === 'sqlite') {
      $path = getenv('DB_PATH') ?: __DIR__ . '/../../data/inventory.db';
      return new PDO("sqlite:$path", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $dbname = getenv('DB_NAME') ?: 'inventory';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    return new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  }
  public static function bootstrap(): InventoryManager {
    $db = self::createConnection();
    $db->exec("
      CREATE TABLE IF NOT EXISTS inventory (
        id INT PRIMARY KEY,
        sku VARCHAR(64),
        name VARCHAR(128),
        quantity INT,
        location VARCHAR(64),
        price DECIMAL(10,2)
      )
    ");
    return new self($db);
  }
  public function upsertItem(array $item): array {
    if (!isset($item['id']) && !isset($item['sku'])) {
      return ['success'=>false,'id'=>null,'message'=>'Missing identifier'];
    }
    $id = $item['id'] ?? null;
    $sku = $item['sku'] ?? null;
    $name = $item['name'] ?? null;
    $qty = isset($item['quantity']) ? (int)$item['quantity'] : 0;
    $location = $item['location'] ?? null;
    $price = isset($item['price']) ? (float)$item['price'] : 0.0;
    if ($id !== null) {
      $stmt = $this->db->prepare("SELECT COUNT(*) FROM inventory WHERE id = ?");
      $stmt->execute([$id]);
      $exists = $stmt->fetchColumn() > 0;
      if ($exists) {
        $stmt = $this->db->prepare("UPDATE inventory SET sku = ?, name = ?, quantity = ?, location = ?, price = ? WHERE id = ?");
        $stmt->execute([$sku, $name, $qty, $location, $price, $id]);
        return ['success'=>true,'id'=>$id,'updated'=>true];
      }
    }
    $stmt = $this->db->prepare("INSERT INTO inventory (sku,name,quantity,location,price) VALUES (?,?,?,?,?)");
    $stmt->execute([$sku, $name, $qty, $location, $price]);
    $newId = (int)$this->db->lastInsertId();
    return ['success'=>true,'id'=>$newId,'inserted'=>true];
  }
  public function updateInventoryFromPayload(array $payload): array {
    if (!isset($payload['items']) || !is_array($payload['items'])) {
      return ['success'=>false,'message'=>'Invalid payload structure'];
    }
    $results = [];
    foreach ($payload['items'] as $item) {
      if (!is_array($item)) { continue; }
      $results[] = $this->upsertItem($item);
    }
    return ['success'=>true,'count'=>count($results),'results'=>$results];
  }
  public function getAllItems(): array {
    $stmt = $this->db->query("SELECT * FROM inventory");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
?>