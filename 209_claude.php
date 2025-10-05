**index.html**
html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JSON Processor</title>
</head>
<body>
    <form action="process.php" method="POST">
        <textarea name="json_data" rows="10" cols="50" placeholder="Enter JSON data here..." required></textarea>
        <br>
        <input type="submit" value="Process JSON">
    </form>
</body>
</html>


**process.php**
<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['json_data']) || empty(trim($_POST['json_data']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No JSON data provided']);
    exit;
}

$json_string = trim($_POST['json_data']);

if (strlen($json_string) > 10000) {
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'JSON data too large']);
    exit;
}

$decoded_data = json_decode($json_string, false);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON format: ' . json_last_error_msg()]);
    exit;
}

if (!is_object($decoded_data) || !property_exists($decoded_data, 'user_id')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Property user_id not found in JSON object']);
    exit;
}

$user_id = htmlspecialchars($decoded_data->user_id, ENT_QUOTES, 'UTF-8');

echo json_encode(['success' => true, 'message' => 'User ID: ' . $user_id]);
?>