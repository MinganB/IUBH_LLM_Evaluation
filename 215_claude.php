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
        <textarea name="json_data" rows="10" cols="50" placeholder="Enter JSON data here..." required></textarea>
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

if (!isset($_POST['json_data']) || empty(trim($_POST['json_data']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No JSON data provided']);
    exit;
}

$json_string = trim($_POST['json_data']);
$json_string = filter_var($json_string, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

$decoded_data = json_decode($json_string, false, 10, JSON_THROW_ON_ERROR);

if ($decoded_data === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON format']);
    exit;
}

if (!isset($decoded_data->user_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Required property user_id not found']);
    exit;
}

$user_id = htmlspecialchars($decoded_data->user_id, ENT_QUOTES, 'UTF-8');

if (!is_numeric($user_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user_id format']);
    exit;
}

echo json_encode([
    'success' => true, 
    'message' => 'Data processed successfully',
    'user_id' => $user_id
]);
?>