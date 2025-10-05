<?php
// classes/InventoryItem.php
class InventoryItem
{
    public string $sku;
    public string $name;
    public int $quantity;
    public float $price;
    public string $lastUpdated;

    public function __construct(string $sku, string $name, int $quantity, float $price)
    {
        $this->sku = $sku;
        $this->name = $name;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->lastUpdated = date('Y-m-d H:i:s');
    }

    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'lastUpdated' => $this->lastUpdated,
        ];
    }
}

?>

<?php
// handlers/process_inventory_data.php
require_once __DIR__ . '/../classes/InventoryItem.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Invalid request method.';
    header('Location: ../public/submit_inventory.php');
    exit;
}

if (!isset($_POST['serialized_data']) || empty($_POST['serialized_data'])) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'No serialized data provided.';
    header('Location: ../public/submit_inventory.php');
    exit;
}

$serializedData = $_POST['serialized_data'];
$inventoryItem = null;

try {
    $inventoryItem = unserialize($serializedData, ['allowed_classes' => ['InventoryItem']]);

    if (!$inventoryItem instanceof InventoryItem) {
        throw new Exception('Deserialized data is not a valid InventoryItem object or contains disallowed classes.');
    }

    // This section simulates a database update.
    // In a production environment, you would integrate with your PDO/ORM here
    // to persist $inventoryItem data into your database.
    // Example:
    // $db = new PDO('mysql:host=localhost;dbname=inventory_db', 'user', 'password');
    // $stmt = $db->prepare("INSERT INTO inventory (sku, name, quantity, price, last_updated) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=?, quantity=?, price=?, last_updated=?");
    // $stmt->execute([
    //     $inventoryItem->sku, $inventoryItem->name, $inventoryItem->quantity, $inventoryItem->price, $inventoryItem->lastUpdated,
    //     $inventoryItem->name, $inventoryItem->quantity, $inventoryItem->price, $inventoryItem->lastUpdated
    // ]);
    
    $_SESSION['processed_item'] = $inventoryItem->toArray();

    $_SESSION['status'] = 'success';
    $_SESSION['message'] = 'Data processed successfully. Details below.';
    header('Location: ../public/submit_inventory.php');
    exit;

} catch (Exception $e) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    header('Location: ../public/submit_inventory.php');
    exit;
} catch (Error $e) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Error: Malformed serialized data provided.';
    header('Location: ../public/submit_inventory.php');
    exit;
}

?>

<?php
// public/submit_inventory.php
require_once __DIR__ . '/../classes/InventoryItem.php';
session_start();

$status = $_SESSION['status'] ?? null;
$message = $_SESSION['message'] ?? null;
$processedItem = $_SESSION['processed_item'] ?? null;

unset($_SESSION['status']);
unset($_SESSION['message']);
unset($_SESSION['processed_item']);

$exampleItem = new InventoryItem("SKU001", "Widget A", 100, 19.99);
$serializedExample = serialize($exampleItem);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Inventory Data</title>
</head>
<body>
    <h1>Submit Serialized Inventory Data</h1>

    <?php if ($status === 'success'): ?>
        <p style="color: green;"><?php echo htmlspecialchars($message); ?></p>
    <?php elseif ($status === 'error'): ?>
        <p style="color: red;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form action="../handlers/process_inventory_data.php" method="POST">
        <label for="serialized_data">Enter Serialized Inventory Data (PHP `InventoryItem` object):</label><br>
        <textarea id="serialized_data" name="serialized_data" rows="10" cols="80" required><?php echo htmlspecialchars($serializedExample); ?></textarea><br><br>
        <button type="submit">Process Data</button>
    </form>

    <?php if ($processedItem): ?>
        <h2>Last Processed Item Details:</h2>
        <table border="1">
            <tr><th>SKU</th><td><?php echo htmlspecialchars($processedItem['sku']); ?></td></tr>
            <tr><th>Name</th><td><?php echo htmlspecialchars($processedItem['name']); ?></td></tr>
            <tr><th>Quantity</th><td><?php echo htmlspecialchars($processedItem['quantity']); ?></td></tr>
            <tr><th>Price</th><td><?php echo htmlspecialchars($processedItem['price']); ?></td></tr>
            <tr><th>Last Updated</th><td><?php echo htmlspecialchars($processedItem['lastUpdated']); ?></td></tr>
        </table>
        <p>
            (This data would typically be used to update your database.)
        </p>
    <?php endif; ?>

    <p>
        This form is for submitting PHP serialized data, typically from a trusted third-party application.
        The data should be a serialized `InventoryItem` object.
    </p>
</body>
</html>
?>