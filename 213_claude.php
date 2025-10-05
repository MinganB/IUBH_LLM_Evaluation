index.html
html
<!DOCTYPE html>
<html>
<head>
    <title>Inventory Data Processor</title>
</head>
<body>
    <form method="POST" action="process.php">
        <textarea name="json_data" rows="10" cols="50" placeholder="Enter JSON data here"></textarea>
        <br>
        <input type="submit" value="Process Data">
    </form>
</body>
</html>


process.php
<?php
header('Content-Type: application/json');

function logProcessingAttempt($message, $sourceIP) {
    $logFile = '/var/log/inventory_processing.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] IP: {$sourceIP} - {$message}" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function validateJsonStructure($data) {
    if (!is_object($data)) {
        return false;
    }
    
    $requiredFields = ['user_id'];
    foreach ($requiredFields as $field) {
        if (!property_exists($data, $field)) {
            return false;
        }
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
    
    if (strlen($jsonString) > 10240) {
        return null;
    }
    
    $data = json_decode($jsonString, false, 10, JSON_BIGINT_AS_STRING);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    
    return $data;
}

$sourceIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logProcessingAttempt('Invalid request method', $sourceIP);
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }
    
    if (!isset($_POST['json_data'])) {
        logProcessingAttempt('Missing json_data parameter', $sourceIP);
        echo json_encode(['success' => false, 'message' => 'Missing required data']);
        exit;
    }
    
    $jsonString = trim($_POST['json_data']);
    
    if (empty($jsonString)) {
        logProcessingAttempt('Empty json_data provided', $sourceIP);
        echo json_encode(['success' => false, 'message' => 'No data provided']);
        exit;
    }
    
    logProcessingAttempt('Processing attempt started', $sourceIP);
    
    $decodedData = safeJsonDecode($jsonString);
    
    if ($decodedData === null) {
        logProcessingAttempt('JSON decode failed', $sourceIP);
        echo json_encode(['success' => false, 'message' => 'Invalid data format']);
        exit;
    }
    
    if (!validateJsonStructure($decodedData)) {
        logProcessingAttempt('JSON structure validation failed', $sourceIP);
        echo json_encode(['success' => false, 'message' => 'Data validation failed']);
        exit;
    }
    
    $userId = intval($decodedData->user_id);
    
    logProcessingAttempt("Successfully processed data for user_id: {$userId}", $sourceIP);
    
    echo json_encode([
        'success' => true, 
        'message' => "Data processed successfully. User ID: {$userId}"
    ]);
    
} catch (Exception $e) {
    logProcessingAttempt('Exception occurred during processing', $sourceIP);
    echo json_encode(['success' => false, 'message' => 'Processing error occurred']);
}
?>