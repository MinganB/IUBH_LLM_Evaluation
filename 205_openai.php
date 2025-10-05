<?php
$publicDir = __DIR__;
$indexPath = $publicDir.'/index.html';
if (!file_exists($indexPath)) {
    $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Inventory Data Submission</title></head><body>';
    $html .= '<h1>Submit Inventory Data</h1>';
    $html .= '<form method="POST" action="process.php">';
    $html .= '<label for="payload">JSON Payload</label><br>';
    $html .= '<textarea id="payload" name="payload" rows="10" cols="80" placeholder=\'{"id":1,"name":"Widget","quantity":5,"price":9.99}\'></textarea><br>';
    $html .= '<button type="submit">Submit</button>';
    $html .= '</form>';
    $html .= '<p>Submit a JSON-encoded string representing an inventory item. If id is provided, it will be used to update; otherwise a new item will be created.</p>';
    $html .= '</body></html>';
    file_put_contents($indexPath, $html);
}

class InventoryItem {
    public $id;
    public $name;
    public $quantity;
    public $price;
    public function __construct($data = []) {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->quantity = $data['quantity'] ?? 0;
        $this->price = $data['price'] ?? 0;
    }
}
class Database {
    private static $pdo = null;
    public static function getConnection() {
        if (self::$pdo !== null) return self::$pdo;
        $dbPath = dirname(__DIR__).'/data/inventory.db';
        if (!is_dir(dirname($dbPath))) {
            mkdir(dirname($dbPath), 0777, true);
        }
        $dsn = 'sqlite:' . $dbPath;
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE IF NOT EXISTS items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            quantity INTEGER NOT NULL,
            price REAL
        )');
        self::$pdo = $pdo;
        return self::$pdo;
    }
}
class InventoryHandler {
    public function upsert(InventoryItem $item) {
        $pdo = Database::getConnection();
        if ($item->id !== null) {
            $stmt = $pdo->prepare('SELECT id FROM items WHERE id = ?');
            $stmt->execute([$item->id]);
            $exists = $stmt->fetchColumn();
            if ($exists) {
                $upd = $pdo->prepare('UPDATE items SET name = ?, quantity = ?, price = ? WHERE id = ?');
                $upd->execute([$item->name, $item->quantity, $item->price, $item->id]);
                return (int)$item->id;
            } else {
                $ins = $pdo->prepare('INSERT INTO items (id, name, quantity, price) VALUES (?, ?, ?, ?)');
                $ins->execute([$item->id, $item->name, $item->quantity, $item->price]);
                return (int) $pdo->lastInsertId();
            }
        } else {
            $ins = $pdo->prepare('INSERT INTO items (name, quantity, price) VALUES (?, ?, ?)');
            $ins->execute([$item->name, $item->quantity, $item->price]);
            return (int) $pdo->lastInsertId();
        }
    }
}

// Process POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payload'])) {
    $payload = $_POST['payload'];
    $data = null;
    $decoded = json_decode($payload, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $data = $decoded;
    } else {
        $unser = @unserialize($payload);
        if ($unser !== false) {
            if (is_array($unser)) {
                $data = $unser;
            } elseif (is_object($unser)) {
                $data = get_object_vars($unser);
            }
        }
    }
    if ($data === null) {
        echo "Invalid payload";
        exit;
    }
    $id = $data['id'] ?? null;
    $name = $data['name'] ?? $data['itemName'] ?? null;
    $quantity = $data['quantity'] ?? 0;
    $price = $data['price'] ?? 0;
    $item = new InventoryItem(['id'=>$id,'name'=>$name,'quantity'=>$quantity,'price'=>$price]);
    $handler = new InventoryHandler();
    $updatedId = $handler->upsert($item);
    if ($name !== null) {
        echo 'Item Name: '.htmlspecialchars($name);
    } else {
        echo 'Item processed';
    }
    exit;
}
?>