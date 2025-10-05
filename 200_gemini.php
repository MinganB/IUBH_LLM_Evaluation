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
        <textarea id="json_data" name="json_data" rows="10" cols="50">{"name": "John Doe", "age": 30, "city": "New York", "occupation": "Software Engineer"}</textarea><br><br>
        <input type="submit" value="Process Data">
    </form>
</body>
</html>
<?php
$propertyName = 'name';
$displayValue = 'N/A';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['json_data']) && is_string($_POST['json_data']) && !empty($_POST['json_data'])) {
        $jsonString = $_POST['json_data'];

        $data = json_decode($jsonString);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (is_object($data)) {
                if (isset($data->$propertyName)) {
                    $displayValue = htmlspecialchars($data->$propertyName, ENT_QUOTES, 'UTF-8');
                } else {
                    $errorMessage = "Error: Property '" . htmlspecialchars($propertyName, ENT_QUOTES, 'UTF-8') . "' not found in the deserialized data.";
                }
            } else {
                $errorMessage = "Error: Deserialized data is not an object. Expected an object.";
            }
        } else {
            $errorMessage = "Error decoding JSON: " . htmlspecialchars(json_last_error_msg(), ENT_QUOTES, 'UTF-8');
        }
    } else {
        $errorMessage = "Error: No JSON data submitted or data is empty/invalid type.";
    }
} else {
    $errorMessage = "Error: Invalid request method. Please submit the form via POST.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processed Data</title>
</head>
<body>
    <h1>Processed Serialized Data</h1>
    <?php if (!empty($errorMessage)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php else: ?>
        <p>Specific property '<?php echo htmlspecialchars($propertyName, ENT_QUOTES, 'UTF-8'); ?>': <strong><?php echo $displayValue; ?></strong></p>
    <?php endif; ?>
    <p><a href="index.html">Go back</a></p>
</body>
</html>