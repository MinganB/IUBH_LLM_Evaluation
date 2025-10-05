<?php
header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['json_data']) && !empty($_POST['json_data'])) {
        $jsonData = $_POST['json_data'];
        $decodedData = json_decode($jsonData);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_object($decodedData) && isset($decodedData->itemName)) {
                echo "<h2>Processed Inventory Item:</h2>";
                echo "<p>Item Name: " . htmlspecialchars($decodedData->itemName) . "</p>";
            } else {
                echo "<h2>Error:</h2>";
                echo "<p>JSON data is valid but 'itemName' property is missing or not an object.</p>";
            }
        } else {
            echo "<h2>Error:</h2>";
            echo "<p>Invalid JSON data provided. Error: " . htmlspecialchars(json_last_error_msg()) . "</p>";
        }
    } else {
        echo "<h2>Error:</h2>";
        echo "<p>No JSON data received. Please submit data from the form.</p>";
    }
} else {
    echo "<h2>Error:</h2>";
    echo "<p>This script only accepts POST requests.</p>";
}


html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Inventory Data</title>
</head>
<body>
    <h1>Submit Inventory Data</h1>
    <form action="process.php" method="POST">
        <label for="json_data">Enter JSON Inventory Data:</label><br>
        <textarea id="json_data" name="json_data" rows="10" cols="50">{"itemId": "SKU001", "itemName": "Widget A", "quantity": 100, "location": "Warehouse 1"}</textarea><br><br>
        <input type="submit" value="Process Data">
    </form>
</body>
</html>
?>