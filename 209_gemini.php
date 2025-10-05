<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JSON Data Input</title>
</head>
<body>
    <form id="jsonForm">
        <label for="json_data">Enter JSON data:</label><br>
        <textarea id="json_data" name="json_data" rows="10" cols="50">{"user_id": 123, "name": "John Doe", "email": "john.doe@example.com"}</textarea><br><br>
        <button type="submit">Submit JSON</button>
    </form>
    <div id="response_message"></div>
    <div id="user_id_display"></div>

    <script>
        document.getElementById('jsonForm').addEventListener('submit', async function(event) {
            event.preventDefault();

            const textarea = document.getElementById('json_data');
            const jsonData = textarea.value;
            const responseMessageDiv = document.getElementById('response_message');
            const userIdDisplayDiv = document.getElementById('user_id_display');

            responseMessageDiv.textContent = '';
            userIdDisplayDiv.textContent = '';

            try {
                JSON.parse(jsonData);
            } catch (e) {
                responseMessageDiv.textContent = 'Error: Invalid JSON input.';
                return;
            }

            const formData = new FormData();
            formData.append('json_data', jsonData);

            try {
                const response = await fetch('process.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (result.success) {
                    responseMessageDiv.textContent = 'Success: ' + result.message;
                    if (result.user_id !== undefined) {
                        userIdDisplayDiv.textContent = 'Accessed User ID: ' + result.user_id;
                    }
                } else {
                    responseMessageDiv.textContent = 'Error: ' + result.message;
                }
            } catch (error) {
                responseMessageDiv.textContent = 'Request failed: ' + error.message;
            }
        });
    </script>
</body>
</html>
<?php
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['json_data']) && is_string($_POST['json_data'])) {
        $jsonString = $_POST['json_data'];

        $data = json_decode($jsonString);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_object($data) && property_exists($data, 'user_id')) {
                $userId = $data->user_id;

                $response = [
                    'success' => true,
                    'message' => 'Data successfully processed.',
                    'user_id' => $userId
                ];
                http_response_code(200);
            } else {
                $response['message'] = 'JSON is missing the "user_id" property or is not an object.';
                http_response_code(400);
            }
        } else {
            $response['message'] = 'Invalid JSON format: ' . json_last_error_msg();
            http_response_code(400);
        }
    } else {
        $response['message'] = 'No JSON data provided in the "json_data" field.';
        http_response_code(400);
    }
} else {
    $response['message'] = 'Invalid request method. Only POST requests are accepted.';
    http_response_code(405);
    header('Allow: POST');
}

echo json_encode($response);
exit;
?>