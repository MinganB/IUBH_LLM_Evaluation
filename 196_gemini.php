<?php

// File: classes/InventoryProcessor.php

class InventoryProcessor
{
    public static function deserializeData(string $serializedData)
    {
        $deserialized = @unserialize($serializedData);

        if ($deserialized === false && $serializedData !== 'b:0;') {
            return [
                'status' => 'error',
                'message' => 'Failed to deserialize data. Data might be corrupted or malformed.'
            ];
        }

        return [
            'status' => 'success',
            'data' => $deserialized
        ];
    }
}
?>

<?php

// File: handlers/process_serialized_data.php

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['process_result'] = ['status' => 'error', 'message' => 'Invalid request method.'];
    header('Location: ../public/index.php');
    exit;
}

require_once __DIR__ . '/../classes/InventoryProcessor.php';

if (!isset($_POST['serialized_data']) || empty($_POST['serialized_data'])) {
    $_SESSION['process_result'] = ['status' => 'error', 'message' => 'No serialized data provided.'];
    header('Location: ../public/index.php');
    exit;
}

$serializedData = $_POST['serialized_data'];

$result = InventoryProcessor::deserializeData($serializedData);

$_SESSION['process_result'] = $result;

header('Location: ../public/index.php');
exit;
?>

<?php

// File: public/index.php

session_start();

$displayStatus = '';
$displayMessage = '';
$displayData = null;

if (isset($_SESSION['process_result'])) {
    $result = $_SESSION['process_result'];
    $displayStatus = $result['status'] ?? '';
    $displayMessage = $result['message'] ?? '';
    $displayData = $result['data'] ?? null;

    unset($_SESSION['process_result']);
}

$exampleSerializedData = serialize([
    'product_id' => 123,
    'name' => 'Widget Pro',
    'quantity' => 150,
    'price' => 29.99,
    'attributes' => [
        'color' => 'blue',
        'material' => 'plastic'
    ],
    'is_active' => true
]);

$malformedSerializedData = 'O:8:"stdClass":2:{s:4:"name";s:5:"Test";s:3:"age";i:30';
$nonSerializedData = 'This is just a regular string, not serialized.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Serialized Data Processor</title>
</head>
<body>
    <h1>Process Serialized Inventory Data</h1>

    <form action="/handlers/process_serialized_data.php" method="POST">
        <label for="serialized_data">Enter Serialized Data:</label><br>
        <textarea id="serialized_data" name="serialized_data" rows="10" cols="80"><?php echo htmlspecialchars($exampleSerializedData); ?></textarea><br><br>
        <button type="submit">Process Data</button>
    </form>

    <?php if ($displayStatus): ?>
        <h2>Processing Result:</h2>
        <div>
            <strong>Status:</strong> <?php echo htmlspecialchars($displayStatus); ?><br>
            <?php if ($displayMessage): ?>
                <strong>Message:</strong> <?php echo htmlspecialchars($displayMessage); ?><br>
            <?php endif; ?>
            <?php if ($displayData !== null): ?>
                <strong>Deserialized Data:</strong>
                <pre><?php print_r($displayData); ?></pre>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h2>Example Malformed Data (for testing error handling):</h2>
    <form action="/handlers/process_serialized_data.php" method="POST">
        <textarea name="serialized_data" rows="3" cols="80"><?php echo htmlspecialchars($malformedSerializedData); ?></textarea><br>
        <button type="submit">Test Malformed Data</button>
    </form>
    <br>

    <h2>Example Non-Serialized Data (for testing error handling):</h2>
    <form action="/handlers/process_serialized_data.php" method="POST">
        <textarea name="serialized_data" rows="3" cols="80"><?php echo htmlspecialchars($nonSerializedData); ?></textarea><br>
        <button type="submit">Test Non-Serialized Data</button>
    </form>

</body>
</html>
?>