<?php

class SecureInventoryProcessor {
    private $logFile;
    private $allowedFields = ['item_id', 'name', 'quantity', 'price', 'category'];
    private $maxDataLength = 10000;
    
    public function __construct($logFile = 'inventory_log.txt') {
        $this->logFile = $logFile;
    }
    
    public function logActivity($message, $sourceIP = null) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $sourceIP ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $logEntry = "[{$timestamp}] IP: {$ip} - {$message}" . PHP_EOL;
        
        if (is_writable(dirname($this->logFile))) {
            file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
    }
    
    public function validateDataStructure($data) {
        if (!is_array($data)) {
            return false;
        }
        
        if (count($data) > 100) {
            return false;
        }
        
        foreach ($data as $item) {
            if (!is_array($item)) {
                return false;
            }
            
            foreach ($item as $key => $value) {
                if (!in_array($key, $this->allowedFields)) {
                    return false;
                }
                
                if (!is_scalar($value) && !is_null($value)) {
                    return false;
                }
                
                if (is_string($value) && strlen($value) > 500) {
                    return false;
                }
            }
            
            if (!isset($item['item_id']) || !is_numeric($item['item_id'])) {
                return false;
            }
            
            if (isset($item['quantity']) && (!is_numeric($item['quantity']) || $item['quantity'] < 0)) {
                return false;
            }
            
            if (isset($item['price']) && (!is_numeric($item['price']) || $item['price'] < 0)) {
                return false;
            }
        }
        
        return true;
    }
    
    public function sanitizeData($data) {
        $sanitized = [];
        
        foreach ($data as $item) {
            $cleanItem = [];
            
            foreach ($this->allowedFields as $field) {
                if (isset($item[$field])) {
                    $value = $item[$field];
                    
                    if (in_array($field, ['item_id', 'quantity'])) {
                        $cleanItem[$field] = (int)$value;
                    } elseif ($field === 'price') {
                        $cleanItem[$field] = round((float)$value, 2);
                    } else {
                        $cleanItem[$field] = htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
                    }
                }
            }
            
            if (!empty($cleanItem['item_id'])) {
                $sanitized[] = $cleanItem;
            }
        }
        
        return $sanitized;
    }
    
    public function secureUnserialize($serializedData) {
        if (strlen($serializedData) > $this->maxDataLength) {
            $this->logActivity("Data too large: " . strlen($serializedData) . " bytes");
            return false;
        }
        
        if (preg_match('/[CO]:\d+:/', $serializedData)) {
            $this->logActivity("Rejected serialized data containing objects");
            return false;
        }
        
        if (strpos($serializedData, '__wakeup') !== false || strpos($serializedData, '__destruct') !== false) {
            $this->logActivity("Rejected serialized data containing magic methods");
            return false;
        }
        
        $originalErrorReporting = error_reporting(0);
        
        try {
            $data = unserialize($serializedData, ['allowed_classes' => false]);
            
            if ($data === false && $serializedData !== 'b:0;') {
                $this->logActivity("Unserialization failed");
                return false;
            }
            
            if (!$this->validateDataStructure($data)) {
                $this->logActivity("Data structure validation failed");
                return false;
            }
            
            $sanitizedData = $this->sanitizeData($data);
            $this->logActivity("Successfully processed " . count($sanitizedData) . " inventory items");
            
            return $sanitizedData;
            
        } catch (Exception $e) {
            $this->logActivity("Exception during processing: " . $e->getMessage());
            return false;
        } finally {
            error_reporting($originalErrorReporting);
        }
    }
    
    public function displayInventory($items) {
        if (empty($items)) {
            return "<p>No valid inventory items found.</p>";
        }
        
        $html = "<div class='inventory-results'>";
        $html .= "<h3>Processed Inventory Items (" . count($items) . ")</h3>";
        $html .= "<table border='1'>";
        $html .= "<tr><th>Item ID</th><th>Name</th><th>Category</th><th>Quantity</th><th>Price</th></tr>";
        
        foreach ($items as $item) {
            $html .= "<tr>";
            $html .= "<td>" . ($item['item_id'] ?? 'N/A') . "</td>";
            $html .= "<td>" . ($item['name'] ?? 'N/A') . "</td>";
            $html .= "<td>" . ($item['category'] ?? 'N/A') . "</td>";
            $html .= "<td>" . ($item['quantity'] ?? 'N/A') . "</td>";
            $html .= "<td>" . (isset($item['price']) ? '$' . number_format($item['price'], 2) : 'N/A') . "</td>";
            $html .= "</tr>";
        }
        
        $html .= "</table>";
        $html .= "</div>";
        
        return $html;
    }
}

$processor = new SecureInventoryProcessor();
$message = '';
$inventoryDisplay = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['serialized_data'])) {
    $serializedInput = $_POST['serialized_data'];
    
    if (empty($serializedInput)) {
        $message = "<p style='color: red;'>No data provided.</p>";
        $processor->logActivity("Empty data submission");
    } else {
        $result = $processor->secureUnserialize($serializedInput);
        
        if ($result !== false) {
            $message = "<p style='color: green;'>Data processed successfully.</p>";
            $inventoryDisplay = $processor->displayInventory($result);
        } else {
            $message = "<p style='color: red;'>Data processing failed. Please check your input format.</p>";
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Inventory Data Processor</title>
</head>
<body>
    <h1>Inventory Management System</h1>
    <h2>Serialized Data Processor</h2>
    
    <?php echo $message; ?>
    
    <form method="post" action="">
        <div>
            <label for="serialized_data">Serialized Inventory Data:</label><br>
            <textarea name="serialized_data" id="serialized_data" rows="10" cols="80" placeholder="Enter serialized inventory data here..." required><?php echo isset($_POST['serialized_data']) ? htmlspecialchars($_POST['serialized_data']) : ''; ?></textarea>
        </div>
        <br>
        <div>
            <input type="submit" value="Process Inventory Data">
        </div>
    </form>
    
    <hr>
    
    <h3>Sample Data Format</h3>
    <p>Expected serialized format for inventory items:</p>
    <pre>a:2:{i:0;a:5:{s:7:"item_id";i:1;s:4:"name";s:12:"Sample Item";s:8:"quantity";i:10;s:5:"price";d:29.99;s:8:"category";s:11:"Electronics";}i:1;a:5:{s:7:"item_id";i:2;s:4:"name";s:13:"Another Item";s:8:"quantity";i:5;s:5:"price";d:15.50;s:8:"category";s:8:"Clothing";}}</pre>
    
    <?php echo $inventoryDisplay; ?>
</body>
</html>
?>