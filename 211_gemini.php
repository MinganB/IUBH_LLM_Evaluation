`index.html`

html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Data Submission</title>
</head>
<body>
    <h1>Submit Inventory Data</h1>
    <form action="process.php" method="POST">
        <label for="jsonData">Enter JSON Data (e.g., {"user_id": 101, "item_name": "Laptop", "quantity": 1, "price": 1200.00}):</label><br>
        <textarea id="jsonData" name="jsonData" rows="10" cols="50">{"user_id": 101, "item_name": "Sample Product", "quantity": 5, "price": 29.99}</textarea><br><br>
        <input type="submit" value="Submit Data">
    </form>
</body>
</html>


`process.php`

<?php
header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'An unexpected error occurred.'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['jsonData']) && !empty($_POST['jsonData'])) {
        $jsonString = $_POST['jsonData'];
        $data = json_decode($jsonString);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($data->user_id)) {
                $userId = $data->user_id;
                $response['success'] = true;
                $response['message'] = "Data for User ID: {$userId} processed successfully.";
            } else {
                $response['message'] = 'Error: "user_id" property not found in the provided JSON data.';
            }
        } else {
            $response['message'] = 'Error: Invalid JSON data received. ' . json_last_error_msg();
        }
    } else {
        $response['message'] = 'Error: No JSON data received in the request.';
    }
} else {
    $response['message'] = 'Error: Only POST requests are accepted.';
}

echo json_encode($response);
exit();