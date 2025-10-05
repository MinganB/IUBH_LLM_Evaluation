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
        <label for="json_input">Enter JSON-encoded Inventory Data:</label><br>
        <textarea id="json_input" name="json_data" rows="10" cols="50">{"user_id": 12345, "item_sku": "INV-PROD-001", "quantity": 50, "location": "Warehouse A"}</textarea><br><br>
        <input type="submit" value="Process Data">
    </form>
</body>
</html>

<?php
header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Invalid Request.'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['json_data']) && is_string($_POST['json_data'])) {
        $json_string = trim($_POST['json_data']);

        $data = json_decode($json_string);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_object($data) && property_exists($data, 'user_id')) {
                $userId = filter_var($data->user_id, FILTER_VALIDATE_INT);

                if ($userId !== false) {
                    $response['success'] = true;
                    $response['message'] = 'Data processed successfully.';
                    $response['user_id'] = $userId;
                } else {
                    $response['message'] = 'Invalid user_id format. Expected an integer.';
                }
            } else {
                $response['message'] = 'JSON data is missing the "user_id" property or is not a valid object.';
            }
        } else {
            $response['message'] = 'Invalid JSON data provided: ' . json_last_error_msg();
        }
    } else {
        $response['message'] = 'No JSON data received or data is not a string.';
    }
}

echo json_encode($response);
exit;