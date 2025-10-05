<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>JSON Payload Submission</title>
</head>
<body>
<form method="POST" action="process.php" autocomplete="off">
<label for="payload">JSON Payload</label><br>
<textarea id="payload" name="payload" rows="10" cols="60" placeholder='{"user_id":123}'></textarea><br>
<button type="submit">Submit</button>
</form>
</body>
</html>
<?php
// process.php
declare(strict_types=1);

define('LOG_DIR', __DIR__ . '/logs');
define('LOG_FILE', LOG_DIR . '/processing.log');

function ensureLogDir(): void {
    if (!is_dir(LOG_DIR)) {
        @mkdir(LOG_DIR, 0755, true);
    }
}
function logProcessing(string $message): void {
    ensureLogDir();
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $message" . PHP_EOL;
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}
function safeDeserializeJson(string $json): ?array {
    if (!is_string($json) || trim($json) === '') {
        return null;
    }
    try {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        return null;
    }
    if (!is_array($decoded)) {
        return null;
    }
    if (!array_key_exists('user_id', $decoded)) {
        return null;
    }
    $userId = $decoded['user_id'];
    if (!is_scalar($userId)) {
        return null;
    }
    if (!preg_match('/^\d+$/', (string)$userId)) {
        return null;
    }
    return $decoded;
}

$payload = $_POST['payload'] ?? '';
$sourceIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$payloadLength = strlen($payload);
logProcessing("Processing attempt from {$sourceIp}; payload_length={$payloadLength}");

$data = safeDeserializeJson($payload);

if ($data === null) {
    logProcessing("Deserialization failed for payload from {$sourceIp}");
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Processing Result</title></head><body>';
        echo '<p>Invalid input.</p>';
        echo '</body></html>';
        exit;
    }
}

$userId = (string)$data['user_id'];
$display = 'User ID: ' . htmlspecialchars($userId, ENT_QUOTES, 'UTF-8');
logProcessing("Successfully deserialized payload from {$sourceIp}; user_id={$userId}");

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => true, 'message' => 'User ID: ' . $userId];
    echo json_encode($response);
    exit;
} else {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Processing Result</title></head><body>';
    echo '<p>' . $display . '</p>';
    echo '</body></html>';
    exit;
}
?>