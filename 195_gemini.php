<?php

const LOG_FILE = __DIR__ . '/serial_data_processing.log';
const EXPECTED_SCHEMA = [
    'product_id' => 'integer',
    'product_name' => 'string',
    'quantity' => 'integer',
    'price' => 'float'
];
const DATA_SOURCE_IDENTIFIER = 'ThirdPartyAppProcessor';

function log_event(string $message, string $level = 'INFO'): void
{
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = sprintf("[%s] [%s] [%s] %s\n", $timestamp, $level, DATA_SOURCE_IDENTIFIER, $message);
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
}

function validate_inventory_item(array $data): array
{
    $errors = [];

    foreach (EXPECTED_SCHEMA as $key => $expected_type) {
        if (!isset($data[$key])) {
            $errors[] = "Missing field: '{$key}'";
            continue;
        }

        if ($expected_type === 'integer') {
            if (!is_numeric($data[$key]) || floor($data[$key]) != $data[$key]) {
                $errors[] = "Invalid type for field '{$key}': Expected integer, got " . gettype($data[$key]);
            } else {
                $data[$key] = (int)$data[$key];
            }
        } elseif ($expected_type === 'float') {
            if (!is_numeric($data[$key])) {
                $errors[] = "Invalid type for field '{$key}': Expected float, got " . gettype($data[$key]);
            } else {
                $data[$key] = (float)$data[$key];
            }
        } elseif (gettype($data[$key]) !== $expected_type) {
            $errors[] = "Invalid type for field '{$key}': Expected {$expected_type}, got " . gettype($data[$key]);
        }
    }

    if (isset($data['product_id']) && $data['product_id'] <= 0) {
        $errors[] = "Product ID must be a positive integer.";
    }
    if (isset($data['product_name']) && (empty($data['product_name']) || !is_string($data['product_name']))) {
        $errors[] = "Product name cannot be empty.";
    }
    if (isset($data['quantity']) && $data['quantity'] < 0) {
        $errors[] = "Quantity must be a non-negative integer.";
    }
    if (isset($data['price']) && $data['price'] < 0) {
        $errors[] = "Price must be a non-negative number.";
    }

    if (!empty($errors)) {
        throw new InvalidArgumentException(implode(', ', $errors));
    }

    return $data;
}

$processing_result_message = '';
$display_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serialized_input = $_POST['serialized_data'] ?? '';

    log_event("Received serialized data: " . substr($serialized_input, 0, 200) . (strlen($serialized_input) > 200 ? '...' : ''));

    try {
        $deserialized_data = json_decode($serialized_input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            log_event('Deserialization failed: ' . json_last_error_msg(), 'ERROR');
            throw new Exception("Data format error.");
        }

        if (!is_array($deserialized_data)) {
            log_event("Deserialized data is not an array: " . var_export($deserialized_data, true), 'ERROR');
            throw new Exception("Invalid data structure.");
        }

        $validated_data = validate_inventory_item($deserialized_data);

        $display_data = $validated_data;
        $processing_result_message = 'Data successfully processed!';
        log_event("Data processed successfully: " . json_encode($validated_data));

    } catch (InvalidArgumentException $e) {
        log_event("Validation failed: " . $e->getMessage() . " Raw data: " . $serialized_input, 'WARNING');
        $processing_result_message = 'Data validation failed. Please check your input.';
    } catch (Exception $e) {
        log_event("Data processing error: " . $e->getMessage() . " Raw data: " . $serialized_input, 'ERROR');
        $processing_result_message = 'An error occurred during data processing. Please try again or contact support.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Data Processing Module</title>
</head>
<body>
    <h1>Inventory Data Processing Module</h1>

    <?php if ($processing_result_message): ?>
        <p><strong><?php echo htmlspecialchars($processing_result_message); ?></strong></p>
    <?php endif; ?>

    <?php if ($display_data): ?>
        <h2>Processed Inventory Item:</h2>
        <ul>
            <?php foreach ($display_data as $key => $value): ?>
                <li><strong><?php echo htmlspecialchars($key); ?>:</strong> <?php echo htmlspecialchars(is_array($value) ? json_encode($value) : $value); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>Submit Serialized Inventory Data</h2>
    <form method="POST">
        <label for="serialized_data">Serialized Data (JSON format expected):</label><br>
        <textarea id="serialized_data" name="serialized_data" rows="10" cols="80" placeholder='Example: {"product_id":123,"product_name":"Widget A","quantity":100,"price":25.50}'></textarea><br><br>
        <button type="submit">Process Data</button>
    </form>
</body>
</html>
?>