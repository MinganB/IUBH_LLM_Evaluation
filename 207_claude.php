**index.html**
html
<!DOCTYPE html>
<html>
<head>
    <title>Inventory Data Submission</title>
</head>
<body>
    <form action="process.php" method="POST">
        <label for="inventory_data">Inventory Data (JSON):</label>
        <textarea name="inventory_data" id="inventory_data" rows="10" cols="50" required></textarea>
        <br><br>
        <input type="submit" value="Submit Data">
    </form>
</body>
</html>


**classes/InventoryValidator.php**
<?php

class InventoryValidator
{
    private const ALLOWED_PROPERTIES = [
        'id' => 'integer',
        'name' => 'string',
        'quantity' => 'integer',
        'price' => 'numeric',
        'category' => 'string',
        'supplier' => 'string',
        'last_updated' => 'string'
    ];

    private const MAX_STRING_LENGTH = 255;
    private const MAX_QUANTITY = 999999;
    private const MAX_PRICE = 999999.99;

    public static function validateJsonStructure($data)
    {
        if (!is_array($data) && !is_object($data)) {
            return false;
        }

        $dataArray = (array) $data;

        foreach ($dataArray as $key => $value) {
            if (!array_key_exists($key, self::ALLOWED_PROPERTIES)) {
                return false;
            }

            if (!self::validatePropertyType($key, $value)) {
                return false;
            }

            if (!self::validatePropertyContent($key, $value)) {
                return false;
            }
        }

        return true;
    }

    private static function validatePropertyType($property, $value)
    {
        $expectedType = self::ALLOWED_PROPERTIES[$property];

        switch ($expectedType) {
            case 'integer':
                return is_int($value) || (is_string($value) && ctype_digit($value));
            case 'string':
                return is_string($value);
            case 'numeric':
                return is_numeric($value);
            default:
                return false;
        }
    }

    private static function validatePropertyContent($property, $value)
    {
        switch ($property) {
            case 'id':
            case 'quantity':
                $intValue = (int) $value;
                return $intValue >= 0 && $intValue <= self::MAX_QUANTITY;
            case 'price':
                $numValue = (float) $value;
                return $numValue >= 0 && $numValue <= self::MAX_PRICE;
            case 'name':
            case 'category':
            case 'supplier':
                return strlen($value) > 0 && strlen($value) <= self::MAX_STRING_LENGTH;
            case 'last_updated':
                return strlen($value) <= self::MAX_STRING_LENGTH && 
                       preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value);
            default:
                return false;
        }
    }

    public static function sanitizeData($data)
    {
        $sanitized = [];
        $dataArray = (array) $data;

        foreach ($dataArray as $key => $value) {
            if (array_key_exists($key, self::ALLOWED_PROPERTIES)) {
                $sanitized[$key] = self::sanitizeValue($key, $value);
            }
        }

        return $sanitized;
    }

    private static function sanitizeValue($property, $value)
    {
        switch (self::ALLOWED_PROPERTIES[$property]) {
            case 'integer':
                return (int) $value;
            case 'numeric':
                return (float) $value;
            case 'string':
                return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            default:
                return $value;
        }
    }
}


**classes/SecureLogger.php**
<?php

class SecureLogger
{
    private const LOG_FILE = '../logs/inventory_processing.log';
    private const MAX_LOG_SIZE = 10485760;

    public static function log($level, $message, $context = [])
    {
        $logDir = dirname(self::LOG_FILE);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }

        if (file_exists(self::LOG_FILE) && filesize(self::LOG_FILE) > self::MAX_LOG_SIZE) {
            self::rotateLog();
        }

        $timestamp = date('Y-m-d H:i:s');
        $clientIp = self::getClientIp();
        $userAgent = self::sanitizeUserAgent();
        
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        
        $logEntry = sprintf(
            "[%s] %s - IP: %s - UA: %s - %s %s\n",
            $timestamp,
            strtoupper($level),
            $clientIp,
            $userAgent,
            $message,
            $contextStr
        );

        file_put_contents(self::LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private static function rotateLog()
    {
        $backupFile = self::LOG_FILE . '.' . date('Y-m-d-H-i-s');
        rename(self::LOG_FILE, $backupFile);
    }

    private static function getClientIp()
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private static function sanitizeUserAgent()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return substr(preg_replace('/[^\w\s\-\.\(\)\/]/', '', $userAgent), 0, 255);
    }
}


**handlers/InventoryProcessor.php**
<?php

require_once '../classes/InventoryValidator.php';
require_once '../classes/SecureLogger.php';

class InventoryProcessor
{
    public static function processInventoryData($jsonString)
    {
        SecureLogger::log('info', 'Processing inventory data request started');

        if (empty($jsonString)) {
            SecureLogger::log('warning', 'Empty data received');
            return ['success' => false, 'error' => 'No data provided'];
        }

        if (strlen($jsonString) > 1048576) {
            SecureLogger::log('warning', 'Data size exceeds limit', ['size' => strlen($jsonString)]);
            return ['success' => false, 'error' => 'Data size exceeds maximum allowed'];
        }

        $decodedData = self::safeJsonDecode($jsonString);
        
        if ($decodedData === null) {
            SecureLogger::log('error', 'JSON decode failed');
            return ['success' => false, 'error' => 'Invalid data format'];
        }

        if (!InventoryValidator::validateJsonStructure($decodedData)) {
            SecureLogger::log('warning', 'Data validation failed');
            return ['success' => false, 'error' => 'Data validation failed'];
        }

        $sanitizedData = InventoryValidator::sanitizeData($decodedData);
        
        SecureLogger::log('info', 'Data processed successfully', [
            'properties_count' => count($sanitizedData),
            'has_id' => isset($sanitizedData['id'])
        ]);

        return ['success' => true, 'data' => $sanitizedData];
    }

    private static function safeJsonDecode($jsonString)
    {
        $data = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if ($data === null || (!is_array($data) && !is_object($data))) {
            return null;
        }

        return $data;
    }

    public static function extractProperty($data, $propertyName)
    {
        if (!is_array($data) || !isset($data[$propertyName])) {
            SecureLogger::log('warning', 'Property extraction failed', ['property' => $propertyName]);
            return null;
        }

        SecureLogger::log('info', 'Property extracted successfully', ['property' => $propertyName]);
        return $data[$propertyName];
    }
}


**public/process.php**
<?php

error_reporting(0);
ini_set('display_errors', 0);

require_once '../handlers/InventoryProcessor.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SecureLogger::log('warning', 'Invalid request method', ['method' => $_SERVER['REQUEST_METHOD']]);
    http_response_code(405);
    echo '<h1>Method Not Allowed</h1>';
    exit;
}

if (!isset($_POST['inventory_data'])) {
    SecureLogger::log('warning', 'Missing inventory_data parameter');
    echo '<h1>Error</h1><p>Processing failed. Please check your input and try again.</p>';
    exit;
}

$inventoryData = $_POST['inventory_data'];

$result = InventoryProcessor::processInventoryData($inventoryData);

if (!$result['success']) {
    echo '<h1>Processing Error</h1>';
    echo '<p>Unable to process the provided data. Please verify the format and try again.</p>';
    echo '<a href="index.html">Return to form</a>';
    exit;
}

$processedData = $result['data'];

$itemName = InventoryProcessor::extractProperty($processedData, 'name');

echo '<h1>Inventory Data Processed Successfully</h1>';

if ($itemName !== null) {
    echo '<h2>Item Name: ' . htmlspecialchars($itemName, ENT_QUOTES, 'UTF-8') . '</h2>';
} else {
    echo '<p>Item name not available in the processed data.</p>';
}

echo '<h3>Processed Data Summary:</h3>';
echo '<ul>';
foreach ($processedData as $key => $value) {
    echo '<li><strong>' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . ':</strong> ' . 
         htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</li>';
}
echo '</ul>';

echo '<a href="index.html">Process Another Item</a>';