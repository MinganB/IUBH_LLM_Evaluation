<?php
declare(strict_types=1);

function logProcessingEvent(string $source, int $payloadLen, string $status, ?int $userId = null): void {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/processing.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] Source=$source | PayloadLen=$payloadLen | Status=$status";
    if ($userId !== null) {
        $entry .= " | UserID=$userId";
    }
    $entry .= PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

function safeDeserializePayload(string $json): ?object {
    if (trim($json) === '') {
        return null;
    }
    $decoded = json_decode($json, false);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    if (!is_object($decoded)) {
        return null;
    }
    if (!property_exists($decoded, 'user_id')) {
        return null;
    }
    $vars = get_object_vars($decoded);
    foreach ($vars as $k => $v) {
        if ($k !== 'user_id') {
            return null;
        }
    }
    if (!isset($decoded->user_id) || !is_numeric($decoded->user_id)) {
        return null;
    }
    $decoded->user_id = (int)$decoded->user_id;
    return $decoded;
}

function getDatabaseConnection(): ?object {
    try {
        $dbPath = __DIR__ . '/../data/inventory.db';
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS inventory_updates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                received_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        return $pdo;
    } catch (Throwable $e) {
        return null;
    }
}

function updateDatabase(object $payloadObj): bool {
    $pdo = getDatabaseConnection();
    if ($pdo === null) {
        return false;
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO inventory_updates (user_id) VALUES (?)");
        $stmt->execute([$payloadObj->user_id]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function renderForm(): void {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory Data Processor</title>
</head>
<body>
<h1>Submit JSON Payload</h1>
<form method="post" action="process.php" autocomplete="off">
<label for="payload">JSON Payload</label><br>
<textarea id="payload" name="payload" rows="10" cols="80" placeholder="{\"user_id\": 123}"></textarea><br><br>
<button type="submit">Process</button>
</form>
</body>
</html>';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    renderForm();
    exit;
}

// POST request processing
$rawPayload = $_POST['payload'] ?? '';
$sourceIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$payloadLen = strlen($rawPayload);

logProcessingEvent($sourceIp, $payloadLen, 'attempt');

$response = [
    'success' => false,
    'message' => 'Invalid request.'
];

$payloadObj = safeDeserializePayload($rawPayload);
if ($payloadObj === null) {
    logProcessingEvent($sourceIp, $payloadLen, 'deserialization_failed');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
}

$userId = $payloadObj->user_id;
$dbOk = updateDatabase($payloadObj);
if (!$dbOk) {
    logProcessingEvent($sourceIp, $payloadLen, 'db_update_failed', $userId);
    $response['success'] = false;
    $response['message'] = 'Payload processed but database update failed.';
    $response['user_id'] = $userId;
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
}

logProcessingEvent($sourceIp, $payloadLen, 'success', $userId);
$response['success'] = true;
$response['message'] = 'Payload processed successfully.';
$response['user_id'] = $userId;

header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
exit;
?>