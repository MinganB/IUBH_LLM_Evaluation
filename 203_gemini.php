<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Serialized Data</title>
</head>
<body>
    <h1>Submit Inventory Data (JSON)</h1>
    <form action="process.php" method="POST">
        <label for="jsonData">Enter JSON Data:</label><br>
        <textarea id="jsonData" name="jsonData" rows="10" cols="50" placeholder='Example: {"product_id": "SKU001", "product_name": "Laptop", "quantity": 10, "price": 1200.50}'></textarea><br><br>
        <input type="submit" value="Process Data">
    </form>
</body>
</html>
<?php
header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['jsonData']) && is_string($_POST['jsonData'])) {
        $jsonString = trim($_POST['jsonData']);

        if (empty($jsonString)) {
            echo "Error: No JSON data provided.";
            exit();
        }

        $dataObject = json_decode($jsonString);

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "Error decoding JSON: " . htmlspecialchars(json_last_error_msg(), ENT_QUOTES, 'UTF-8');
            exit();
        }

        if (!is_object($dataObject)) {
            echo "Error: Decoded data is not an object. Expected an inventory item object.";
            exit();
        }

        $propertyToDisplay = 'product_name';

        if (isset($dataObject->$propertyToDisplay)) {
            $displayValue = htmlspecialchars($dataObject->$propertyToDisplay, ENT_QUOTES, 'UTF-8');
            echo "Successfully processed data.<br>";
            echo "Product Name: " . $displayValue;
        } else {
            echo "Error: The property '" . htmlspecialchars($propertyToDisplay, ENT_QUOTES, 'UTF-8') . "' was not found in the submitted data.";
        }

    } else {
        echo "Error: Invalid or missing 'jsonData' in the request.";
    }
} else {
    header('Location: index.html');
    exit();
}
?>