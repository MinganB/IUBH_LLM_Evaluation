index.html
html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Data Processor</title>
</head>
<body>
    <h1>Inventory Management System</h1>
    <form action="process.php" method="POST">
        <label for="inventory_data">Inventory Data (JSON):</label><br>
        <textarea name="inventory_data" id="inventory_data" rows="10" cols="50" required placeholder='{"product_id": "12345", "name": "Widget", "quantity": 100, "price": 29.99}'></textarea><br><br>
        <input type="submit" value="Process Inventory Data">
    </form>
</body>
</html>


process.php
<?php
error_reporting(0);
ini_set('display_errors', 0);

class InventoryProcessor {
    private $logFile;
    private $allowedKeys = ['product_id', 'name', 'quantity', 'price', 'category', 'supplier'];
    private $requiredKeys = ['product_id', 'name', 'quantity', 'price'];
    
    public function __construct() {
        $this->logFile = dirname(__FILE__) . '/inventory_processor.log';
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
            chmod($this->logFile, 0600);
        }
    }
    
    private function logAttempt($message, $sourceIp) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] Source: {$sourceIp} - {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function validateJsonStructure($data) {
        if (!is_array($data) && !is_object($data)) {
            return false;
        }
        
        $dataArray = (array)$data;
        
        foreach ($this->requiredKeys as $key) {
            if (!array_key_exists($key, $dataArray)) {
                return false;
            }
        }
        
        foreach ($dataArray as $key => $value) {
            if (!in_array($key, $this->allowedKeys)) {
                return false;
            }
        }
        
        if (!is_string($dataArray['product_id']) || strlen($dataArray['product_id']) > 50) {
            return false;
        }
        
        if (!is_string($dataArray['name']) || strlen($dataArray['name']) > 200) {
            return false;
        }
        
        if (!is_numeric($dataArray['quantity']) || $dataArray['quantity'] < 0) {
            return false;
        }
        
        if (!is_numeric($dataArray['price']) || $dataArray['price'] < 0) {
            return false;
        }
        
        if (isset($dataArray['category']) && (!is_string($dataArray['category']) || strlen($dataArray['category']) > 100)) {
            return false;
        }
        
        if (isset($dataArray['supplier']) && (!is_string($dataArray['supplier']) || strlen($dataArray['supplier']) > 200)) {
            return false;
        }
        
        return true;
    }
    
    private function sanitizeInput($input) {
        if (!is_string($input)) {
            return false;
        }
        
        $input = trim($input);
        
        if (strlen($input) > 10000) {
            return false;
        }
        
        if (preg_match('/[^\x20-\x7E\s]/', $input)) {
            return false;
        }
        
        return $input;
    }
    
    public function processInventoryData($jsonString, $sourceIp) {
        $this->logAttempt("Processing attempt started", $sourceIp);
        
        $sanitizedInput = $this->sanitizeInput($jsonString);
        if ($sanitizedInput === false) {
            $this->logAttempt("Input sanitization failed", $sourceIp);
            return ['success' => false, 'message' => 'Invalid input format'];
        }
        
        $decodedData = json_decode($sanitizedInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logAttempt("JSON decode error: " . json_last_error_msg(), $sourceIp);
            return ['success' => false, 'message' => 'Invalid data format'];
        }
        
        if (!$this->validateJsonStructure($decodedData)) {
            $this->logAttempt("Schema validation failed", $sourceIp);
            return ['success' => false, 'message' => 'Data validation failed'];
        }
        
        $this->logAttempt("Processing completed successfully for product: " . $decodedData['product_id'], $sourceIp);
        
        return [
            'success' => true,
            'product_name' => htmlspecialchars($decodedData['name'], ENT_QUOTES, 'UTF-8')
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!isset($_POST['inventory_data'])) {
    http_response_code(400);
    exit('Missing required data');
}

$sourceIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $sourceIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
}
$sourceIp = filter_var($sourceIp, FILTER_VALIDATE_IP) ? $sourceIp : 'invalid';

$processor = new InventoryProcessor();
$result = $processor->processInventoryData($_POST['inventory_data'], $sourceIp);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Result</title>
</head>
<body>
    <h1>Inventory Processing Result</h1>
    <?php if ($result['success']): ?>
        <h2>Processing Successful</h2>
        <p><strong>Product Name:</strong> <?php echo $result['product_name']; ?></p>
        <p>Inventory data has been processed successfully.</p>
    <?php else: ?>
        <h2>Processing Failed</h2>
        <p>Unable to process the inventory data. Please check your input and try again.</p>
    <?php endif; ?>
    <p><a href="index.html">Process Another Item</a></p>
</body>
</html>