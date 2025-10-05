<?php
// /classes/InventoryItem.php
class InventoryItem {
    public $id;
    public $name;
    public $quantity;
    public $price;
    public $category;
    public $supplier;
    
    public function __construct($id = null, $name = '', $quantity = 0, $price = 0.00, $category = '', $supplier = '') {
        $this->id = $id;
        $this->name = $name;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->category = $category;
        $this->supplier = $supplier;
    }
    
    public function toArray() {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'category' => $this->category,
            'supplier' => $this->supplier
        ];
    }
}
?>


<?php
// /classes/Database.php
class Database {
    private $host = 'localhost';
    private $dbname = 'inventory_db';
    private $username = 'root';
    private $password = '';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function insertItem($item) {
        $sql = "INSERT INTO inventory (name, quantity, price, category, supplier) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$item->name, $item->quantity, $item->price, $item->category, $item->supplier]);
    }
    
    public function updateItem($item) {
        $sql = "UPDATE inventory SET name = ?, quantity = ?, price = ?, category = ?, supplier = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$item->name, $item->quantity, $item->price, $item->category, $item->supplier, $item->id]);
    }
    
    public function deleteItem($id) {
        $sql = "DELETE FROM inventory WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    public function getItem($id) {
        $sql = "SELECT * FROM inventory WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAllItems() {
        $sql = "SELECT * FROM inventory ORDER BY id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>


<?php
// /classes/SerializationHandler.php
class SerializationHandler {
    
    public static function serialize($data) {
        return serialize($data);
    }
    
    public static function unserialize($serializedData) {
        $data = @unserialize($serializedData);
        if ($data === false && $serializedData !== 'b:0;') {
            throw new Exception('Invalid serialized data');
        }
        return $data;
    }
    
    public static function validateSerializedData($serializedData) {
        if (empty($serializedData)) {
            return false;
        }
        
        $data = @unserialize($serializedData);
        return $data !== false || $serializedData === 'b:0;';
    }
    
    public static function arrayToInventoryItem($array) {
        if (!is_array($array)) {
            throw new Exception('Data must be an array');
        }
        
        return new InventoryItem(
            isset($array['id']) ? $array['id'] : null,
            isset($array['name']) ? $array['name'] : '',
            isset($array['quantity']) ? intval($array['quantity']) : 0,
            isset($array['price']) ? floatval($array['price']) : 0.00,
            isset($array['category']) ? $array['category'] : '',
            isset($array['supplier']) ? $array['supplier'] : ''
        );
    }
}
?>


<?php
// /handlers/inventory_handler.php
session_start();
require_once '../classes/InventoryItem.php';
require_once '../classes/Database.php';
require_once '../classes/SerializationHandler.php';

class InventoryHandler {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function processSerializedData($serializedData, $action = 'insert') {
        try {
            if (!SerializationHandler::validateSerializedData($serializedData)) {
                throw new Exception('Invalid serialized data format');
            }
            
            $data = SerializationHandler::unserialize($serializedData);
            
            if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                $results = [];
                foreach ($data as $itemData) {
                    $results[] = $this->processSingleItem($itemData, $action);
                }
                return $results;
            } else {
                return [$this->processSingleItem($data, $action)];
            }
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    private function processSingleItem($itemData, $action) {
        try {
            $item = SerializationHandler::arrayToInventoryItem($itemData);
            
            switch ($action) {
                case 'insert':
                    if ($this->db->insertItem($item)) {
                        return ['success' => 'Item inserted successfully', 'item' => $item->toArray()];
                    } else {
                        return ['error' => 'Failed to insert item'];
                    }
                    
                case 'update':
                    if ($item->id && $this->db->updateItem($item)) {
                        return ['success' => 'Item updated successfully', 'item' => $item->toArray()];
                    } else {
                        return ['error' => 'Failed to update item or invalid ID'];
                    }
                    
                case 'delete':
                    if ($item->id && $this->db->deleteItem($item->id)) {
                        return ['success' => 'Item deleted successfully', 'id' => $item->id];
                    } else {
                        return ['error' => 'Failed to delete item or invalid ID'];
                    }
                    
                default:
                    return ['error' => 'Invalid action specified'];
            }
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $handler = new InventoryHandler();
    
    if (isset($_POST['serialized_data'])) {
        $action = isset($_POST['action']) ? $_POST['action'] : 'insert';
        $results = $handler->processSerializedData($_POST['serialized_data'], $action);
        
        $_SESSION['processing_results'] = $results;
        header('Location: ../public/results.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_sample') {
    $sampleData = [
        [
            'id' => null,
            'name' => 'Sample Product 1',
            'quantity' => 100,
            'price' => 29.99,
            'category' => 'Electronics',
            'supplier' => 'TechCorp'
        ],
        [
            'id' => null,
            'name' => 'Sample Product 2',
            'quantity' => 50,
            'price' => 15.50,
            'category' => 'Office Supplies',
            'supplier' => 'OfficeMax'
        ]
    ];
    
    header('Content-Type: application/json');
    echo json_encode([
        'serialized' => SerializationHandler::serialize($sampleData),
        'original' => $sampleData
    ]);
    exit;
}
?>


<?php
// /public/index.php
session_start();
require_once '../classes/Database.php';

$db = new Database();
$items = $db->getAllItems();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Serialized Data Processor</title>
</head>
<body>
    <h1>Inventory Management System</h1>
    <h2>Process Serialized Data</h2>
    
    <form action="../handlers/inventory_handler.php" method="POST">
        <div>
            <label for="serialized_data">Serialized Data:</label><br>
            <textarea id="serialized_data" name="serialized_data" rows="10" cols="80" placeholder="Paste your serialized data here..." required></textarea>
        </div>
        
        <div>
            <label for="action">Action:</label>
            <select id="action" name="action" required>
                <option value="insert">Insert New Items</option>
                <option value="update">Update Existing Items</option>
                <option value="delete">Delete Items</option>
            </select>
        </div>
        
        <div>
            <button type="submit">Process Data</button>
            <button type="button" onclick="loadSampleData()">Load Sample Data</button>
            <button type="button" onclick="clearForm()">Clear Form</button>
        </div>
    </form>
    
    <hr>
    
    <h2>Current Inventory</h2>
    <?php if (empty($items)): ?>
        <p>No items in inventory.</p>
    <?php else: ?>
        <table border="1">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Category</th>
                    <th>Supplier</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['id']); ?></td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                        <td><?php echo htmlspecialchars($item['supplier']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <script>
        function loadSampleData() {
            fetch('../handlers/inventory_handler.php?action=get_sample')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('serialized_data').value = data.serialized;
                })
                .catch(error => {
                    alert('Error loading sample data: ' + error);
                });
        }
        
        function clearForm() {
            document.getElementById('serialized_data').value = '';
            document.getElementById('action').selectedIndex = 0;
        }
    </script>
</body>
</html>


<?php
// /public/results.php
session_start();
require_once '../classes/SerializationHandler.php';

if (!isset($_SESSION['processing_results'])) {
    header('Location: index.php');
    exit;
}

$results = $_SESSION['processing_results'];
unset($_SESSION['processing_results']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Results - Inventory Management</title>
</head>
<body>
    <h1>Data Processing Results</h1>
    
    <?php foreach ($results as $index => $result): ?>
        <div>
            <h3>Item <?php echo $index + 1; ?>:</h3>
            <?php if (isset($result['error'])): ?>
                <p><strong>Error:</strong> <?php echo htmlspecialchars($result['error']); ?></p>
            <?php else: ?>
                <p><strong>Status:</strong> <?php echo htmlspecialchars($result['success']); ?></p>
                <?php if (isset($result['item'])): ?>
                    <table border="1">
                        <tr>
                            <th>Field</th>
                            <th>Value</th>
                        </tr>
                        <?php foreach ($result['item'] as $field => $value): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($field); ?></td>
                                <td><?php echo htmlspecialchars($value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
                <?php if (isset($result['id'])): ?>
                    <p><strong>Affected ID:</strong> <?php echo htmlspecialchars($result['id']); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <hr>
    <?php endforeach; ?>
    
    <div>
        <a href="index.php">← Back to Main Page</a>
        <a href="serialize_form.php">Create New Serialized Data</a>
    </div>
</body>
</html>


<?php
// /public/serialize_form.php
require_once '../classes/InventoryItem.php';
require_once '../classes/SerializationHandler.php';

$serialized_data = '';
$original_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items = [];
    
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $itemData) {
            if (!empty($itemData['name'])) {
                $item = new InventoryItem(
                    !empty($itemData['id']) ? intval($itemData['id']) : null,
                    $itemData['name'],
                    intval($itemData['quantity']),
                    floatval($itemData['price']),
                    $itemData['category'],
                    $itemData['supplier']
                );
                $items[] = $item->toArray();
            }
        }
    }
    
    if (!empty($items)) {
        $serialized_data = SerializationHandler::serialize($items);
        $original_data = $items;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Serialized Data - Inventory Management</title>
</head>
?>