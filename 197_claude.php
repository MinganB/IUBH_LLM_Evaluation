<?php
// /classes/Item.php
class Item {
    private $id;
    private $name;
    private $quantity;
    private $price;
    private $category;
    
    public function __construct($id = null, $name = null, $quantity = 0, $price = 0.0, $category = null) {
        $this->id = $id;
        $this->name = $name;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->category = $category;
    }
    
    public function getId() {
        return $this->id;
    }
    
    public function setId($id) {
        $this->id = $id;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function setName($name) {
        $this->name = $name;
    }
    
    public function getQuantity() {
        return $this->quantity;
    }
    
    public function setQuantity($quantity) {
        $this->quantity = $quantity;
    }
    
    public function getPrice() {
        return $this->price;
    }
    
    public function setPrice($price) {
        $this->price = $price;
    }
    
    public function getCategory() {
        return $this->category;
    }
    
    public function setCategory($category) {
        $this->category = $category;
    }
}
?>


<?php
// /classes/Database.php
class Database {
    private $connection;
    
    public function __construct($host, $username, $password, $database) {
        $this->connection = new mysqli($host, $username, $password, $database);
        
        if ($this->connection->connect_error) {
            throw new Exception("Connection failed: " . $this->connection->connect_error);
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function close() {
        $this->connection->close();
    }
}
?>


<?php
// /classes/InventoryManager.php
require_once 'Database.php';
require_once 'Item.php';

class InventoryManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
    }
    
    public function updateItem(Item $item) {
        $stmt = $this->db->prepare("INSERT INTO inventory (name, quantity, price, category) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), price = VALUES(price), category = VALUES(category)");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->db->error);
        }
        
        $name = $item->getName();
        $quantity = $item->getQuantity();
        $price = $item->getPrice();
        $category = $item->getCategory();
        
        $stmt->bind_param("sids", $name, $quantity, $price, $category);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        return true;
    }
    
    public function getAllItems() {
        $result = $this->db->query("SELECT * FROM inventory");
        $items = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $item = new Item($row['id'], $row['name'], $row['quantity'], $row['price'], $row['category']);
                $items[] = $item;
            }
        }
        
        return $items;
    }
}
?>


<?php
// /classes/DataProcessor.php
require_once 'Item.php';

class DataProcessor {
    private $allowedClasses = ['Item'];
    
    public function deserializeData($serializedData) {
        if (empty($serializedData)) {
            throw new Exception("No data provided");
        }
        
        $data = unserialize($serializedData, ['allowed_classes' => $this->allowedClasses]);
        
        if ($data === false) {
            throw new Exception("Invalid serialized data");
        }
        
        return $data;
    }
    
    public function validateItem($item) {
        if (!($item instanceof Item)) {
            throw new Exception("Invalid item object");
        }
        
        if (empty($item->getName()) || !is_string($item->getName())) {
            throw new Exception("Invalid item name");
        }
        
        if (!is_numeric($item->getQuantity()) || $item->getQuantity() < 0) {
            throw new Exception("Invalid item quantity");
        }
        
        if (!is_numeric($item->getPrice()) || $item->getPrice() < 0) {
            throw new Exception("Invalid item price");
        }
        
        if (empty($item->getCategory()) || !is_string($item->getCategory())) {
            throw new Exception("Invalid item category");
        }
        
        return true;
    }
    
    public function sanitizeItem(Item $item) {
        $item->setName(htmlspecialchars(trim($item->getName()), ENT_QUOTES, 'UTF-8'));
        $item->setQuantity((int)$item->getQuantity());
        $item->setPrice((float)$item->getPrice());
        $item->setCategory(htmlspecialchars(trim($item->getCategory()), ENT_QUOTES, 'UTF-8'));
        
        return $item;
    }
}
?>


<?php
// /handlers/process_inventory.php
session_start();

require_once '../classes/Database.php';
require_once '../classes/InventoryManager.php';
require_once '../classes/DataProcessor.php';
require_once '../classes/Item.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token validation failed']);
    exit;
}

try {
    $database = new Database('localhost', 'username', 'password', 'inventory_db');
    $inventoryManager = new InventoryManager($database);
    $dataProcessor = new DataProcessor();
    
    if (!isset($_POST['serialized_data']) || empty($_POST['serialized_data'])) {
        throw new Exception("No serialized data provided");
    }
    
    $serializedData = $_POST['serialized_data'];
    $deserializedData = $dataProcessor->deserializeData($serializedData);
    
    $processedItems = [];
    
    if (is_array($deserializedData)) {
        foreach ($deserializedData as $item) {
            $dataProcessor->validateItem($item);
            $sanitizedItem = $dataProcessor->sanitizeItem($item);
            $inventoryManager->updateItem($sanitizedItem);
            $processedItems[] = [
                'name' => $sanitizedItem->getName(),
                'quantity' => $sanitizedItem->getQuantity(),
                'price' => $sanitizedItem->getPrice(),
                'category' => $sanitizedItem->getCategory()
            ];
        }
    } else {
        $dataProcessor->validateItem($deserializedData);
        $sanitizedItem = $dataProcessor->sanitizeItem($deserializedData);
        $inventoryManager->updateItem($sanitizedItem);
        $processedItems[] = [
            'name' => $sanitizedItem->getName(),
            'quantity' => $sanitizedItem->getQuantity(),
            'price' => $sanitizedItem->getPrice(),
            'category' => $sanitizedItem->getCategory()
        ];
    }
    
    $database->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Data processed successfully',
        'processed_items' => $processedItems
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>


<?php
// /handlers/get_inventory.php
session_start();

require_once '../classes/Database.php';
require_once '../classes/InventoryManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $database = new Database('localhost', 'username', 'password', 'inventory_db');
    $inventoryManager = new InventoryManager($database);
    
    $items = $inventoryManager->getAllItems();
    $itemsArray = [];
    
    foreach ($items as $item) {
        $itemsArray[] = [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'quantity' => $item->getQuantity(),
            'price' => $item->getPrice(),
            'category' => $item->getCategory()
        ];
    }
    
    $database->close();
    
    echo json_encode([
        'success' => true,
        'items' => $itemsArray
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>


<?php
// /public/index.php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../classes/Item.php';

$sampleItem1 = new Item(null, 'Laptop Computer', 10, 999.99, 'Electronics');
$sampleItem2 = new Item(null, 'Office Chair', 25, 149.50, 'Furniture');
$sampleItems = [$sampleItem1, $sampleItem2];
$sampleSerialized = serialize($sampleItems);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management System</title>
</head>
<body>
    <h1>Inventory Management System</h1>
    
    <h2>Process Serialized Inventory Data</h2>
    <form id="inventoryForm" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        
        <label for="serialized_data">Serialized Data:</label><br>
        <textarea name="serialized_data" id="serialized_data" rows="10" cols="80" required><?php echo htmlspecialchars($sampleSerialized); ?></textarea><br><br>
        
        <button type="submit">Process Data</button>
    </form>
    
    <div id="result"></div>
    
    <h2>Current Inventory</h2>
    <button id="loadInventory">Load Current Inventory</button>
    <div id="inventoryDisplay"></div>
    
    <script>
        document.getElementById('inventoryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('../handlers/process_inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('result');
                if (data.success) {
                    resultDiv.innerHTML = '<h3>Success!</h3><p>' + data.message + '</p>';
                    if (data.processed_items) {
                        resultDiv.innerHTML += '<h4>Processed Items:</h4>';
                        data.processed_items.forEach(item => {
                            resultDiv.innerHTML += '<p>Name: ' + item.name + ', Quantity: ' + item.quantity + ', Price: $' + item.price + ', Category: ' + item.category + '</p>';
                        });
                    }
                    resultDiv.style.color = 'green';
                } else {
                    resultDiv.innerHTML = '<h3>Error!</h3><p>' + data.error + '</p>';
                    resultDiv.style.color = 'red';
                }
            })
            .catch(error => {
                document.getElementById('result').innerHTML = '<h3>Error!</h3><p>Network error occurred</p>';
                document.getElementById('result').style.color = 'red';
            });
        });
        
        document.getElementById('loadInventory').addEventListener('click', function() {
            fetch('../handlers/get_inventory.php')
            .then(response => response.json())
            .then(data => {
                const inventoryDiv = document.getElementById('inventoryDisplay');
                if (data.success) {
                    inventoryDiv.innerHTML = '<h3>Current Inventory Items:</h3>';
                    if (data.items.length > 0) {
                        data.items.forEach(item => {
                            inventoryDiv.innerHTML += '<p>ID: ' + item.id + ', Name: ' + item.name + ', Quantity: ' + item.quantity + ', Price: $' + item.price + ', Category: ' + item.category + '</p>';
                        });
                    } else {
                        inventoryDiv.innerHTML += '<p>No items in inventory</p>';
                    }
                } else {
                    inventoryDiv.innerHTML = '<h3>Error loading inventory!</h3><p>' + data.error + '</p>';
                }
            })
            .catch(error => {
                document.getElementById('inventoryDisplay').innerHTML = '<h3>Error!</h3><p>Network error occurred</p>';
            });
        });
    </script>
</body>
</html>


sql
-- Database schema (run this SQL to create the required table)
CREATE DATABASE IF NOT EXISTS inventory_db;
USE inventory_db;

CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    quantity INT NOT NULL DEFAULT 0,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
?>