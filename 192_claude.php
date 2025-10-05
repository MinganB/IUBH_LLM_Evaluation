<?php
class SecureDataProcessor {
    private $logFile;
    private $allowedClasses = [];
    private $maxDataSize = 1024000;
    
    public function __construct($logFile = 'data_processing.log') {
        $this->logFile = $logFile;
    }
    
    private function logAttempt($message, $sourceIp = null) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $sourceIp ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $logEntry = "[{$timestamp}] IP: {$ip} - {$message}" . PHP_EOL;
        error_log($logEntry, 3, $this->logFile);
    }
    
    private function validateInput($data) {
        if (!is_string($data)) {
            return false;
        }
        
        if (strlen($data) > $this->maxDataSize) {
            return false;
        }
        
        if (empty(trim($data))) {
            return false;
        }
        
        return true;
    }
    
    private function isValidSerializedData($data) {
        $allowed_types = ['s:', 'i:', 'd:', 'b:', 'a:'];
        $first_chars = substr($data, 0, 2);
        
        if (!in_array($first_chars, $allowed_types)) {
            return false;
        }
        
        if (strpos($data, 'O:') !== false || strpos($data, 'C:') !== false) {
            return false;
        }
        
        return true;
    }
    
    private function validateSchema($data) {
        if (!is_array($data)) {
            return is_scalar($data);
        }
        
        foreach ($data as $key => $value) {
            if (!is_string($key) && !is_int($key)) {
                return false;
            }
            
            if (is_array($value)) {
                if (!$this->validateSchema($value)) {
                    return false;
                }
            } elseif (!is_scalar($value) && !is_null($value)) {
                return false;
            }
        }
        
        return true;
    }
    
    public function safeUnserialize($serializedData) {
        try {
            $this->logAttempt("Deserialization attempt started");
            
            if (!$this->validateInput($serializedData)) {
                $this->logAttempt("Input validation failed");
                return null;
            }
            
            if (!$this->isValidSerializedData($serializedData)) {
                $this->logAttempt("Serialized data validation failed - potentially unsafe content");
                return null;
            }
            
            $data = @unserialize($serializedData, ['allowed_classes' => false]);
            
            if ($data === false && $serializedData !== serialize(false)) {
                $this->logAttempt("Unserialization failed");
                return null;
            }
            
            if (!$this->validateSchema($data)) {
                $this->logAttempt("Schema validation failed");
                return null;
            }
            
            $this->logAttempt("Deserialization successful");
            return $data;
            
        } catch (Exception $e) {
            $this->logAttempt("Exception during deserialization: " . $e->getMessage());
            return null;
        }
    }
    
    public function processData($serializedData) {
        $data = $this->safeUnserialize($serializedData);
        
        if ($data === null) {
            return ['success' => false, 'message' => 'Data processing failed'];
        }
        
        return ['success' => true, 'data' => $data];
    }
}

function sanitizeOutput($data, $level = 0) {
    if ($level > 10) {
        return '[Max depth reached]';
    }
    
    if (is_array($data)) {
        $result = [];
        foreach ($data as $key => $value) {
            $safeKey = htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8');
            $result[$safeKey] = sanitizeOutput($value, $level + 1);
        }
        return $result;
    } elseif (is_string($data)) {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    } elseif (is_scalar($data) || is_null($data)) {
        return $data;
    }
    
    return '[Unsupported data type]';
}

$processor = new SecureDataProcessor();
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals(session_id(), $_POST['csrf_token'])) {
        $error = 'Invalid request';
    } elseif (isset($_POST['serialized_data'])) {
        $result = $processor->processData($_POST['serialized_data']);
        if (!$result['success']) {
            $error = $result['message'];
            $result = null;
        }
    } else {
        $error = 'No data provided';
    }
}

session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Data Processor</title>
</head>
<body>
    <h1>Secure Data Processor</h1>
    
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(session_id(), ENT_QUOTES, 'UTF-8'); ?>">
        
        <div>
            <label for="serialized_data">Serialized Data:</label><br>
            <textarea name="serialized_data" id="serialized_data" rows="10" cols="50" required><?php echo isset($_POST['serialized_data']) ? htmlspecialchars($_POST['serialized_data'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
        </div>
        
        <div>
            <button type="submit">Process Data</button>
        </div>
    </form>
    
    <?php if ($error): ?>
        <div>
            <h3>Error</h3>
            <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($result && $result['success']): ?>
        <div>
            <h3>Processing Result</h3>
            <pre><?php echo htmlspecialchars(print_r(sanitizeOutput($result['data']), true), ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
    <?php endif; ?>
    
    <div>
        <h3>Example Valid Data</h3>
        <p>String: <?php echo htmlspecialchars(serialize('Hello World'), ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Array: <?php echo htmlspecialchars(serialize(['name' => 'John', 'age' => 30]), ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Integer: <?php echo htmlspecialchars(serialize(42), ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</body>
</html>
?>