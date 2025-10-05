<?php
// config.php
define('BASE_DIR', __DIR__);
define('LOG_FILE_PATH', BASE_DIR . '/logs/data_processing.log');

?>
<?php
// classes/SafeDeserializer.php
class SafeDeserializer
{
    /**
     * Safely deserializes a string into a PHP array.
     * Prioritizes JSON. If it appears to be PHP serialized, it strictly
     * disallows objects and attempts to sanitize by a JSON roundtrip.
     *
     * @param string $data The serialized data string.
     * @return array|null The deserialized array, or null if deserialization failed or was deemed unsafe.
     */
    public static function deserialize(string $data): ?array
    {
        // 1. Attempt JSON deserialization first
        $decodedJson = json_decode($data, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedJson)) {
            return $decodedJson;
        }

        // 2. If not JSON, attempt a restricted PHP unserialization.
        //    Strictly disallow any objects (O:) or custom objects (C:).
        //    This is a basic, but crucial, check to prevent common object injection attacks.
        if (strpos($data, 'O:') !== false || strpos($data, 'C:') !== false) {
            // Object detected, reject immediately. Log this attempt.
            error_log("Security alert: Attempted to deserialize PHP serialized data containing objects (O: or C: detected). Rejected.");
            return null;
        }

        // Attempt PHP unserialization for arrays/scalars only.
        // Even with the O:/C: check, PHP's unserialize can still be dangerous if not fully controlled.
        // The following trick (unserialize then json_encode/json_decode) attempts to sanitize
        // any complex types (like references) into a simple, safe array/scalar structure.
        // It also ensures any internal PHP objects that might somehow be created (e.g., via
        // malformed data not explicitly containing O: or C:) are flattened.
        try {
            // Temporarily suppress errors/warnings from unserialize
            set_error_handler(function ($errno, $errstr) {
                // Log the warning but prevent it from being output to the client
                error_log("PHP unserialize warning (suppressed): " . $errstr);
                return true; // Handle the error, prevent PHP's default error handler
            });

            $unserializedData = unserialize($data);
            restore_error_handler(); // Restore original error handler

            if ($unserializedData !== false) {
                // Re-encode to JSON and then decode to strip any lingering PHP-specific
                // objects, references, or other potentially unsafe structures,
                // forcing the data into a pure array/scalar representation.
                $jsonEncodedClean = json_encode($unserializedData);
                if ($jsonEncodedClean !== false) {
                    $finalCleanData = json_decode($jsonEncodedClean, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($finalCleanData)) {
                        return $finalCleanData;
                    }
                }
            }
        } catch (Throwable $e) {
            // Catch any unexpected exceptions during unserialization process
            error_log("SafeDeserializer caught exception during PHP unserialization attempt: " . $e->getMessage());
        }

        return null; // Deserialization failed or data was deemed unsafe
    }
}

?>
<?php
// classes/InventoryItemValidator.php
class InventoryItemValidator
{
    private const SCHEMA = [
        'sku' => ['type' => 'string', 'required' => true, 'min_length' => 1, 'max_length' => 50],
        'name' => ['type' => 'string', 'required' => true, 'min_length' => 1, 'max_length' => 255],
        'quantity' => ['type' => 'integer', 'required' => true, 'min' => 0, 'max' => 1000000],
        'price' => ['type' => 'float', 'required' => true, 'min' => 0.01, 'max' => 100000.00],
        'category' => ['type' => 'string', 'required' => false, 'min_length' => 1, 'max_length' => 50],
        'location' => ['type' => 'string', 'required' => false, 'min_length' => 1, 'max_length' => 50],
    ];

    /**
     * Validates an array of inventory items against a predefined schema.
     *
     * @param array $data The deserialized array of inventory items.
     * @return bool True if all items are valid, false otherwise.
     */
    public static function validate(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        // Ensure the top-level array contains actual items (e.g., it's not just an empty array or scalar).
        // It expects an array of arrays (items).
        if (!is_array(reset($data))) {
            return false;
        }

        foreach ($data as $item) {
            if (!is_array($item)) {
                return false; // Each item in the batch must be an array
            }

            foreach (self::SCHEMA as $field => $rules) {
                $value = $item[$field] ?? null;

                // Check required fields
                if ($rules['required'] && ($value === null || $value === '')) {
                    return false;
                }

                // If not required and not provided, skip further validation for this field
                if (!$rules['required'] && ($value === null || $value === '')) {
                    continue;
                }

                // Type and content validation
                switch ($rules['type']) {
                    case 'string':
                        if (!is_string($value)) {
                            return false;
                        }
                        if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
                            return false;
                        }
                        if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                            return false;
                        }
                        break;
                    case 'integer':
                        // Use filter_var for robust integer validation. It handles string-to-int conversion.
                        $options = [];
                        if (isset($rules['min'])) {
                            $options['min_range'] = $rules['min'];
                        }
                        if (isset($rules['max'])) {
                            $options['max_range'] = $rules['max'];
                        }
                        if (filter_var($value, FILTER_VALIDATE_INT, ['options' => $options]) === false) {
                            return false;
                        }
                        // Ensure strict type after filter_var if input was string (e.g., '100' becomes 100)
                        // If it was already an int, filter_var keeps it, but this double check is for robustness.
                        if (!is_int($value) && !is_string($value)) { // Filter_var can accept string ints, floats, but we want integer ultimately
                             return false;
                        }
                        // Check range again if it was a string that passed filter_var
                        $intValue = (int) $value;
                        if (isset($rules['min']) && $intValue < $rules['min']) {
                            return false;
                        }
                        if (isset($rules['max']) && $intValue > $rules['max']) {
                            return false;
                        }
                        break;
                    case 'float':
                        // Use filter_var for robust float validation.
                        if (filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
                            return false;
                        }
                        $floatValue = (float) $value;
                        if (isset($rules['min']) && $floatValue < $rules['min']) {
                            return false;
                        }
                        if (isset($rules['max']) && $floatValue > $rules['max']) {
                            return false;
                        }
                        break;
                    // Add more types (e.g., 'boolean', 'array') as needed
                }
            }
            // Strict schema validation: Check for unknown fields
            foreach ($item as $field => $value) {
                if (!array_key_exists($field, self::SCHEMA)) {
                    return false; // Unknown field found
                }
            }
        }
        return true; // All items passed validation
    }
}

?>
<?php
// handlers/process_serialized_data.php
require_once __DIR__ . '/../config.php';
require_once BASE_DIR . '/classes/SafeDeserializer.php';
require_once BASE_DIR . '/classes/InventoryItemValidator.php';

header('Content-Type: application/json');

/**
 * Logs data processing attempts to a secure log file.
 *
 * @param string $message The message to log.
 * @param string $source An identifier for the data source (e.g., IP address, API key).
 * @return void
 */
function logProcessingAttempt(string $message, string $source = 'unknown'): void
{
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = sprintf("[%s] [Source: %s] %s%s", $timestamp, $source, $message, PHP_EOL);
    
    // Ensure the log directory exists and is writable
    $logDir = dirname(LOG_FILE_PATH);
    if (!is_dir($logDir)) {
        // Attempt to create the directory with appropriate permissions
        // Note: In production, ensure these permissions are tightly controlled.
        mkdir($logDir, 0755, true); 
    }
    
    // Use FILE_APPEND and LOCK_EX for safe, concurrent logging
    file_put_contents(LOG_FILE_PATH, $logEntry, FILE_APPEND | LOCK_EX);
}

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logProcessingAttempt("Invalid request method: " . $_SERVER['REQUEST_METHOD'], $_SERVER['REMOTE_ADDR']);
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

// Get the serialized data from the POST request
$serializedData = $_POST['serialized_data'] ?? '';

if (empty($serializedData)) {
    logProcessingAttempt("Received empty serialized data.", $_SERVER['REMOTE_ADDR']);
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Empty data received.']);
    exit;
}

// Identify the source for logging purposes
$sourceIdentifier = $_SERVER['REMOTE_ADDR'] . ' - ' . ($_SERVER['HTTP_REFERER'] ?? 'N/A');
logProcessingAttempt("Attempting to process data.", $sourceIdentifier);

// 1. Deserialize the data using the safe deserializer
$deserialized = SafeDeserializer::deserialize($serializedData);

if ($deserialized === null) {
    logProcessingAttempt("Failed to deserialize data or data deemed unsafe.", $sourceIdentifier);
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid or unsafe data format.']);
    exit;
}

// 2. Validate the deserialized data against the inventory item schema
if (!InventoryItemValidator::validate($deserialized)) {
    logProcessingAttempt("Data validation failed for deserialized payload.", $sourceIdentifier);
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Data validation failed.']);
    exit;
}

// 3. Process the data (simulate database update)
// In a real application, you would perform database inserts/updates here
// using the $deserialized and validated array.
// For this example, we'll just log the successful processing.
$processedItemCount = count($deserialized);
$skuList = 'N/A';
if ($processedItemCount > 0 && isset($deserialized[0]['sku'])) {
    $skuList = implode(', ', array_column($deserialized, 'sku'));
}

logProcessingAttempt("Successfully deserialized and validated $processedItemCount inventory items (SKUs: $skuList). Simulating database update.", $sourceIdentifier);

// Simulate a successful database update response
$response = [
    'status' => 'success',
    'message' => 'Data processed and inventory updated successfully.',
    'processed_items_count' => $processedItemCount,
    'first_item_sku' => $deserialized[0]['sku'] ?? null,
    // Do NOT include sensitive or verbose deserialized data in the public response
];

http_response_code(200); // OK
echo json_encode($response);
exit;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Serialized Inventory Data</title>
</head>
<body>
    <h1>Submit Serialized Inventory Data</h1>
    <p>Please enter the serialized inventory data (JSON or restricted PHP serialize format) below. The system will attempt to deserialize and validate it.</p>
    <form action="../handlers/process_serialized_data.php" method="POST">
        <label for="serialized_data">Serialized Data:</label><br>
        <textarea id="serialized_data" name="serialized_data" rows="15" cols="80" required>
[
    {
        "sku": "ITEM001",
        "name": "Widget A",
        "quantity": 100,
        "price": 12.99,
        "category": "Electronics"
    },
    {
        "sku": "ITEM002",
        "name": "Gadget B",
        "quantity": 250,
        "price": 5.50
    },
    {
        "sku": "ITEM003",
        "name": "Doodad C",
        "quantity": 50,
        "price": 25.00,
        "location": "Warehouse 1"
    }
]
        </textarea><br><br>
        <button type="submit">Submit Data</button>
    </form>

    <h2>Example PHP Serialized Data (Non-Object)</h2>
    <p>This example demonstrates a PHP serialized array. If objects are present (e.g., "O:"), it will be rejected.</p>
    <textarea rows="5" cols="80" readonly>
a:2:{i:0;a:4:{s:3:"sku";s:7:"PHPITEM";s:4:"name";s:10:"PHP Widget";s:8:"quantity";i:10;s:5:"price";d:99.99;}i:1;a:4:{s:3:"sku";s:8:"PHPITEM2";s:4:"name";s:11:"PHP Gadget";s:8:"quantity";i:20;s:5:"price";d:19.99;}}
    </textarea>

    <h2>Example Invalid Data (Object in PHP Serialize)</h2>
    <p>This example contains a serialized PHP object, which will be rejected by the system for security reasons.</p>
    <textarea rows="5" cols="80" readonly>
O:8:"MyObject":1:{s:4:"data";s:12:"Malicious Data";}
    </textarea>

    <h2>Example Invalid Data (Missing Required Field)</h2>
    <p>This example is missing the 'quantity' field for one item, which will cause validation to fail.</p>
    <textarea rows="5" cols="80" readonly>
[
    {
        "sku": "ITEM004",
        "name": "Incomplete Item",
        "price": 10.00
    }
]
    </textarea>

</body>
</html>
?>