`index.html`
html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit JSON Data</title>
</head>
<body>
    <h1>Submit JSON Data for Processing</h1>
    <form action="process.php" method="POST">
        <label for="json_data">Enter JSON Data (e.g., {"user_id": 12345, "username": "testuser"}):</label><br>
        <textarea id="json_data" name="json_data" rows="10" cols="50">{
    "user_id": 12345,
    "username": "example_user",
    "email": "user@example.com"
}</textarea><br><br>
        <input type="submit" value="Process Data">
    </form>
</body>
</html>

`process.php`
<?php
const LOG_FILE = __DIR__ . '/data_processing.log';
const USER_ID_MIN_VALUE = 1;
const USER_ID_MAX_VALUE = 99999;

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'An unexpected error occurred.',
];

function log_event(string $message, string $level = 'INFO', array $context = []): void
{
    $timestamp = date('Y-m-d H:i:s');
    $source_ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $log_entry = "[{$timestamp}] [{$level}] [IP: {$source_ip}] - {$message}";

    if (!empty($context)) {
        $log_entry .= " Context: " . json_encode($context);
    }
    $log_entry .= PHP_EOL;

    if (file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX) === false) {
        error_log("Failed to write to log file: " . LOG_FILE . " - " . $log_entry);
    }
}

function safe_json_decode(string $json_string)
{
    $decoded_data = json_decode($json_string, false, 512, JSON_BIGINT_AS_STRING);

    if (json_last_error() !== JSON_ERROR_NONE) {
        log_event('JSON decoding failed.', 'WARNING', [
            'error' => json_last_error_msg(),
            'raw_input_start' => substr($json_string, 0, 200)
        ]);
        return false;
    }
    return $decoded_data;
}

$source_ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

if (!isset($_POST['json_data']) || !is_string($_POST['json_data'])) {
    $response['message'] = 'Invalid request: JSON data is missing or not a string.';
    log_event($response['message'], 'ERROR');
    echo json_encode($response);
    exit;
}

$raw_json_input = $_POST['json_data'];

$data_object = safe_json_decode($raw_json_input);

if ($data_object === false) {
    $response['message'] = 'Failed to parse JSON data. Please ensure it is valid JSON.';
    echo json_encode($response);
    exit;
}

if (!is_object($data_object)) {
    $response['message'] = 'Invalid JSON structure: Expected an object.';
    log_event($response['message'], 'ERROR', [
        'decoded_type' => gettype($data_object),
        'raw_input_start' => substr($raw_json_input, 0, 200)
    ]);
    echo json_encode($response);
    exit;
}

if (!property_exists($data_object, 'user_id')) {
    $response['message'] = 'Invalid data schema: "user_id" property is missing.';
    log_event($response['message'], 'ERROR', [
        'decoded_object' => print_r($data_object, true),
        'raw_input_start' => substr($raw_json_input, 0, 200)
    ]);
    echo json_encode($response);
    exit;
}

$user_id_raw = $data_object->user_id;
$user_id = null;

if (is_int($user_id_raw)) {
    $user_id = $user_id_raw;
} elseif (is_string($user_id_raw) && ctype_digit($user_id_raw)) {
    $user_id = (int)$user_id_raw;
}

if (!is_int($user_id) || $user_id < USER_ID_MIN_VALUE || $user_id > USER_ID_MAX_VALUE) {
    $response['message'] = "Invalid data content: \"user_id\" must be an integer between " . USER_ID_MIN_VALUE . " and " . USER_ID_MAX_VALUE . ".";
    log_event($response['message'], 'ERROR', [
        'user_id_value' => $user_id_raw,
        'user_id_type' => gettype($user_id_raw)
    ]);
    echo json_encode($response);
    exit;
}

$response['success'] = true;
$response['message'] = "Data successfully processed for User ID: {$user_id}.";
$response['data'] = ['user_id' => $user_id];

log_event("Successfully processed user_id: {$user_id}.", 'INFO');

echo json_encode($response);
exit;