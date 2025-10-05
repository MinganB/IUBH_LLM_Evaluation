<?php
class InventoryProcessor {
    private $allowedFields = ['id', 'name', 'quantity', 'price', 'category', 'supplier'];
    private $maxDataSize = 1048576;
    
    public function validateSerializedData($data) {
        if (strlen($data) > $this->maxDataSize) {
            throw new Exception('Data size exceeds maximum limit');
        }
        
        if (!preg_match('/^[aOs]:[0-9]+:/', $data)) {
            throw new Exception('Invalid serialized data format');
        }
        
        return true;
    }
    
    public function sanitizeData($data) {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                if (in_array($key, $this->allowedFields)) {
                    $sanitized[$key] = $this->sanitizeValue($value);
                }
            }
            return $sanitized;
        }
        return $this->sanitizeValue($data);
    }
    
    private function sanitizeValue($value) {
        if (is_string($value)) {
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
        if (is_numeric($value)) {
            return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }
        return $value;
    }
    
    public function processInventoryData($serializedData) {
        try {
            $this->validateSerializedData($serializedData);
            $data = unserialize($serializedData, ['allowed_classes' => false]);
            
            if ($data === false) {
                throw new Exception('Failed to deserialize data');
            }
            
            return $this->sanitizeData($data);
        } catch (Exception $e) {
            throw new Exception('Processing error: ' . $e->getMessage());
        }
    }
    
    public function displayInventoryData($data) {
        $html = '<div class="inventory-results">';
        $html .= '<h3>Processed Inventory Data</h3>';
        
        if (is_array($data)) {
            $html .= '<table border="1">';
            $html .= '<tr><th>Field</th><th>Value</th></tr>';
            
            foreach ($data as $key => $value) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</table>';
        } else {
            $html .= '<p>Single Value: ' . htmlspecialchars($data, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        
        $html .= '</div>';
        return $html;
    }
}

session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$processor = new InventoryProcessor();
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        try {
            $serializedData = $_POST['serialized_data'] ?? '';
            if (empty($serializedData)) {
                throw new Exception('No data provided');
            }
            
            $result = $processor->processInventoryData($serializedData);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Data Processor</title>
</head>
<body>
    <h1>Inventory Management System</h1>
    <h2>Serialized Data Processor</h2>
    
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        
        <label for="serialized_data">Serialized Inventory Data:</label><br>
        <textarea name="serialized_data" id="serialized_data" rows="10" cols="80" required><?php echo isset($_POST['serialized_data']) ? htmlspecialchars($_POST['serialized_data'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea><br><br>
        
        <input type="submit" value="Process Data">
    </form>
    
    <div class="sample-data">
        <h3>Sample Serialized Data:</h3>
        <p>Copy and paste this sample data to test:</p>
        <code>a:5:{s:2:"id";s:3:"123";s:4:"name";s:12:"Sample Item";s:8:"quantity";s:2:"50";s:5:"price";s:5:"29.99";s:8:"category";s:11:"Electronics";}</code>
    </div>
    
    <?php if ($error): ?>
        <div class="error">
            <h3>Error:</h3>
            <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($result !== null): ?>
        <?php echo $processor->displayInventoryData($result); ?>
    <?php endif; ?>
</body>
</html>
?>