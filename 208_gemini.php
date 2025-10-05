`index.html`
html
<!DOCTYPE html>
<html>
<head>
    <title>JSON Data Submission</title>
</head>
<body>

    <h1>Submit JSON Data</h1>

    <form id="jsonForm">
        <label for="json_input">Enter JSON data (e.g., {"user_id": 123, "name": "John Doe"}):</label><br>
        <textarea id="json_input" name="json_input" rows="10" cols="50"></textarea><br><br>
        <button type="submit">Submit JSON</button>
    </form>

    <div id="response_area" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc;">
        <h2>Server Response:</h2>
        <pre id="server_message"></pre>
        <pre id="server_success"></pre>
    </div>

    <script>
        document.getElementById('jsonForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const jsonInput = document.getElementById('json_input').value;
            const responseMessageElement = document.getElementById('server_message');
            const responseSuccessElement = document.getElementById('server_success');

            responseMessageElement.textContent = 'Sending data...';
            responseSuccessElement.textContent = '';

            fetch('process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: jsonInput
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                responseMessageElement.textContent = 'Message: ' + data.message;
                responseSuccessElement.textContent = 'Success: ' + data.success;
            })
            .catch(error => {
                responseMessageElement.textContent = 'Error: ' + error.message;
                responseSuccessElement.textContent = 'Success: false';
            });
        });
    </script>

</body>
</html>

`process.php`
<?php
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

$jsonInput = file_get_contents('php://input');

if (empty($jsonInput)) {
    $response['message'] = 'No JSON data received.';
    echo json_encode($response);
    exit;
}

$data = json_decode($jsonInput);

if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Invalid JSON received: ' . json_last_error_msg();
    echo json_encode($response);
    exit;
}

if (is_object($data) && property_exists($data, 'user_id')) {
    $userId = $data->user_id;
    $response['success'] = true;
    $response['message'] = 'Successfully processed data. User ID: ' . $userId;
} else {
    $response['message'] = 'JSON data processed, but "user_id" property not found or data is not an object.';
}

echo json_encode($response);
exit;