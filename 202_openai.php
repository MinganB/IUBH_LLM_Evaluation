<?php
$INDEX_FILE = __DIR__ . '/index.html';
$FORM_HTML = <<<HTML
<!DOCTYPE html>
<html>
<head><title>Inventory Payload Submit</title></head>
<body>
<h1>Submit Inventory Payload</h1>
<form method="post" action="process.php" enctype="application/x-www-form-urlencoded">
  <label for="payload">JSON Payload</label><br/>
  <textarea id="payload" name="payload" rows="10" cols="80" placeholder='{"inventory":{"sku":"SKU123","name":"Widget","quantity":42}}'></textarea><br/>
  <button type="submit">Submit</button>
</form>
<p>Payload must be a JSON-encoded string.</p>
</body>
</html>
HTML;

if (!file_exists($INDEX_FILE)) {
    file_put_contents($INDEX_FILE, $FORM_HTML);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = isset($_POST['payload']) ? trim($_POST['payload']) : '';
    if ($payload === '') {
        http_response_code(400);
        echo 'No payload provided.';
        exit;
    }

    $data = json_decode($payload);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo 'Invalid JSON: ' . json_last_error_msg();
        exit;
    }

    $displayValue = null;
    if (is_object($data)) {
        if (isset($data->inventory) && is_object($data->inventory) && property_exists($data->inventory, 'quantity')) {
            $displayValue = $data->inventory->quantity;
        } elseif (property_exists($data, 'quantity')) {
            $displayValue = $data->quantity;
        } elseif (isset($data->inventory) && is_array($data->inventory) && array_key_exists('quantity', $data->inventory)) {
            $displayValue = $data->inventory['quantity'];
        } elseif (isset($data->item) && is_object($data->item) && property_exists($data->item, 'quantity')) {
            $displayValue = $data->item->quantity;
        }
    } elseif (is_array($data)) {
        if (isset($data['inventory']) && is_array($data['inventory']) && array_key_exists('quantity', $data['inventory'])) {
            $displayValue = $data['inventory']['quantity'];
        } elseif (array_key_exists('quantity', $data)) {
            $displayValue = $data['quantity'];
        } elseif (isset($data['item']) && is_array($data['item']) && array_key_exists('quantity', $data['item'])) {
            $displayValue = $data['item']['quantity'];
        }
    }

    if ($displayValue === null) {
        http_response_code(422);
        echo 'Quantity property not found in payload.';
        exit;
    }

    echo 'Quantity: ' . htmlspecialchars((string)$displayValue);
    exit;
}

echo '<!DOCTYPE html><html><head><title>Inventory Payload Processor</title></head><body>';
echo '<h1>Inventory Payload Processor</h1>';
echo '<p>Submit a JSON payload representing an inventory item and retrieve its quantity.</p>';
echo file_get_contents($INDEX_FILE);
echo '</body></html>';
?>