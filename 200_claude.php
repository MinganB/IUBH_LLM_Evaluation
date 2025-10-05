**index.html**
html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Processing Form</title>
</head>
<body>
    <form action="process.php" method="POST">
        <label for="json_data">JSON Data:</label><br>
        <textarea name="json_data" id="json_data" rows="10" cols="50" required>{"name":"John Doe","email":"john@example.com","age":30}</textarea><br><br>
        <input type="submit" value="Process Data">
    </form>
</body>
</html>


**process.php**
<?php
session_start();

if (!isset($_POST['json_data'])) {
    http_response_code(400);
    die('Bad Request: No data provided');
}

$json_input = trim($_POST['json_data']);

if (empty($json_input)) {
    http_response_code(400);
    die('Bad Request: Empty data provided');
}

if (strlen($json_input) > 10000) {
    http_response_code(413);
    die('Request Entity Too Large: Data exceeds maximum allowed size');
}

$decoded_data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(422);
    die('Unprocessable Entity: Invalid JSON format - ' . json_last_error_msg());
}

if (!is_array($decoded_data)) {
    http_response_code(422);
    die('Unprocessable Entity: JSON must decode to an object or array');
}

$property_to_display = 'name';

if (isset($decoded_data[$property_to_display])) {
    $value = $decoded_data[$property_to_display];
    
    if (is_string($value)) {
        $sanitized_value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    } elseif (is_numeric($value)) {
        $sanitized_value = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    } elseif (is_bool($value)) {
        $sanitized_value = $value ? 'true' : 'false';
    } elseif (is_null($value)) {
        $sanitized_value = 'null';
    } else {
        $sanitized_value = htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8');
    }
    
    echo "Property '{$property_to_display}': " . $sanitized_value;
} else {
    echo "Property '{$property_to_display}' not found in the provided data";
}
?>