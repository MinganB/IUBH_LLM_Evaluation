**index.html**
html
<!DOCTYPE html>
<html>
<head>
    <title>JSON Processing Form</title>
</head>
<body>
    <form action="process.php" method="POST">
        <textarea name="json_input" rows="10" cols="50" placeholder="Enter JSON string here"></textarea>
        <br>
        <input type="submit" value="Process JSON">
    </form>
</body>
</html>


**process.php**
<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_input = $_POST['json_input'] ?? '';
    
    $decoded_data = json_decode($json_input, false);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        if (isset($decoded_data->user_id)) {
            echo "<p>User ID: " . htmlspecialchars($decoded_data->user_id) . "</p>";
            
            echo json_encode([
                'success' => true,
                'message' => 'JSON processed successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Property user_id not found in JSON'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON format'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed'
    ]);
}
?>