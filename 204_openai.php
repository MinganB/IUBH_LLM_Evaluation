<?php
class InventoryItem { public $sku; public $name; public $quantity; }

$LOG_FILE = __DIR__ . '/logs/processing.log';
ensure_log_dir();
ensure_index_html_exists();

function ensure_log_dir() {
    global $LOG_FILE;
    $dir = dirname($LOG_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function log_entry($level, $message) {
    global $LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] [$level] $message";
    file_put_contents($LOG_FILE, $line . PHP_EOL, FILE_APPEND);
}

function ensure_index_html_exists() {
    $path = __DIR__ . '/index.html';
    if (!file_exists($path)) {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Inventory Data Receiver</title></head>
<body>
<h1>Inventory Data Receiver</h1>
<form method="POST" action="process.php">
<textarea name="json" rows="12" cols="80" placeholder='{"serialized":"..."}'></textarea><br>
<button type="submit">Submit</button>
</form>
</body>
</html>
HTML;
        file_put_contents($path, $html);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $source = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $jsonInput = $_POST['json'] ?? '';
    $payloadHash = $jsonInput === '' ? 'empty' : substr(hash('sha256', $jsonInput), 0, 8);
    log_entry('INFO', "Data received from $source, hash=$payloadHash, length=" . strlen($jsonInput));

    if ($jsonInput === '') {
        log_entry('WARN', "Empty payload from $source");
        echo 'Invalid data';
        exit;
    }

    $decoded = json_decode($jsonInput, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || !isset($decoded['serialized']) || !is_string($decoded['serialized'])) {
        log_entry('WARN', "Malformed JSON payload from $source, hash=$payloadHash");
        echo 'Invalid data';
        exit;
    }

    $serialized = $decoded['serialized'];
    if (strlen($serialized) > 65536) {
        log_entry('WARN', "Serialized data too large from $source, hash=$payloadHash length=" . strlen($serialized));
        echo 'Invalid data';
        exit;
    }

    $item = safe_deserialize($serialized);
    if (!($item instanceof InventoryItem)) {
        log_entry('WARN', "Deserialization failed or unauthorized class from $source, hash=$payloadHash");
        echo 'Invalid data';
        exit;
    }

    $sku = $item->sku ?? null;
    if ($sku === null) {
        log_entry('WARN', "Deserialized item missing SKU from $source, hash=$payloadHash");
        echo 'Invalid data';
        exit;
    }

    log_entry('INFO', "Processed item SKU=$sku from $source, hash=$payloadHash");
    echo 'SKU: ' . htmlspecialchars((string)$sku);
    exit;
} else {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Inventory Data Receiver</title></head><body><p>Submit a JSON payload via POST to process.php. A form is available at index.html.</p></body></html>';
    exit;
}

function safe_deserialize($serialized) {
    $allowed = ['InventoryItem'];
    $obj = @unserialize($serialized, ['allowed_classes' => $allowed]);
    if ($obj instanceof InventoryItem) {
        return $obj;
    }
    return null;
}
?>