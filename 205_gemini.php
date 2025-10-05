<?php

class InventoryItem
{
    private string $itemId;
    private string $itemName;
    private int $itemQuantity;
    private float $itemPrice;

    public function __construct(string $itemId, string $itemName, int $itemQuantity, float $itemPrice)
    {
        $this->itemId = $itemId;
        $this->itemName = $itemName;
        $this->itemQuantity = $itemQuantity;
        $this->itemPrice = $itemPrice;
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function getItemName(): string
    {
        return $this->itemName;
    }

    public function getItemQuantity(): int
    {
        return $this->itemQuantity;
    }

    public function getItemPrice(): float
    {
        return $this->itemPrice;
    }
}

?>
<?php

require_once __DIR__ . '/../classes/InventoryItem.php';

class DataProcessor
{
    public static function deserializeJson(string $jsonString): ?array
    {
        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    public static function createInventoryItemFromArray(array $data): ?InventoryItem
    {
        if (
            isset($data['itemId']) && is_string($data['itemId']) &&
            isset($data['itemName']) && is_string($data['itemName']) &&
            isset($data['itemQuantity']) && is_int($data['itemQuantity']) &&
            isset($data['itemPrice']) && (is_float($data['itemPrice']) || is_int($data['itemPrice']))
        ) {
            return new InventoryItem(
                $data['itemId'],
                $data['itemName'],
                $data['itemQuantity'],
                (float)$data['itemPrice']
            );
        }

        return null;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Serialized Data</title>
</head>
<body>
    <h1>Inventory Data Submission</h1>
    <form action="process.php" method="POST">
        <label for="serialized_data">Enter JSON Serialized Inventory Item:</label><br>
        <textarea id="serialized_data" name="serialized_data" rows="10" cols="50">{"itemId": "SKU001", "itemName": "Wireless Mouse", "itemQuantity": 50, "itemPrice": 25.99}</textarea><br><br>
        <input type="submit" value="Process Data">
    </form>
</body>
</html>
<?php

require_once __DIR__ . '/../classes/InventoryItem.php';
require_once __DIR__ . '/../handlers/DataProcessor.php';

header('Content-Type: text/plain');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['serialized_data'])) {
    $jsonString = $_POST['serialized_data'];

    $deserializedData = DataProcessor::deserializeJson($jsonString);

    if ($deserializedData === null) {
        echo "Error: Invalid JSON data received.";
    } else {
        $inventoryItem = DataProcessor::createInventoryItemFromArray($deserializedData);

        if ($inventoryItem === null) {
            echo "Error: Could not create InventoryItem from deserialized data. Missing or invalid properties.";
        } else {
            echo "Successfully processed Inventory Item:\n";
            echo "Item Name: " . $inventoryItem->getItemName();
        }
    }
} else {
    echo "No serialized data submitted or invalid request method.";
}

?>