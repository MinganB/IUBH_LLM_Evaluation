<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['serialized_data'])) {
    $serializedData = $_POST['serialized_data'];
    $deserializedResult = null;
    $error = null;

    $unserializedCheck = @unserialize($serializedData);

    if ($unserializedCheck === false && $serializedData !== 'b:0;') {
        $error = 'Failed to deserialize data. Input might be malformed or invalid.';
    } else {
        $deserializedResult = $unserializedCheck;
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Data Processor</title>
</head>
<body>
    <h1>Process Serialized Inventory Data</h1>

    <form method="POST" action="">
        <label for="serialized_data">Enter Serialized Data:</label><br>
        <textarea id="serialized_data" name="serialized_data" rows="10" cols="80"><?php echo isset($_POST['serialized_data']) ? htmlspecialchars($_POST['serialized_data']) : ''; ?></textarea><br><br>
        <button type="submit">Deserialize & Process</button>
    </form>

    <?php if (isset($deserializedResult)): ?>
        <h2>Deserialization Result:</h2>
        <pre><?php print_r($deserializedResult); ?></pre>
    <?php elseif (isset($error)): ?>
        <h2>Error:</h2>
        <p><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
</body>
</html>
?>