<!DOCTYPE html>
<html>
<head>
    <title>Inventory Data Input</title>
</head>
<body>
    <form method="POST" action="process.php">
        <textarea name="json_data" rows="10" cols="50" placeholder="Enter JSON data here"></textarea><br>
        <input type="submit" value="Submit">
    </form>
</body>
</html>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_string = $_POST['json_data'] ?? '';
    
    $decoded_data = json_decode($json_string, false);
    
    if ($decoded_data === null) {
        echo "Invalid JSON data";
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON data provided'
        ]);
        exit;
    }
    
    $user_id = $decoded_data->user_id ?? null;
    
    echo "User ID: " . htmlspecialchars($user_id);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Data processed successfully'
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>