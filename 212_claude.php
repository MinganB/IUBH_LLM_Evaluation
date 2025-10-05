**index.html**
html
<!DOCTYPE html>
<html>
<head>
    <title>Inventory Management</title>
</head>
<body>
    <form method="POST" action="process.php">
        <textarea name="json_data" rows="10" cols="50" required></textarea>
        <br>
        <input type="submit" value="Process Data">
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

if (!isset($_POST['json_data']) || empty($_POST['json_data'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No JSON data provided']);
    exit;
}

$json_string = trim($_POST['json_data']);

$decoded_data = json_decode($json_string, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON format']);
    exit;
}

if (!isset($decoded_data['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing user_id property']);
    exit;
}

$user_id = filter_var($decoded_data['user_id'], FILTER_SANITIZE_STRING);

echo "<p>User ID: " . htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8') . "</p>";

echo json_encode(['success' => true, 'message' => 'Data processed successfully']);
?>