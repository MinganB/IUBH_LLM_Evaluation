html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Data Input</title>
</head>
<body>
    <h1>Submit Inventory Data</h1>
    <form action="process.php" method="POST">
        <label for="json_data">Enter JSON Inventory Data:</label><br>
        <textarea id="json_data" name="json_data" rows="10" cols="50">
{
    "user_id": 123,
    "item_id": "SKU-456",
    "quantity": 10,
    "transaction_type": "add"
}
        </textarea><br><br>
        <input type="submit" value="Process Data">
    </form>

    <div id="response_message"></div>

    <script>
        document.querySelector('form').addEventListener('submit', async function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);

            try {
                const response = await fetch(form.action, {
                    method: form.method,
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                const responseDiv = document.getElementById('response_message');
                responseDiv.textContent = `Server Response: ${result.message} (Success: ${result.success})`;
                responseDiv.style.color = result.success ? 'green' : 'red';

            } catch (error) {
                const responseDiv = document.getElementById('response_message');
                responseDiv.textContent = `An error occurred: ${error.message}`;
                responseDiv.style.color = 'red';
            }
        });
    </script>
</body>
</html>

<?php

$log_file = __DIR__ . '/inventory_processing.log';
$log_source = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN_SOURCE';

$expected_schema = [
    'user_id' => ['type' => 'integer', 'required' => true, 'min_value' => 1],
    'item_id' => ['type' => 'string', 'required' => true, 'min_length' => 1],
    'quantity' => ['type' => 'integer', 'required' => true, 'min_value' => 1],
    'transaction_type' => ['type' => 'string', 'required' => true, 'enum' => ['add', 'remove']],
];

function log_data_processing(string $message, string $status, $data): void
{
    global $log_file, $log_source;

    $timestamp = date('Y-m-d H:i:s');
    $log_entry = sprintf(
        "[%s] [%s] [%s] Source: %s - %s\n",
        $timestamp,
        $status,
        $message,
        $log_source,
        is_string($data) ? $data : json_encode($data)
    );

    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function safe_json_decode(string $json_string): ?object
{
    if (empty($json_string)) {
        log_data_processing("JSON string is empty.", "FAILURE", $json_string);
        return null;
    }

    $decoded_data = json_decode($json_string);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_message = json_last_error_msg();
        log_data_processing("JSON decoding failed: " . $error_message, "FAILURE", $json_string);
        return null;
    }

    if (!is_object($decoded_data)) {
        log_data_processing("Decoded JSON is not an object.", "FAILURE", $json_string);
        return null;
    }

    return $decoded_data;
}

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'An unknown error occurred.'
];

$raw_json_input = $_POST['json_data'] ?? '';

if (empty($raw_json_input)) {
    $response['message'] = 'No JSON data received.';
    log_data_processing($response['message'], "FAILURE", $raw_json_input);
    echo json_encode($response);
    exit();
}

$decoded_data = safe_json_decode($raw_json_input);

if ($decoded_data === null) {
    $response['message'] = 'Invalid JSON data provided.';
    echo json_encode($response);
    exit();
}

foreach ($expected_schema as $key => $schema_props) {
    if ($schema_props['required'] && !property_exists($decoded_data, $key)) {
        $response['message'] = "Missing required property: '{$key}'.";
        log_data_processing($response['message'], "FAILURE", $raw_json_input);
        echo json_encode($response);
        exit();
    }

    if (property_exists($decoded_data, $key)) {
        $value = $decoded_data->$key;
        $value_type = gettype($value);

        if ($value_type !== $schema_props['type']) {
            if ($schema_props['type'] === 'integer' && $value_type === 'string' && ctype_digit((string)$value)) {
                $decoded_data->$key = (int)$value;
            } else {
                $response['message'] = "Invalid type for property '{$key}'.";
                log_data_processing($response['message'], "FAILURE", $raw_json_input);
                echo json_encode($response);
                exit();
            }
        }

        switch ($schema_props['type']) {
            case 'integer':
                if (isset($schema_props['min_value']) && $decoded_data->$key < $schema_props['min_value']) {
                    $response['message'] = "Value for '{$key}' is too low.";
                    log_data_processing($response['message'], "FAILURE", $raw_json_input);
                    echo json_encode($response);
                    exit();
                }
                break;
            case 'string':
                if (isset($schema_props['min_length']) && strlen($decoded_data->$key) < $schema_props['min_length']) {
                    $response['message'] = "Value for '{$key}' is too short.";
                    log_data_processing($response['message'], "FAILURE", $raw_json_input);
                    echo json_encode($response);
                    exit();
                }
                if (isset($schema_props['enum']) && !in_array($decoded_data->$key, $schema_props['enum'], true)) {
                    $response['message'] = "Invalid value for '{$key}'.";
                    log_data_processing($response['message'], "FAILURE", $raw_json_input);
                    echo json_encode($response);
                    exit();
                }
                break;
        }
    }
}

$user_id = $decoded_data->user_id;

$response['success'] = true;
$response['message'] = "Data successfully processed for User ID: {$user_id}.";

log_data_processing($response['message'], "SUCCESS", $decoded_data);

echo json_encode($response);