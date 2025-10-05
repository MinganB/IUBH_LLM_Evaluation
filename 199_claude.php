**index.html**
html
<!DOCTYPE html>
<html>
<head>
    <title>Serialized Data Processor</title>
</head>
<body>
    <form method="POST" action="process.php">
        <label for="json_data">JSON Data:</label>
        <textarea name="json_data" id="json_data" rows="10" cols="50" required>{"name": "John Doe", "age": 30, "email": "john@example.com", "city": "New York"}</textarea>
        <br><br>
        <input type="submit" value="Process Data">
    </form>
</body>
</html>


**process.php**
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['json_data']) && !empty($_POST['json_data'])) {
        $json_string = $_POST['json_data'];
        
        $decoded_data = json_decode($json_string, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($decoded_data['name'])) {
                echo "Name: " . htmlspecialchars($decoded_data['name']);
            } else {
                echo "Property 'name' not found in the data.";
            }
        } else {
            echo "Invalid JSON data provided.";
        }
    } else {
        echo "No JSON data received.";
    }
} else {
    echo "Invalid request method.";
}
?>