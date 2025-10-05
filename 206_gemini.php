<?php declare(strict_types=1);

class InventoryItem
{
    private int $id;
    private string $name;
    private string $sku;
    private int $quantity;
    private float $price;

    public function __construct(int $id, string $name, string $sku, int $quantity, float $price)
    {
        $this->id = $id;
        $this->name = $name;
        $this->sku = $sku;
        $this->quantity = $quantity;
        $this->price = $price;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getPrice(): float
    {
        return $this->price;
    }
}
?>

<?php declare(strict_types=1);

require_once __DIR__ . '/../classes/InventoryItem.php';

class DataHandler
{
    public function processInventoryJson(string $jsonString): ?InventoryItem
    {
        $decodedData = json_decode($jsonString, false, 512, JSON_BIGINT_AS_STRING);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if (!is_object($decodedData)) {
            return null;
        }

        if (!isset($decodedData->id) || !is_numeric($decodedData->id) ||
            !isset($decodedData->name) || !is_string($decodedData->name) ||
            !isset($decodedData->sku) || !is_string($decodedData->sku) ||
            !isset($decodedData->quantity) || !is_numeric($decodedData->quantity) ||
            !isset($decodedData->price) || (!is_float($decodedData->price) && !is_int($decodedData->price))) {
            return null;
        }

        $id = (int)$decodedData->id;
        $name = (string)$decodedData->name;
        $sku = (string)$decodedData->sku;
        $quantity = (int)$decodedData->quantity;
        $price = (float)$decodedData->price;

        return new InventoryItem($id, $name, $sku, $quantity, $price);
    }
}
?>

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
        <label for="jsonData">Enter JSON Data:</label><br>
        <textarea id="jsonData" name="jsonData" rows="10" cols="50" required>{
    "id": 101,
    "name": "Widget A",
    "sku": "WA-001",
    "quantity": 50,
    "price": 19.99
}</textarea><br><br>
        <button type="submit">Process Data</button>
    </form>
</body>
</html>

<?php declare(strict_types=1);

require_once __DIR__ . '/../handlers/DataHandler.php';

header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['jsonData']) && is_string($_POST['jsonData'])) {
        $jsonData = trim($_POST['jsonData']);

        $dataHandler = new DataHandler();
        $inventoryItem = $dataHandler->processInventoryJson($jsonData);

        if ($inventoryItem instanceof InventoryItem) {
            echo '<h2>Processed Inventory Item Details:</h2>';
            echo '<p>Item Name: ' . htmlspecialchars($inventoryItem->getName(), ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p>Item SKU: ' . htmlspecialchars($inventoryItem->getSku(), ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p>Quantity: ' . htmlspecialchars((string)$inventoryItem->getQuantity(), ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p>Price: ' . htmlspecialchars((string)$inventoryItem->getPrice(), ENT_QUOTES, 'UTF-8') . '</p>';
        } else {
            echo '<h2>Error Processing Data:</h2>';
            echo '<p>Could not process the provided JSON data. Please ensure it is valid and complete with all required properties.</p>';
        }
    } else {
        echo '<h2>Error:</h2>';
        echo '<p>No JSON data received or data format is invalid.</p>';
    }
} else {
    header('Location: index.html');
    exit();
}
?>