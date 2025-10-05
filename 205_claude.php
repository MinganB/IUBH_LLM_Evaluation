<?php
class Inventory {
    private $id;
    private $name;
    private $quantity;
    private $price;
    
    public function __construct($id, $name, $quantity, $price) {
        $this->id = $id;
        $this->name = $name;
        $this->quantity = $quantity;
        $this->price = $price;
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
    
    public function setId($id) {
        $this->id = $id;
    }
    
    public function setName($name) {
        $this->name = $name;
    }
    
    public function setQuantity($quantity) {
        $this->quantity = $quantity;
    }
    
    public function setPrice($price) {
        $this->price = $price;
    }
}
?>


<?php
class Database {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO('sqlite:' . __DIR__ . '/../inventory.db');
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initializeDatabase();
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    private function initializeDatabase() {
        $sql = "CREATE TABLE IF NOT EXISTS inventory (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            quantity INTEGER NOT NULL,
            price DECIMAL(10,2) NOT NULL
        )";
        $this->pdo->exec($sql);
    }
    
    public function updateInventory($inventory) {
        $sql = "INSERT OR REPLACE INTO inventory (id, name, quantity, price) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $inventory->getId(),
            $inventory->getName(),
            $inventory->getQuantity(),
            $inventory->getPrice()
        ]);
    }
    
    public function getInventoryById($id) {
        $sql = "SELECT * FROM inventory WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            return new Inventory($row['id'], $row['name'], $row['quantity'], $row['price']);
        }
        
        return null;
    }
    
    public function getAllInventory() {
        $sql = "SELECT * FROM inventory";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $items = [];
        foreach ($rows as $row) {
            $items[] = new Inventory($row['id'], $row['name'], $row['quantity'], $row['price']);
        }
        
        return $items;
    }
}
?>


<?php
require_once __DIR__ . '/../classes/Inventory.php';
require_once __DIR__ . '/../classes/Database.php';

class InventoryHandler {
    private $database;
    
    public function __construct() {
        $this->database = new Database();
    }
    
    public function processSerializedData($jsonData) {
        try {
            $data = json_decode($jsonData, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON data");
            }
            
            if (!isset($data['id']) || !isset($data['name']) || !isset($data['quantity']) || !isset($data['price'])) {
                throw new Exception("Missing required fields");
            }
            
            $inventory = new Inventory(
                $data['id'],
                $data['name'],
                $data['quantity'],
                $data['price']
            );
            
            $this->database->updateInventory($inventory);
            
            return $inventory;
            
        } catch (Exception $e) {
            throw new Exception("Error processing data: " . $e->getMessage());
        }
    }
    
    public function getInventoryProperty($inventory, $property) {
        switch ($property) {
            case 'id':
                return $inventory->getId();
            case 'name':
                return $inventory->getName();
            case 'quantity':
                return $inventory->getQuantity();
            case 'price':
                return $inventory->getPrice();
            default:
                return null;
        }
    }
}
?>


html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
</head>
<body>
    <h1>Inventory Data Processor</h1>
    
    <form action="process.php" method="POST">
        <div>
            <label for="json_data">JSON Data:</label>
            <textarea id="json_data" name="json_data" rows="10" cols="50" placeholder='{"id": 1, "name": "Product Name", "quantity": 100, "price": 29.99}'></textarea>
        </div>
        
        <div>
            <label for="property">Display Property:</label>
            <select id="property" name="property">
                <option value="id">ID</option>
                <option value="name">Name</option>
                <option value="quantity">Quantity</option>
                <option value="price">Price</option>
            </select>
        </div>
        
        <div>
            <button type="submit">Process Data</button>
        </div>
    </form>
    
    <h2>Sample JSON Format:</h2>
    <pre>
{
    "id": 1,
    "name": "Sample Product",
    "quantity": 50,
    "price": 19.99
}
    </pre>
</body>
</html>


<?php
require_once __DIR__ . '/../handlers/InventoryHandler.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

if (!isset($_POST['json_data']) || !isset($_POST['property'])) {
    http_response_code(400);
    echo "Missing required parameters";
    exit;
}

try {
    $handler = new InventoryHandler();
    $inventory = $handler->processSerializedData($_POST['json_data']);
    $property = $_POST['property'];
    $displayValue = $handler->getInventoryProperty($inventory, $property);
    
    echo "<!DOCTYPE html>";
    echo "<html lang='en'>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    echo "<title>Processing Result</title>";
    echo "</head>";
    echo "<body>";
    echo "<h1>Data Processing Result</h1>";
    echo "<p><strong>Property:</strong> " . htmlspecialchars($property) . "</p>";
    echo "<p><strong>Value:</strong> " . htmlspecialchars($displayValue) . "</p>";
    echo "<p><strong>Status:</strong> Data successfully processed and saved to database</p>";
    echo "<a href='index.html'>Process Another Item</a>";
    echo "</body>";
    echo "</html>";
    
} catch (Exception $e) {
    http_response_code(500);
    echo "<!DOCTYPE html>";
    echo "<html lang='en'>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    echo "<title>Error</title>";
    echo "</head>";
    echo "<body>";
    echo "<h1>Error Processing Data</h1>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='index.html'>Try Again</a>";
    echo "</body>";
    echo "</html>";
}
?>