index.html
html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JSON Processor</title>
</head>
<body>
    <form action="process.php" method="POST">
        <textarea name="json_data" rows="10" cols="50" placeholder="Enter JSON data here"></textarea>
        <br>
        <input type="submit" value="Process JSON">
    </form>
</body>
</html>


process.php
<?php
header('Content-Type: application/json');

function logActivity($message, $sourceIp, $data = '') {
    $logFile = 'secure.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] IP: {$sourceIp} - {$message}";
    if ($data) {
        $logEntry .= " - Data: " . substr($data, 0, 100);
    }
    $logEntry .= PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function validateJsonSchema($data) {
    if (!is_object($data)) {
        return false;
    }
    
    if (!property_exists($data, 'user_id')) {
        return false;
    }
    
    if (!is_numeric($data->user_id) || $data->user_id <= 0) {
        return false;
    }
    
    return true;
}

function safeJsonDecode($jsonString) {
    if (!is_string($jsonString)) {
        return null;
    }
    
    if (strlen($jsonString) > 10000) {
        return null;
    }
    
    if (preg_match('/[^\x20-\x7E\s]/', $jsonString)) {
        return null;
    }
    
    $decoded = json_decode($jsonString, false, 3, JSON_BIGINT_AS_STRING);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    
    return $decoded;
}

$sourceIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logActivity('Invalid request method', $sourceIp);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method'
        ]);
        exit;
    }
    
    if (!isset($_POST['json_data'])) {
        logActivity('Missing json_data parameter', $sourceIp);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required data'
        ]);
        exit;
    }
    
    $jsonInput = trim($_POST['json_data']);
    
    if (empty($jsonInput)) {
        logActivity('Empty input received', $sourceIp);
        echo json_encode([
            'success' => false,
            'message' => 'Empty input provided'
        ]);
        exit;
    }
    
    logActivity('Processing attempt', $sourceIp, $jsonInput);
    
    $decodedData = safeJsonDecode($jsonInput);
    
    if ($decodedData === null) {
        logActivity('JSON decode failed', $sourceIp);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON format'
        ]);
        exit;
    }
    
    if (!validateJsonSchema($decodedData)) {
        logActivity('Schema validation failed', $sourceIp);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid data structure'
        ]);
        exit;
    }
    
    $userId = intval($decodedData->user_id);
    
    logActivity('Successfully processed user_id: ' . $userId, $sourceIp);
    
    echo json_encode([
        'success' => true,
        'message' => 'User ID: ' . $userId
    ]);
    
} catch (Exception $e) {
    logActivity('Exception occurred', $sourceIp);
    echo json_encode([
        'success' => false,
        'message' => 'Processing error occurred'
    ]);
}
?>