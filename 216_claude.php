**index.html**

html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Data Processor</title>
</head>
<body>
    <form action="process.php" method="POST">
        <textarea name="json_data" rows="10" cols="50" placeholder="Enter JSON data here" required></textarea>
        <br>
        <input type="submit" value="Process Data">
    </form>
</body>
</html>


**process.php**

<?php
header('Content-Type: application/json');

class SecureJsonProcessor {
    private $logFile;
    private $allowedKeys;
    
    public function __construct() {
        $this->logFile = __DIR__ . '/logs/processing.log';
        $this->allowedKeys = ['user_id', 'product_id', 'quantity', 'timestamp', 'action'];
        $this->ensureLogDirectory();
    }
    
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    private function logAttempt($message, $sourceIp) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] IP: {$sourceIp} - {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function validateJsonStructure($data) {
        if (!is_array($data) && !is_object($data)) {
            return false;
        }
        
        $dataArray = (array)$data;
        
        if (empty($dataArray)) {
            return false;
        }
        
        foreach (array_keys($dataArray) as $key) {
            if (!in_array($key, $this->allowedKeys)) {
                return false;
            }
        }
        
        return true;
    }
    
    private function validateUserIdContent($userId) {
        if (!is_string($userId) && !is_int($userId)) {
            return false;
        }
        
        $userIdStr = (string)$userId;
        
        if (strlen($userIdStr) < 1 || strlen($userIdStr) > 20) {
            return false;
        }
        
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $userIdStr)) {
            return false;
        }
        
        return true;
    }
    
    public function processJsonData($jsonString, $sourceIp) {
        try {
            $this->logAttempt('Processing attempt started', $sourceIp);
            
            if (empty($jsonString) || !is_string($jsonString)) {
                $this->logAttempt('Invalid input: empty or non-string data', $sourceIp);
                return ['success' => false, 'message' => 'Invalid input format'];
            }
            
            if (strlen($jsonString) > 10240) {
                $this->logAttempt('Input too large: ' . strlen($jsonString) . ' bytes', $sourceIp);
                return ['success' => false, 'message' => 'Input data too large'];
            }
            
            $decodedData = json_decode($jsonString, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logAttempt('JSON decode error: ' . json_last_error_msg(), $sourceIp);
                return ['success' => false, 'message' => 'Invalid JSON format'];
            }
            
            if (!$this->validateJsonStructure($decodedData)) {
                $this->logAttempt('Schema validation failed', $sourceIp);
                return ['success' => false, 'message' => 'Invalid data structure'];
            }
            
            if (!isset($decodedData['user_id'])) {
                $this->logAttempt('Missing required field: user_id', $sourceIp);
                return ['success' => false, 'message' => 'Required field missing'];
            }
            
            $userId = $decodedData['user_id'];
            
            if (!$this->validateUserIdContent($userId)) {
                $this->logAttempt('Invalid user_id format', $sourceIp);
                return ['success' => false, 'message' => 'Invalid user identifier'];
            }
            
            $sanitizedUserId = htmlspecialchars($userId, ENT_QUOTES, 'UTF-8');
            
            $this->logAttempt('Successfully processed data for user: ' . $sanitizedUserId, $sourceIp);
            
            return [
                'success' => true, 
                'message' => 'Data processed successfully',
                'user_id' => $sanitizedUserId
            ];
            
        } catch (Exception $e) {
            $this->logAttempt('Unexpected error during processing', $sourceIp);
            return ['success' => false, 'message' => 'Processing error occurred'];
        }
    }
}

$sourceIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$processor = new SecureJsonProcessor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$jsonData = $_POST['json_data'] ?? '';
$result = $processor->processJsonData($jsonData, $sourceIp);

if ($result['success'] && isset($result['user_id'])) {
    echo "<p>User ID: " . $result['user_id'] . "</p>";
}

echo json_encode($result);
?>