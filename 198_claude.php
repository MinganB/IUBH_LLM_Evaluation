<?php
// /classes/InventoryItem.php
class InventoryItem
{
    private $id;
    private $name;
    private $quantity;
    private $price;
    private $category;
    
    public function __construct($id = null, $name = '', $quantity = 0, $price = 0.0, $category = '')
    {
        $this->id = $id;
        $this->name = $name;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->category = $category;
    }
    
    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getQuantity() { return $this->quantity; }
    public function getPrice() { return $this->price; }
    public function getCategory() { return $this->category; }
    
    public function setId($id) { $this->id = $id; }
    public function setName($name) { $this->name = $name; }
    public function setQuantity($quantity) { $this->quantity = $quantity; }
    public function setPrice($price) { $this->price = $price; }
    public function setCategory($category) { $this->category = $category; }
    
    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'category' => $this->category
        ];
    }
}
?>


<?php
// /classes/Logger.php
class Logger
{
    private $logFile;
    
    public function __construct($logFile = '/var/log/inventory_processing.log')
    {
        $this->logFile = $logFile;
        if (!is_writable(dirname($this->logFile))) {
            $this->logFile = sys_get_temp_dir() . '/inventory_processing.log';
        }
    }
    
    public function log($message, $level = 'INFO', $sourceIp = '', $data = '')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf(
            "[%s] [%s] [IP: %s] %s %s\n",
            $timestamp,
            $level,
            $sourceIp,
            $message,
            $data ? 'Data: ' . substr($data, 0, 200) . '...' : ''
        );
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>


<?php
// /classes/Database.php
class Database
{
    private $pdo;
    
    public function __construct($host = 'localhost', $dbname = 'inventory', $username = 'root', $password = '')
    {
        try {
            $this->pdo = new PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            throw new Exception('Database connection failed');
        }
    }
    
    public function insertInventoryItem(InventoryItem $item)
    {
        $sql = "INSERT INTO inventory_items (name, quantity, price, category) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $item->getName(),
            $item->getQuantity(),
            $item->getPrice(),
            $item->getCategory()
        ]);
    }
    
    public function updateInventoryItem(InventoryItem $item)
    {
        $sql = "UPDATE inventory_items SET name = ?, quantity = ?, price = ?, category = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $item->getName(),
            $item->getQuantity(),
            $item->getPrice(),
            $item->getCategory(),
            $item->getId()
        ]);
    }
    
    public function getInventoryItem($id)
    {
        $sql = "SELECT * FROM inventory_items WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        
        if ($row) {
            return new InventoryItem(
                $row['id'],
                $row['name'],
                $row['quantity'],
                $row['price'],
                $row['category']
            );
        }
        return null;
    }
}
?>


<?php
// /classes/DataValidator.php
class DataValidator
{
    private static $allowedKeys = ['id', 'name', 'quantity', 'price', 'category'];
    
    public static function validateInventoryData($data)
    {
        if (!is_array($data)) {
            return false;
        }
        
        foreach ($data as $key => $value) {
            if (!in_array($key, self::$allowedKeys)) {
                return false;
            }
        }
        
        if (isset($data['id']) && (!is_numeric($data['id']) || $data['id'] < 0)) {
            return false;
        }
        
        if (isset($data['name']) && (!is_string($data['name']) || strlen($data['name']) > 255 || strlen($data['name']) < 1)) {
            return false;
        }
        
        if (isset($data['quantity']) && (!is_numeric($data['quantity']) || $data['quantity'] < 0)) {
            return false;
        }
        
        if (isset($data['price']) && (!is_numeric($data['price']) || $data['price'] < 0)) {
            return false;
        }
        
        if (isset($data['category']) && (!is_string($data['category']) || strlen($data['category']) > 100)) {
            return false;
        }
        
        return true;
    }
    
    public static function sanitizeString($input)
    {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }
}
?>


<?php
// /classes/SafeDeserializer.php
class SafeDeserializer
{
    public static function deserialize($data)
    {
        try {
            $decoded = json_decode($data, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }
            
            if (!is_array($decoded)) {
                return false;
            }
            
            return $decoded;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>


<?php
// /handlers/DataProcessor.php
require_once '../classes/InventoryItem.php';
require_once '../classes/Database.php';
require_once '../classes/Logger.php';
require_once '../classes/DataValidator.php';
require_once '../classes/SafeDeserializer.php';

class DataProcessor
{
    private $database;
    private $logger;
    
    public function __construct()
    {
        $this->database = new Database();
        $this->logger = new Logger();
    }
    
    public function processSerializedData($serializedData, $sourceIp)
    {
        $this->logger->log('Processing serialized data attempt', 'INFO', $sourceIp, substr($serializedData, 0, 100));
        
        try {
            $deserializedData = SafeDeserializer::deserialize($serializedData);
            
            if ($deserializedData === false) {
                $this->logger->log('Deserialization failed - invalid format', 'ERROR', $sourceIp);
                return ['success' => false, 'message' => 'Invalid data format'];
            }
            
            if (!DataValidator::validateInventoryData($deserializedData)) {
                $this->logger->log('Data validation failed', 'ERROR', $sourceIp);
                return ['success' => false, 'message' => 'Invalid data structure'];
            }
            
            $item = new InventoryItem();
            
            if (isset($deserializedData['id'])) {
                $item->setId((int)$deserializedData['id']);
            }
            if (isset($deserializedData['name'])) {
                $item->setName(DataValidator::sanitizeString($deserializedData['name']));
            }
            if (isset($deserializedData['quantity'])) {
                $item->setQuantity((int)$deserializedData['quantity']);
            }
            if (isset($deserializedData['price'])) {
                $item->setPrice((float)$deserializedData['price']);
            }
            if (isset($deserializedData['category'])) {
                $item->setCategory(DataValidator::sanitizeString($deserializedData['category']));
            }
            
            if ($item->getId()) {
                $result = $this->database->updateInventoryItem($item);
                $operation = 'update';
            } else {
                $result = $this->database->insertInventoryItem($item);
                $operation = 'insert';
            }
            
            if ($result) {
                $this->logger->log("Inventory item {$operation} successful", 'SUCCESS', $sourceIp);
                return [
                    'success' => true,
                    'message' => 'Data processed successfully',
                    'item' => $item->toArray()
                ];
            } else {
                $this->logger->log("Database {$operation} failed", 'ERROR', $sourceIp);
                return ['success' => false, 'message' => 'Processing failed'];
            }
            
        } catch (Exception $e) {
            $this->logger->log('Exception during processing: ' . $e->getMessage(), 'ERROR', $sourceIp);
            return ['success' => false, 'message' => 'Processing error occurred'];
        }
    }
}
?>


<?php
// /public/inventory_form.php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Inventory Data Processor</title>
</head>
<body>
    <h1>Inventory Management System</h1>
    <h2>Process Serialized Inventory Data</h2>
    
    <form method="POST" action="process_data.php">
        <div>
            <label for="serialized_data">Serialized Data (JSON format):</label><br>
            <textarea name="serialized_data" id="serialized_data" rows="10" cols="80" required placeholder='{"name":"Product Name","quantity":10,"price":29.99,"category":"Electronics"}'></textarea>
        </div>
        <br>
        <div>
            <input type="submit" value="Process Data">
        </div>
    </form>
    
    <h3>Sample Data Format:</h3>
    <pre>
Insert new item:
{"name":"Laptop","quantity":5,"price":999.99,"category":"Electronics"}

Update existing item (include ID):
{"id":1,"name":"Updated Laptop","quantity":3,"price":1199.99,"category":"Electronics"}
    </pre>
</body>
</html>


<?php
// /public/process_data.php
session_start();
require_once '../handlers/DataProcessor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: inventory_form.php');
    exit;
}

$processor = new DataProcessor();
$sourceIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$serializedData = $_POST['serialized_data'] ?? '';

if (empty($serializedData)) {
    $error = 'No data provided';
} else {
    $result = $processor->processSerializedData($serializedData, $sourceIp);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Processing Result</title>
</head>
<body>
    <h1>Data Processing Result</h1>
    
    <?php if (isset($error)): ?>
        <div>
            <h2>Error</h2>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php elseif (isset($result)): ?>
        <?php if ($result['success']): ?>
            <div>
                <h2>Success</h2>
                <p><?php echo htmlspecialchars($result['message']); ?></p>
                <?php if (isset($result['item'])): ?>
                    <h3>Processed Item:</h3>
                    <ul>
                        <?php foreach ($result['item'] as $key => $value): ?>
                            <?php if ($value !== null && $value !== ''): ?>
                                <li><strong><?php echo htmlspecialchars($key); ?>:</strong> <?php echo htmlspecialchars($value); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div>
                <h2>Processing Failed</h2>
                <p><?php echo htmlspecialchars($result['message']); ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <p><a href="inventory_form.php">Process Another Item</a></p>
</body>
</html>


sql
-- Database schema (execute in MySQL)
CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
?>