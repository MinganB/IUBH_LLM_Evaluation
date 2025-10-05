<?php

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'An unknown error occurred.'
];

// 1. Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Only POST requests are allowed.';
    echo json_encode($response);
    exit();
}

// Check if 'jsonData' is present in the POST request
if (!isset($_POST['jsonData'])) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Required "jsonData" field is missing.';
    echo json_encode($response);
    exit();
}

$jsonString = $_POST['jsonData'];

// 2. Use the json_decode() function to deserialize the string
$data = json_decode($jsonString);

// Handle JSON decoding errors
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Invalid JSON format: ' . json_last_error_msg();
    echo json_encode($response);
    exit();
}

// Ensure the decoded data is an object
if (!is_object($data)) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Decoded JSON is not an object.';
    echo json_encode($response);
    exit();
}

// 3. Access a specific property from the resulting object, e.g., user_id
if (!isset($data->user_id)) {
    http_response_code(400); // Bad Request
    $response['message'] = 'The "user_id" property is missing from the JSON data.';
    echo json_encode($response);
    exit();
}

$userId = $data->user_id;

// Basic validation for user_id (e.g., ensure it's an integer)
if (!is_int($userId) && !ctype_digit(strval($userId))) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Invalid "user_id" format. Must be an integer.';
    echo json_encode($response);
    exit();
}

// At this point, $userId is safely extracted and validated.
// In a production environment, you would now use $userId and other data
// to interact with your database (e.g., update inventory, log activity).
// For this example, we'll just display it as part of the response.

// 4. Display the value of the accessed property on the page.
//    (In an API context, this means including it in the JSON response
//     so the client can display it.)

// 5. Return a JSON response with a boolean success key and a string message key.
$response['success'] = true;
$response['message'] = 'User ID ' . htmlspecialchars($userId, ENT_QUOTES, 'UTF-8') . ' successfully processed.';
$response['processed_user_id'] = $userId; // Optionally return the processed ID

echo json_encode($response);
exit();

html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management Input</title>
</head>
<body>

    <h1>Submit Inventory Data</h1>

    <form action="process.php" method="POST">
        <label for="jsonData">Enter JSON-encoded inventory data:</label><br>
        <textarea id="jsonData" name="jsonData" rows="10" cols="50">
{
    "user_id": 12345,
    "product_sku": "ABC-123",
    "quantity": 100,
    "operation": "add",
    "timestamp": "2023-10-27T10:00:00Z"
}
        </textarea><br><br>
        <input type="submit" value="Process Data">
    </form>

</body>
</html>
?>