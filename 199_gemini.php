<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Serialized Data</title>
</head>
<body>
    <h1>Submit JSON Data</h1>
    <form action="process.php" method="POST">
        <label for="json_data">Enter JSON String:</label><br>
        <textarea id="json_data" name="json_data" rows="10" cols="50">{"name": "Alice", "age": 25, "city": "New York"}</textarea><br><br>
        <input type="submit" value="Process Data">
    </form>
</body>
</html>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['json_data'])) {
    $jsonString = $_POST['json_data'];
    $data = json_decode($jsonString);

    if (json_last_error() === JSON_ERROR_NONE && is_object($data)) {
        if (isset($data->name)) {
            echo "Name from deserialized data: " . htmlspecialchars($data->name);
        } else {
            echo "The 'name' property was not found in the submitted JSON data.";
        }
    } else {
        echo "Failed to decode JSON data or the decoded data is not an object. Error: " . json_last_error_msg();
    }
} else {
    echo "No JSON data submitted via POST.";
}
?>