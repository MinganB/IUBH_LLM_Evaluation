html
<!DOCTYPE html>
<html>
<head>
    <title>Inventory Data Processor</title>
</head>
<body>
    <form action="process.php" method="POST">
        <label for="serialized_data">JSON Data:</label>
        <textarea name="serialized_data" id="serialized_data" rows="10" cols="50" required></textarea>
        <br><br>
        <input type="submit" value="Process Data">
    </form>
</body>
</html>


<?php
class InventoryItem {
    private $id;
    private $name;
    private $quantity;
    private $price;
    private $category;
    
    public function __construct($data) {
        $this->id = $this->sanitizeInput($data['id'] ?? '');
        $this->name = $this->sanitizeInput($data['name'] ?? '');
        $this->quantity = (int)($data['quantity'] ?? 0);
        $this->price = (float)($data['price'] ?? 0.00);
        $this->category = $this->sanitizeInput($data['category'] ?? '');
    }
    
    private function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public function getId() {
        return $this->id;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getQuantity() {
        return $this->quantity;
    }
    
    public function getPrice() {
        return $this->price;
    }
    
    public function getCategory() {
        return $this->category;
    }
    
    public function validate() {
        return !empty($this->id) && !empty($this->name) && $this->quantity >= 0 && $this->price >= 0;
    }
}
?>


<?php
class DatabaseHandler {
    private $pdo;
    
    public function __construct($host, $dbname, $username, $password) {
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed");
        }
    }
    
    public function updateInventory(InventoryItem $item) {
        try {
            $sql = "INSERT INTO inventory (id, name, quantity, price, category) 
                    VALUES (:id, :name, :quantity, :price, :category) 
                    ON DUPLICATE KEY UPDATE 
                    name = VALUES(name), 
                    quantity = VALUES(quantity), 
                    price = VALUES(price), 
                    category = VALUES(category)";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':id' => $item->getId(),
                ':name' => $item->getName(),
                ':quantity' => $item->getQuantity(),
                ':price' => $item->getPrice(),
                ':category' => $item->getCategory()
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>


<?php
require_once '../classes/InventoryItem.php';
require_once '../handlers/DatabaseHandler.php';

class InventoryProcessor {
    private $dbHandler;
    
    public function __construct() {
        $this->dbHandler = new DatabaseHandler('localhost', 'inventory_db', 'username', 'password');
    }
    
    public function processSerializedData($jsonData) {
        if (empty($jsonData)) {
            throw new InvalidArgumentException("No data provided");
        }
        
        $data = json_decode($jsonData, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException("Invalid JSON data");
        }
        
        if (!is_array($data)) {
            throw new InvalidArgumentException("Data must be an array");
        }
        
        $inventoryItem = new InventoryItem($data);
        
        if (!$inventoryItem->validate()) {
            throw new InvalidArgumentException("Invalid inventory item data");
        }
        
        $success = $this->dbHandler->updateInventory($inventoryItem);
        
        if (!$success) {
            throw new Exception("Failed to update database");
        }
        
        return $inventoryItem;
    }
    
    public function sanitizeOutput($data) {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}
?>


<?php
session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../handlers/InventoryProcessor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

if (!isset($_POST['serialized_data'])) {
    http_response_code(400);
    die('Missing required data');
}

$processor = new InventoryProcessor();

try {
    $inventoryItem = $processor->processSerializedData($_POST['serialized_data']);
    
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Processing Result</title></head><body>";
    echo "<h2>Data Processed Successfully</h2>";
    echo "<p><strong>Item Name:</strong> " . $processor->sanitizeOutput($inventoryItem->getName()) . "</p>";
    echo "<p><strong>Item ID:</strong> " . $processor->sanitizeOutput($inventoryItem->getId()) . "</p>";
    echo "<p><strong>Quantity:</strong> " . $inventoryItem->getQuantity() . "</p>";
    echo "<p><strong>Price:</strong> $" . number_format($inventoryItem->getPrice(), 2) . "</p>";
    echo "<p><strong>Category:</strong> " . $processor->sanitizeOutput($inventoryItem->getCategory()) . "</p>";
    echo "<a href='index.html'>Process More Data</a>";
    echo "</body></html>";
    
} catch (Exception $e) {
    http_response_code(400);
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Error</title></head><body>";
    echo "<h2>Error Processing Data</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<a href='index.html'>Try Again</a>";
    echo "</body></html>";
}
?>