<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Data Processor</title>
</head>
<body>
    <h1>Submit Inventory Data</h1>
    <form action="process.php" method="POST">
        <label for="json_data">Enter JSON Inventory Item:</label><br>
        <textarea id="json_data" name="json_data" rows="10" cols="50">
{
  "item_id": 1001,
  "item_name": "Premium Gadget A",
  "quantity": 50,
  "price": 29.99,
  "category": "Electronics"
}
        </textarea><br><br>
        <input type="submit" value="Process Data">
    </form>
</body>
</html>
<?php

const LOG_FILE = __DIR__ . '/log/processing.log';
const REQUIRED_SCHEMA = [
    'item_id' => 'integer',
    'item_name' => 'string',
    'quantity' => 'integer',
    'price' => 'double',
    'category' => 'string'
];

function log_message(string $level, string $message, array $context = []): void
{
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = sprintf("[%s] [%s] %s", $timestamp, $level, $message);

    if (!empty($context)) {
        $log_entry .= " Context: " . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    $log_entry .= PHP_EOL;

    $log_dir = dirname(LOG_FILE);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    if (file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX) === false) {
        error_log("Failed to write to log file: " . LOG_FILE);
    }
}

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Inventory Data Processing Result</h1>";

$raw_json_data = '';
$source_ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

try {
    if (!isset($_POST['json_data']) || empty($_POST['json_data'])) {
        log_message('WARNING', 'No JSON data received.', ['source_ip' => $source_ip]);
        echo "<p>Processing failed: No data submitted.</p>";
        exit;
    }

    $raw_json_data = $_POST['json_data'];

    log_message('INFO', 'Attempting to process incoming JSON data.', [
        'source_ip' => $source_ip,
        'raw_data_length' => strlen($raw_json_data)
    ]);

    $decoded_data = json_decode($raw_json_data, false, 512, JSON_THROW_ON_ERROR);

    if (!is_object($decoded_data)) {
        log_message('ERROR', 'Deserialised data is not an object.', [
            'source_ip' => $source_ip,
            'raw_data' => $raw_json_data,
            'decoded_type' => gettype($decoded_data)
        ]);
        echo "<p>Processing failed: Invalid data format.</p>";
        exit;
    }

    foreach (REQUIRED_SCHEMA as $field => $expected_type) {
        if (!property_exists($decoded_data, $field)) {
            log_message('ERROR', "Missing required field in JSON data: {$field}.", [
                'source_ip' => $source_ip,
                'raw_data' => $raw_json_data,
                'missing_field' => $field
            ]);
            echo "<p>Processing failed: Data schema invalid (missing required fields).</p>";
            exit;
        }

        $field_value = $decoded_data->$field;
        $actual_type = gettype($field_value);

        $type_matches = false;
        if ($expected_type === 'double' && ($actual_type === 'double' || $actual_type === 'integer')) {
             $type_matches = true;
        } elseif ($actual_type === $expected_type) {
             $type_matches = true;
        }

        if (!$type_matches) {
            log_message('ERROR', "Field '{$field}' has incorrect type. Expected '{$expected_type}', got '{$actual_type}'.", [
                'source_ip' => $source_ip,
                'raw_data' => $raw_json_data,
                'field' => $field,
                'expected_type' => $expected_type,
                'actual_type' => $actual_type,
                'field_value' => $field_value
            ]);
            echo "<p>Processing failed: Data schema invalid (incorrect field type).</p>";
            exit;
        }

        if ($field === 'quantity' && (!is_int($field_value) || $field_value <= 0)) {
            log_message('ERROR', "Invalid content for field 'quantity'. Must be a positive integer.", [
                'source_ip' => $source_ip,
                'raw_data' => $raw_json_data,
                'field' => $field,
                'field_value' => $field_value
            ]);
            echo "<p>Processing failed: Invalid data content (quantity).</p>";
            exit;
        }
        if ($field === 'price' && (!is_numeric($field_value) || $field_value <= 0)) {
             log_message('ERROR', "Invalid content for field 'price'. Must be a positive number.", [
                'source_ip' => $source_ip,
                'raw_data' => $raw_json_data,
                'field' => $field,
                'field_value' => $field_value
            ]);
            echo "<p>Processing failed: Invalid data content (price).</p>";
            exit;
        }
    }

    log_message('INFO', 'JSON data successfully deserialised and validated.', [
        'source_ip' => $source_ip,
        'item_id' => $decoded_data->item_id,
        'item_name' => $decoded_data->item_name
    ]);

    $item_name = htmlspecialchars($decoded_data->item_name, ENT_QUOTES, 'UTF-8');
    $item_id = htmlspecialchars((string)$decoded_data->item_id, ENT_QUOTES, 'UTF-8');
    $quantity = htmlspecialchars((string)$decoded_data->quantity, ENT_QUOTES, 'UTF-8');
    $price = htmlspecialchars(sprintf('%.2f', $decoded_data->price), ENT_QUOTES, 'UTF-8');
    $category = htmlspecialchars($decoded_data->category, ENT_QUOTES, 'UTF-8');

    echo "<p>Data successfully processed!</p>";
    echo "<h2>Inventory Item Details:</h2>";
    echo "<p><strong>Item Name:</strong> {$item_name}</p>";
    echo "<p><strong>Item ID:</strong> {$item_id}</p>";
    echo "<p><strong>Quantity:</strong> {$quantity}</p>";
    echo "<p><strong>Price:</strong> {$price}</p>";
    echo "<p><strong>Category:</strong> {$category}</p>";

} catch (JsonException $e) {
    log_message('ERROR', 'JSON decoding failed.', [
        'source_ip' => $source_ip,
        'raw_data' => $raw_json_data,
        'error_message' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
    echo "<p>Processing failed: Malformed JSON data.</p>";
} catch (Throwable $e) {
    log_message('CRITICAL', 'An unexpected error occurred during processing.', [
        'source_ip' => $source_ip,
        'raw_data' => $raw_json_data,
        'error_message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    echo "<p>Processing failed: An internal server error occurred.</p>";
}

?>