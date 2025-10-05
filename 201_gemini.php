html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Serialized Data</title>
</head>
<body>
    <h1>Submit JSON Data</h1>
    <form action="process.php" method="POST">
        <label for="json_data">Enter JSON Data:</label><br>
        <textarea id="json_data" name="json_data" rows="10" cols="50">
{"name": "John Doe", "id": 123, "email": "john.doe@example.com"}
        </textarea><br><br>
        <button type="submit">Process Data</button>
    </form>
</body>
</html>

<?php

error_reporting(0);
ini_set('display_errors', 'Off');

define('LOG_FILE', __DIR__ . '/data.log');
define('EXPECTED_SCHEMA', [
    'name' => 'string',
    'id' => 'integer',
]);

function log_event(string $message, string $level = 'INFO'): void
{
    $timestamp = date('Y-m-d H:i:s');
    $source_ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $log_entry = sprintf("[%s] [%s] [%s] %s\n", $timestamp, $source_ip, $level, $message);
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
}

function display_error(string $message = 'An unexpected error occurred.'): void
{
    log_event("Processing failed: " . $message, 'ERROR');
    echo 'Data processing failed. Please check your input.';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_event("Invalid request method: " . $_SERVER['REQUEST_METHOD'], 'WARNING');
    display_error('Invalid request method.');
}

if (!isset($_POST['json_data'])) {
    log_event("Missing 'json_data' in POST request.", 'WARNING');
    display_error('Missing required data.');
}

$json_string = $_POST['json_data'];

if (!is_string($json_string) || empty($json_string)) {
    log_event("Invalid or empty 'json_data' type: " . gettype($json_string), 'WARNING');
    display_error('Invalid JSON input.');
}

$data_object = json_decode($json_string, false, 512, JSON_BIGINT_AS_STRING);

if (json_last_error() !== JSON_ERROR_NONE) {
    $json_error_message = json_last_error_msg();
    log_event("JSON deserialization failed: " . $json_error_message, 'WARNING');
    display_error('Invalid JSON format.');
}

if (!is_object($data_object)) {
    log_event("Deserialized data is not an object. Type: " . gettype($data_object), 'WARNING');
    display_error('Invalid data structure. Expected an object.');
}

foreach (EXPECTED_SCHEMA as $property => $expected_type) {
    if (!property_exists($data_object, $property)) {
        log_event("Schema validation failed: Missing property '" . $property . "'.", 'WARNING');
        display_error('Invalid data schema. Missing required property.');
    }

    $actual_value = $data_object->$property;
    $actual_type = gettype($actual_value);

    if ($actual_type !== $expected_type) {
        log_event("Type validation failed for property '" . $property . "'. Expected: " . $expected_type . ", Actual: " . $actual_type . ".", 'WARNING');
        display_error('Invalid data type for a property.');
    }

    // Additional content validation based on property name
    if ($property === 'name' && empty($actual_value)) {
        log_event("Content validation failed for 'name': Value is empty.", 'WARNING');
        display_error('Name cannot be empty.');
    }
    if ($property === 'id' && (!is_int($actual_value) || $actual_value <= 0)) {
        log_event("Content validation failed for 'id': Value is not a positive integer.", 'WARNING');
        display_error('ID must be a positive integer.');
    }
}

// All validations passed. Process the data.
$display_property = $data_object->name;

log_event("Successfully processed data. Displayed property: '" . $display_property . "'. Full data: " . $json_string, 'INFO');

echo 'Successfully processed data.<br>';
echo 'Name: ' . htmlspecialchars($display_property, ENT_QUOTES, 'UTF-8');
?>