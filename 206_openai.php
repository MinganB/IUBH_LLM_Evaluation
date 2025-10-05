<?php
declare(strict_types=1);

$publicIndexPath = __DIR__ . '/../public/index.html';
if (!file_exists($publicIndexPath)) {
    $indexHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Inventory Data Receiver</title></head>
<body>
<h1>Inventory Data Receiver</h1>
<form method="POST" action="/handlers/process.php">
  <label for="json_input">JSON Input</label><br>
  <textarea id="json_input" name="json_input" rows="10" cols="80" placeholder='{"payload":"<serialized_item>"}'></textarea><br>
  <button type="submit">Submit</button>
</form>
</body>
</html>
HTML;
    file_put_contents($publicIndexPath, $indexHtml);
}

spl_autoload_register(function ($class) {
    $base = __DIR__ . '/../classes/';
    $path = $base . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) {
        require $path;
    }
});

if (!class_exists('InventoryItem')) {
    class InventoryItem {
        public $id;
        public $sku;
        public $name;
        public $quantity;
        public $location;
        public $updated_at;
    }
}

if (!class_exists('InventoryRepository')) {
    class InventoryRepository {
        private $pdo;
        public function __construct(PDO $pdo) { $this->pdo = $pdo; $this->init(); }
        private function init(): void {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS inventory (
                    id TEXT PRIMARY KEY,
                    sku TEXT,
                    name TEXT,
                    quantity INTEGER,
                    location TEXT,
                    updated_at TEXT
                )
            ");
            $this->pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_inventory_sku ON inventory (sku)");
        }
        public function upsert(InventoryItem $item): void {
            $exists = false;
            if (!empty($item->id)) {
                $stmt = $this->pdo->prepare("SELECT id FROM inventory WHERE id = :id");
                $stmt->execute([':id' => $item->id]);
                $exists = $stmt->fetchColumn() !== false;
            }
            if ($exists) {
                $stmt = $this->pdo->prepare("
                    UPDATE inventory
                    SET sku = :sku, name = :name, quantity = :quantity, location = :location, updated_at = :updated_at
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':sku' => $item->sku,
                    ':name' => $item->name,
                    ':quantity' => (int)$item->quantity,
                    ':location' => $item->location,
                    ':updated_at' => $item->updated_at,
                    ':id' => $item->id
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO inventory (id, sku, name, quantity, location, updated_at)
                    VALUES (:id, :sku, :name, :quantity, :location, :updated_at)
                ");
                $stmt->execute([
                    ':id' => $item->id,
                    ':sku' => $item->sku,
                    ':name' => $item->name,
                    ':quantity' => (int)$item->quantity,
                    ':location' => $item->location,
                    ':updated_at' => $item->updated_at
                ]);
            }
        }
    }
}

$inputJson = null;
if (isset($_POST['json_input'])) {
    $inputJson = $_POST['json_input'];
} else {
    $raw = trim(file_get_contents('php://input'));
    if ($raw !== '') { $inputJson = $raw; }
}
if (!$inputJson) {
    http_response_code(400);
    echo 'Missing input';
    exit;
}
$decoded = json_decode($inputJson, true);
if ($decoded === null) {
    http_response_code(400);
    echo 'Invalid JSON';
    exit;
}
$payload = null;
if (is_array($decoded) && isset($decoded['payload'])) {
    $payload = $decoded['payload'];
} elseif (is_string($decoded)) {
    $payload = $decoded;
} else {
    http_response_code(400);
    echo 'Payload not found';
    exit;
}
if (!is_string($payload)) {
    http_response_code(400);
    echo 'Payload must be string';
    exit;
}
try {
    $item = unserialize($payload, ['allowed_classes' => ['InventoryItem']]);
} catch (Throwable $e) {
    http_response_code(400);
    echo 'Unserialization failed';
    exit;
}
if (!($item instanceof InventoryItem)) {
    http_response_code(400);
    echo 'Invalid item object';
    exit;
}

$dbPath = __DIR__ . '/../data/inventory.db';
$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$repo = new InventoryRepository($pdo);
$repo->upsert($item);

echo '<html><body>';
echo '<h2>Inventory Update</h2>';
echo '<p>Processed item SKU: ' . htmlspecialchars($item->sku ?? '') . '</p>';
echo '<p>Quantity: ' . htmlspecialchars($item->quantity ?? '') . '</p>';
echo '</body></html>';
?>