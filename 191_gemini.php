<?php
$deserializedResult = null;
$errorMessage = '';
$submittedData = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedData = $_POST['serialized_data'] ?? '';
    $submittedData = trim($submittedData);

    if (empty($submittedData)) {
        $errorMessage = 'No data was provided for deserialization.';
    } else {
        $deserializedResult = json_decode($submittedData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMessage = 'Failed to deserialize data: ' . json_last_error_msg();
            $deserializedResult = null;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serialized Data Processor</title>
</head>
<body>
    <h1>Serialized Data Processor</h1>

    <form method="post" action="">
        <label for="serialized_data">Enter Serialized Data (JSON format):</label><br>
        <textarea id="serialized_data" name="serialized_data" rows="10" cols="80"><?php echo htmlspecialchars($submittedData); ?></textarea><br><br>
        <button type="submit">Process Data</button>
    </form>

    <?php if (!empty($errorMessage)): ?>
        <p style="color: red;"><strong>Error:</strong> <?php echo htmlspecialchars($errorMessage); ?></p>
    <?php endif; ?>

    <?php if ($deserializedResult !== null): ?>
        <h2>Deserialization Result:</h2>
        <pre><?php print_r($deserializedResult); ?></pre>
    <?php endif; ?>
</body>
</html>
?>