<?php
session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

class SerializedDataProcessor {
    private $allowedClasses = ['stdClass', 'DateTime', 'DateTimeImmutable'];
    private $maxDataSize = 1048576;
    
    public function validateCSRF($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public function sanitizeInput($data) {
        if (strlen($data) > $this->maxDataSize) {
            throw new Exception('Data size exceeds maximum allowed limit');
        }
        
        return trim($data);
    }
    
    public function deserializeData($serializedData) {
        if (empty($serializedData)) {
            throw new Exception('No data provided');
        }
        
        $originalErrorHandler = set_error_handler(function($severity, $message, $file, $line) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        
        try {
            if (!$this->isValidSerializedData($serializedData)) {
                throw new Exception('Invalid serialized data format');
            }
            
            $data = unserialize($serializedData, [
                'allowed_classes' => $this->allowedClasses,
                'max_depth' => 10
            ]);
            
            if ($data === false && $serializedData !== serialize(false)) {
                throw new Exception('Failed to unserialize data');
            }
            
            return $data;
        } finally {
            set_error_handler($originalErrorHandler);
        }
    }
    
    private function isValidSerializedData($data) {
        if (preg_match('/[Oo]:\d+:"(?!(?:stdClass|DateTime|DateTimeImmutable)")[^"]*"/', $data)) {
            return false;
        }
        
        if (preg_match('/[^asbdifNO:{};\'"0-9\-\.]/', $data)) {
            return false;
        }
        
        return true;
    }
    
    public function formatOutput($data) {
        return $this->formatValue($data, 0);
    }
    
    private function formatValue($value, $depth = 0) {
        if ($depth > 10) {
            return '[Max depth reached]';
        }
        
        $indent = str_repeat('  ', $depth);
        
        if (is_null($value)) {
            return 'NULL';
        } elseif (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        } elseif (is_scalar($value)) {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        } elseif (is_array($value)) {
            if (empty($value)) {
                return 'Array (empty)';
            }
            
            $output = "Array (\n";
            foreach ($value as $key => $val) {
                $output .= $indent . '  [' . htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') . '] => ' . $this->formatValue($val, $depth + 1) . "\n";
            }
            $output .= $indent . ')';
            return $output;
        } elseif (is_object($value)) {
            $className = get_class($value);
            if (!in_array($className, $this->allowedClasses)) {
                return '[Disallowed object type]';
            }
            
            $output = $className . " Object (\n";
            $reflection = new ReflectionObject($value);
            $properties = $reflection->getProperties();
            
            foreach ($properties as $property) {
                $property->setAccessible(true);
                $propValue = $property->getValue($value);
                $output .= $indent . '  [' . htmlspecialchars($property->getName(), ENT_QUOTES, 'UTF-8') . '] => ' . $this->formatValue($propValue, $depth + 1) . "\n";
            }
            $output .= $indent . ')';
            return $output;
        }
        
        return '[Unknown type]';
    }
}

$processor = new SerializedDataProcessor();
$result = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$processor->validateCSRF($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token');
        }
        
        $serializedData = $processor->sanitizeInput($_POST['serialized_data'] ?? '');
        $deserializedData = $processor->deserializeData($serializedData);
        $result = $processor->formatOutput($deserializedData);
        
    } catch (Exception $e) {
        $error = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    } catch (ErrorException $e) {
        $error = 'Error processing data: Invalid format';
    }
}

$sampleData = [
    'name' => 'John Doe',
    'age' => 30,
    'active' => true,
    'scores' => [85, 92, 78],
    'profile' => [
        'email' => 'john@example.com',
        'city' => 'New York'
    ]
];
$sampleSerialized = serialize($sampleData);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serialized Data Processor</title>
</head>
<body>
    <h1>Serialized Data Processor</h1>
    
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        
        <label for="serialized_data">Enter Serialized Data:</label><br>
        <textarea name="serialized_data" id="serialized_data" rows="10" cols="80" required><?php echo isset($_POST['serialized_data']) ? htmlspecialchars($_POST['serialized_data'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea><br><br>
        
        <button type="submit">Process Data</button>
        <button type="button" onclick="document.getElementById('serialized_data').value='<?php echo htmlspecialchars($sampleSerialized, ENT_QUOTES, 'UTF-8'); ?>'">Load Sample Data</button>
    </form>
    
    <?php if (!empty($error)): ?>
        <h2>Error:</h2>
        <div style="color: red; border: 1px solid red; padding: 10px; margin: 10px 0;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($result)): ?>
        <h2>Deserialized Data:</h2>
        <pre style="background: #f4f4f4; padding: 15px; border: 1px solid #ddd; overflow-x: auto;"><?php echo $result; ?></pre>
    <?php endif; ?>
    
    <h3>Instructions:</h3>
    <ul>
        <li>Enter valid PHP serialized data in the textarea above</li>
        <li>Click "Load Sample Data" to see an example</li>
        <li>Only basic data types and specific object classes are allowed for security</li>
        <li>Maximum data size is limited to 1MB</li>
    </ul>
    
    <h3>Sample Serialized Data:</h3>
    <pre style="background: #f9f9f9; padding: 10px; border: 1px solid #ccc; font-size: 12px;"><?php echo htmlspecialchars($sampleSerialized, ENT_QUOTES, 'UTF-8'); ?></pre>
</body>
</html>
?>