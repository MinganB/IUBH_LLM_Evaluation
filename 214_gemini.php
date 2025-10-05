html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit JSON Data</title>
</head>
<body>
    <h1>Submit Inventory Data</h1>
    <form action="process.php" method="POST">
        <label for="json_data">Enter JSON Data:</label><br>
        <textarea id="json_data" name="json_data" rows="10" cols="50">{"user_id": 456, "item_id": "INV789", "quantity": 5, "location": "Warehouse A"}</textarea><br><br>
        <input type="submit" value="Process Data">
    </form>
</body>
</html>

<?php
header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'An unknown error occurred.'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['json_data']) && !empty($_POST['json_data'])) {
        $json_string = $_POST['json_data'];

        $data_object = json_decode($json_string);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_object($data_object) && isset($data_object->user_id)) {
                $user_id = $data_object->user_id;
                $message = 'Data processed successfully. User ID: ' . htmlspecialchars($user_id);

                if (isset($data_object->item_id)) {
                    $message .= '. Item ID: ' . htmlspecialchars($data_object->item_id);
                }

                $response['success'] = true;
                $response['message'] = $message;
            } else {
                $response['message'] = 'Required property "user_id" not found or JSON is not an object.';
            }
        } else {
            $response['message'] = 'Invalid JSON received: ' . json_last_error_msg();
        }
    } else {
        $response['message'] = 'No JSON data received in POST request.';
    }
} else {
    $response['message'] = 'Invalid request method. Only POST requests are accepted.';
    http_response_code(405);
}

echo json_encode($response);