<?php
define('LOG_FILE', __DIR__ . '/serialized_data_processing.log');
define('LOG_MASK', 0600);
define('MAX_DATA_SIZE', 1024 * 10);

function log_activity(string $message, string $level = 'INFO'): void
{
    $timestamp = date('Y-m-d H:i:s');
    $source_ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $log_entry = sprintf("[%s] [%s] [%s] %s\n", $timestamp, $source_ip, $level, $message);

    $file_handle = @fopen(LOG_FILE, 'a');
    if ($file_handle === false) {
        error_log("CRITICAL: Failed to open log file '" . LOG_FILE . "' for writing. Message: " . $log_entry, 0);
        return;
    }

    if (!file_exists(LOG_FILE) || fileperms(LOG_FILE) !== (LOG_MASK | 0100000)) {
        @chmod(LOG_FILE, LOG_MASK);
    }

    if (@fwrite($file_handle, $log_entry) === false) {
        error_log("CRITICAL: Failed to write to log file '" . LOG_FILE . "'. Message: " . $log_entry, 0);
    }

    @fclose($file_handle);
}

function safe_unserialize_and_validate(string $serialized_data)
{
    if (empty($serialized_data) || !is_string($serialized_data)) {
        log_activity("Attempted deserialization with invalid input type or empty string.", "WARNING");
        return false;
    }

    if (strlen($serialized_data) > MAX_DATA_SIZE) {
        log_activity("Attempted deserialization with data exceeding maximum allowed size.", "WARNING");
        return false;
    }

    $unserialized_result = @unserialize($serialized_data, ['allowed_classes' => false]);

    if ($unserialized_result === false && $serialized_data !== 'b:0;') {
        $last_error = error_get_last();
        if (isset($last_error['type']) && ($last_error['type'] === E_NOTICE || $last_error['type'] === E_WARNING)) {
            log_activity("Deserialization failed: " . $last_error['message'] . " (Input: " . substr($serialized_data, 0, 200) . "...)", "ERROR");
            return false;
        }
    }

    $is_safe_data = function ($data) use (&$is_safe_data) {
        if (is_scalar($data)) {
            return true;
        }
        if (is_array($data)) {
            foreach ($data as $item) {
                if (!$is_safe_data($item)) {
                    return false;
                }
            }
            return true;
        }
        return false;
    };

    if (!$is_safe_data($unserialized_result)) {
        log_activity("Deserialized data contains disallowed types (e.g., objects, resources) after initial `allowed_classes` check. Input: " . substr($serialized_data, 0, 200) . "...", "WARNING");
        return false;
    }

    return $unserialized_result;
}

$display_result = '';
$error_message = '';
$raw_serialized_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['serialized_data'])) {
    $raw_serialized_input = $_POST['serialized_data'];
    log_activity("Received POST request for deserialization. Data length: " . strlen($raw_serialized_input) . ", Raw data: " . substr($raw_serialized_input, 0, 500) . "...");

    $processed_data = safe_unserialize_and_validate($raw_serialized_input);

    if ($processed_data !== false) {
        log_activity("Data successfully deserialized and validated. Result: " . print_r($processed_data, true), "INFO");
        ob_start();
        print_r($processed_data);
        $display_result = '<pre>' . htmlspecialchars(ob_get_clean()) . '</pre>';
    } else {
        log_activity("Data deserialization or validation failed for input: " . substr($raw_serialized_input, 0, 500) . "...", "ERROR");
        $error_message = 'An error occurred while processing your data. Please check the input and try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serialized Data Processor</title>
</head>
<body>
    <h1>Serialized Data Processor</h1>

    <?php if (!empty($error_message)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="serialized_data">Enter Serialized Data:</label><br>
        <textarea id="serialized_data" name="serialized_data" rows="10" cols="80" required><?php echo htmlspecialchars($raw_serialized_input); ?></textarea><br><br>
        <button type="submit">Process Data</button>
    </form>

    <?php if (!empty($display_result)): ?>
        <h2>Processed Result:</h2>
        <?php echo $display_result; ?>
    <?php endif; ?>
</body>
</html>
?>