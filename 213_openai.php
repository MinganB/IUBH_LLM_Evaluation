<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Inventory Data Ingest</title>
</head>
<body>
<h1>Inventory Data Ingest</h1>
<form method="post" action="process.php" autocomplete="off">
    <label for="payload">JSON Payload</label><br>
    <textarea id="payload" name="payload" rows="10" cols="80" placeholder='{"user_id":123,"item":"Widget"}'></textarea><br>
    <button type="submit">Submit</button>
</form>
</body>
</html>

<?php
header('Content-Type: application/json');
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/process.log';

function logAttempt($source, $payload, $success, $message, $logFile) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $timestamp = date('Y-m-d H:i:s');
    $payloadPreview = substr($payload ?? '', 0, 2048);
    $status = $success ? 'SUCCESS' : 'FAILURE';
    $line = sprintf("%s | source=%s | ip=%s | ua=%s | payload=%s | status=%s | message=%s\n",
                    $timestamp, $source, $ip, $ua, $payloadPreview, $status, $message);
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
function safeDeserialize($json, &$out) {
    if (!is_string($json)) {
        return false;
    }
    $decoded = json_decode($json);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }
    if (!is_object($decoded)) {
        return false;
    }
    if (!isset($decoded->user_id)) {
        return false;
    }
    $uid = $decoded->user_id;
    if (is_int($uid)) {
        // ok
    } elseif (is_string($uid) && ctype_digit($uid)) {
        $decoded->user_id = (int)$uid;
    } else {
        return false;
    }
    $out = $decoded;
    return true;
}

$payload = $_POST['payload'] ?? null;
$source = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rawPayload = is_string($payload) ? $payload : '';

if (!isset($payload) || !is_string($payload) || trim($payload) === '') {
    logAttempt($source, $rawPayload, false, 'Empty or missing payload', $logFile);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$data = null;
if (!safeDeserialize($payload, $data)) {
    logAttempt($source, $rawPayload, false, 'Deserialization failed or invalid schema', $logFile);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$userId = $data->user_id;
logAttempt($source, $rawPayload, true, 'Processed user_id=' . $userId, $logFile);

echo json_encode(['success' => true, 'message' => 'Processed user_id: ' . $userId]);