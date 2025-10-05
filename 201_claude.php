**index.html**
html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Processing Form</title>
</head>
<body>
    <form action="process.php" method="POST">
        <label for="json_data">JSON Data:</label>
        <textarea name="json_data" id="json_data" rows="10" cols="50" required></textarea>
        <br>
        <button type="submit">Submit</button>
    </form>
</body>
</html>


**process.php**
<?php
session_start();

class SecureDataProcessor {
    private $logFile;
    private $allowedProperties;
    private $maxDataSize;
    
    public function __construct() {
        $this->logFile = __DIR__ . '/secure_data.log';
        $this->allowedProperties = ['name', 'email', 'message', 'id', 'status'];
        $this->maxDataSize = 1024 * 10;
    }
    
    public function processRequest() {
        $sourceIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->logAttempt('Invalid request method', $sourceIP);
                $this->displayError();
                return;
            }
            
            if (!isset($_POST['json_data'])) {
                $this->logAttempt('Missing json_data parameter', $sourceIP);
                $this->displayError();
                return;
            }
            
            $rawData = $_POST['json_data'];
            
            if (strlen($rawData) > $this->maxDataSize) {
                $this->logAttempt('Data size exceeds limit', $sourceIP);
                $this->displayError();
                return;
            }
            
            if (!$this->validateInputFormat($rawData)) {
                $this->logAttempt('Invalid input format', $sourceIP);
                $this->displayError();
                return;
            }
            
            $deserializedData = $this->safeDeserialize($rawData);
            
            if ($deserializedData === null) {
                $this->logAttempt('Deserialization failed', $sourceIP);
                $this->displayError();
                return;
            }
            
            if (!$this->validateSchema($deserializedData)) {
                $this->logAttempt('Schema validation failed', $sourceIP);
                $this->displayError();
                return;
            }
            
            $this->logAttempt('Successful processing', $sourceIP);
            $this->displayResult($deserializedData);
            
        } catch (Exception $e) {
            $this->logAttempt('Exception occurred', $sourceIP);
            $this->displayError();
        }
    }
    
    private function validateInputFormat($data) {
        if (!is_string($data)) {
            return false;
        }
        
        if (preg_match('/[^\x20-\x7E\s]/', $data)) {
            return false;
        }
        
        if (strpos($data, 'O:') !== false || strpos($data, 'C:') !== false) {
            return false;
        }
        
        return true;
    }
    
    private function safeDeserialize($jsonString) {
        $data = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        if (!is_array($data)) {
            return null;
        }
        
        return $this->sanitizeArray($data);
    }
    
    private function sanitizeArray($data) {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (!is_string($key) || strlen($key) > 50) {
                continue;
            }
            
            $cleanKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            
            if (is_string($value)) {
                $sanitized[$cleanKey] = htmlspecialchars(substr($value, 0, 500), ENT_QUOTES, 'UTF-8');
            } elseif (is_numeric($value)) {
                $sanitized[$cleanKey] = $value;
            } elseif (is_bool($value)) {
                $sanitized[$cleanKey] = $value;
            } elseif (is_array($value)) {
                $sanitized[$cleanKey] = $this->sanitizeArray($value);
            }
        }
        
        return $sanitized;
    }
    
    private function validateSchema($data) {
        if (!is_array($data)) {
            return false;
        }
        
        if (count($data) > 20) {
            return false;
        }
        
        foreach ($data as $key => $value) {
            if (!in_array($key, $this->allowedProperties)) {
                return false;
            }
        }
        
        return true;
    }
    
    private function displayResult($data) {
        echo "<h2>Processing Result</h2>";
        echo "<div>";
        
        if (isset($data['name'])) {
            echo "<p>Name: " . $data['name'] . "</p>";
        } elseif (isset($data['email'])) {
            echo "<p>Email: " . $data['email'] . "</p>";
        } elseif (isset($data['message'])) {
            echo "<p>Message: " . $data['message'] . "</p>";
        } elseif (isset($data['id'])) {
            echo "<p>ID: " . $data['id'] . "</p>";
        } elseif (isset($data['status'])) {
            echo "<p>Status: " . $data['status'] . "</p>";
        } else {
            echo "<p>No displayable property found.</p>";
        }
        
        echo "</div>";
        echo "<a href='index.html'>Back to Form</a>";
    }
    
    private function displayError() {
        echo "<h2>Processing Error</h2>";
        echo "<p>The submitted data could not be processed.</p>";
        echo "<a href='index.html'>Back to Form</a>";
    }
    
    private function logAttempt($message, $sourceIP) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] Source: {$sourceIP} - {$message}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

$processor = new SecureDataProcessor();
$processor->processRequest();
?>