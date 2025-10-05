<?php // public/index.html
/*
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Data Submission</title>
</head>
<body>
    <h1>Submit Inventory Data</h1>
    <form action="process.php" method="POST">
        <label for="jsonData">Enter JSON Data:</label><br>
        <textarea id="jsonData" name="data" rows="10" cols="50" required>{
    "id": 101,
    "productName": "Laptop X200",
    "quantity": 50,
    "price": 1200.50
}</textarea><br><br>
        <button type="submit">Process Data</button>
    </form>
</body>
</html>
*/

<?php // classes/InventoryItem.php

namespace InventorySystem\Classes;

class InventoryItem
{
    public int $id;
    public string $productName;
    public int $quantity;
    public float $price;

    public function __construct(int $id, string $productName, int $quantity, float $price)
    {
        $this->id = $id;
        $this->productName = $productName;
        $this->quantity = $quantity;
        $this->price = $price;
    }

    public static function fromStdClass(\stdClass $data): ?self
    {
        if (!isset($data->id) || !is_int($data->id) || $data->id <= 0) {
            return null;
        }
        if (!isset($data->productName) || !is_string($data->productName) || empty(trim($data->productName))) {
            return null;
        }
        if (!isset($data->quantity) || !is_int($data->quantity) || $data->quantity < 0) {
            return null;
        }
        if (!isset($data->price) || (!is_float($data->price) && !is_int($data->price)) || $data->price < 0) {
            return null;
        }

        $price = (float)$data->price;

        return new self($data->id, $data->productName, $data->quantity, $price);
    }
}

<?php // handlers/DataHandler.php

namespace InventorySystem\Handlers;

use InventorySystem\Classes\InventoryItem;
use JsonException;

class DataHandler
{
    private string $logFilePath;

    public function __construct(string $logFilePath)
    {
        $this->logFilePath = $logFilePath;
    }

    public function deserializeAndValidate(string $jsonString): ?InventoryItem
    {
        $decodedData = null;
        try {
            $decodedData = json_decode($jsonString, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logProcessingAttempt('JSON deserialization failed.', [
                'error' => $e->getMessage(),
                'json_input' => $jsonString
            ]);
            return null;
        }

        if (!is_object($decodedData)) {
            $this->logProcessingAttempt('Decoded data is not an object.', [
                'data_type' => gettype($decodedData),
                'json_input' => $jsonString
            ]);
            return null;
        }

        $item = InventoryItem::fromStdClass($decodedData);

        if ($item === null) {
            $this->logProcessingAttempt('Data failed schema or content validation.', [
                'json_input' => $jsonString,
                'decoded_data' => (array) $decodedData
            ]);
            return null;
        }

        $this->logProcessingAttempt('Data successfully deserialized and validated.', [
            'item_id' => $item->id,
            'product_name' => $item->productName
        ]);

        return $item;
    }

    public function logProcessingAttempt(string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf(
            "[%s] %s Source: %s %s\n",
            $timestamp,
            $message,
            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
            !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );

        $logDir = dirname($this->logFilePath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

<?php // public/process.php

define('BASE_DIR', dirname(__DIR__));

spl_autoload_register(function ($class) {
    $prefix = 'InventorySystem\\';
    $base_dir = BASE_DIR . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use InventorySystem\Handlers\DataHandler;

$logFilePath = BASE_DIR . '/logs/processing.log';

$dataHandler = new DataHandler($logFilePath);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['data'])) {
    $jsonData = $_POST['data'];
    $inventoryItem = $dataHandler->deserializeAndValidate($jsonData);

    if ($inventoryItem !== null) {
        echo "<h1>Inventory Data Processed Successfully!</h1>";
        echo "<p>Product Name: " . htmlspecialchars($inventoryItem->productName) . "</p>";
    } else {
        echo "<h1>Error Processing Inventory Data</h1>";
        echo "<p>An error occurred while processing your data. Please check the input and try again.</p>";
    }
} else {
    $dataHandler->logProcessingAttempt('Invalid request or missing data payload.', [
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'has_post_data' => isset($_POST['data'])
    ]);
    echo "<h1>Invalid Request</h1>";
    echo "<p>Please submit data via the form.</p>";
}
?>