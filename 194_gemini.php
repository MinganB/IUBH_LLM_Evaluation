<?php

$deserializedData = null;
$processingError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['serialized_data'])) {
        $serializedInput = $_POST['serialized_data'];

        $unserializedResult = @unserialize($serializedInput, ["allowed_classes" => false]);

        if ($unserializedResult === false && $serializedInput !== 'b:0;') {
            $processingError = "Failed to deserialize data. The input may be malformed or contain disallowed classes.";
        } else {
            $deserializedData = $unserializedResult;
        }
    } else {
        $processingError = "No serialized data was provided.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Data Processor</title>
</head>
<body>
    <h1>Process Serialized Inventory Data</h1>

    <form action="" method="POST">
        <label for="serialized_data">Serialized Data from Third-Party Application:</label><br>
        <textarea id="serialized_data" name="serialized_data" rows="10" cols="80" required></textarea><br><br>
        <button type="submit">Deserialize and Display</button>
    </form>

    <?php if ($deserializedData !== null): ?>
        <h2>Deserialized Data Result:</h2>
        <?php
        if (is_array($deserializedData) || is_object($deserializedData)) {
            echo '<pre>';
            echo htmlspecialchars(print_r($deserializedData, true));
            echo '</pre>';

            if (is_array($deserializedData) && !empty($deserializedData)) {
                echo '<h3>Inventory Items:</h3>';
                echo '<table border="1" cellpadding="5" cellspacing="0">';
                echo '<thead><tr><th>Product Key</th><th>Name</th><th>Stock</th><th>Status</th></tr></thead>';
                echo '<tbody>';
                foreach ($deserializedData as $productKey => $item) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars((string)$productKey) . '</td>';
                    if (is_array($item) && isset($item['name']) && isset($item['stock'])) {
                        echo '<td>' . htmlspecialchars((string)$item['name']) . '</td>';
                        echo '<td>' . htmlspecialchars((string)$item['stock']) . '</td>';
                        echo '<td>' . ((int)$item['stock'] > 0 ? 'In Stock' : 'Out of Stock') . '</td>';
                    } else {
                        echo '<td colspan="3"><i>Malformed Item Data</i></td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
            } else {
                echo '<p><i>Data is deserialized but not in an expected array format for structured display.</i></p>';
            }
        } else {
            echo '<p><strong>Type:</strong> ' . htmlspecialchars(gettype($deserializedData)) . '</p>';
            echo '<p><strong>Value:</strong> ' . htmlspecialchars((string)$deserializedData) . '</p>';
        }
        ?>
    <?php elseif ($processingError !== null): ?>
        <p><strong>Error:</strong> <?php echo htmlspecialchars($processingError); ?></p>
    <?php endif; ?>

</body>
</html>
?>