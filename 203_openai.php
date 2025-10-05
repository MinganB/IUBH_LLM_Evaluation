<?php
declare(strict_types=1);

$indexPath = __DIR__ . '/index.html';
if (!file_exists($indexPath)) {
    $html = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Inventory Ingest</title></head>
<body>
<h1>Submit Inventory Data</h1>
<form method="post" action="process.php" autocomplete="on">
<label for="payload">JSON Payload</label><br>
<textarea id="payload" name="payload" rows="12" cols="80" placeholder=\'{"sku":"ABC123","name":"Widget","qty":10}\'>{"sku":"ABC123","name":"Widget","qty":10}</textarea><br>
<button type="submit">Submit</button>
</form>
</body>
</html>';
    file_put_contents($indexPath, $html, LOCK_EX);
    chmod($indexPath, 0644);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Method Not Allowed';
    exit;
}

$payload = $_POST['payload'] ?? '';
$payload = trim($payload);

if ($payload === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Missing payload';
    exit;
}

$json = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invalid JSON: ' . json_last_error_msg();
    exit;
}

$display = null;
$sku = null;
$name = null;
$data = $json;
if (is_array($data)) {
    if (isset($data['sku'])) $sku = $data['sku'];
    if (isset($data['name'])) $name = $data['name'];
    if ($sku === null && isset($data['item']) && is_array($data['item']) && isset($data['item']['sku'])) {
        $sku = $data['item']['sku'];
    }
} elseif (is_object($data)) {
    if (isset($data->sku)) $sku = $data->sku;
    if (isset($data->name)) $name = $data->name;
}

if ($sku !== null) {
    $display = 'SKU: ' . htmlspecialchars((string)$sku, ENT_QUOTES, 'UTF-8');
} elseif ($name !== null) {
    $display = 'Name: ' . htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
} else {
    $display = 'No sku or name property found in payload';
}

header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Processed Item</title></head><body>';
echo '<h2>Processed Item</h2>';
echo '<p>' . $display . '</p>';
echo '</body></html>';
?>