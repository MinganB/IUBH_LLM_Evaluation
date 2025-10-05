**index.html**

html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Data Processor</title>
</head>
<body>
    <form action="process.php" method="POST">
        <label for="json_data">JSON Data:</label>
        <textarea name="json_data" id="json_data" rows="10" cols="50" required></textarea>
        <button type="submit">Process Data</button>
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
$decoded_data = json_decode($json_string, false);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON format: ' . json_last_error_msg()]);
    exit;
}

if (!is_object($decoded_data) || !property_exists($decoded_data, 'user_id')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required property: user_id']);
    exit;
}

$user_id = $decoded_data->user_id;

echo "<p>User ID: " . htmlspecialchars($user_id) . "</p>";

echo json_encode(['success' => true, 'message' => 'Data processed successfully']);
?>