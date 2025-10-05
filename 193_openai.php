<?php
declare(strict_types=1);

$storageDir = __DIR__ . '/storage';
$inventoryFile = $storageDir . '/inventory.json';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
}

$error = '';
$processed = false;
$summary = [];

$samplePayload = [
    'inventory' => [
        ['sku' => 'SKU001', 'name' => 'Widget A', 'quantity' => 10, 'price' => 5.50],
        ['sku' => 'SKU002', 'name' => 'Gadget B', 'quantity' => 4, 'price' => 12.25]
    ]
];
$sampleSerialized = serialize($samplePayload);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = $_POST['payload'] ?? '';
    $payload = trim($payload);
    if ($payload === '') {
        $error = 'Serialized payload is empty.';
    } else {
        $unserialized = @unserialize($payload, ['allowed_classes' => false]);
        if ($unserialized === false && $payload !== 'b:0;' && $payload !== 'N;') {
            $error = 'Invalid serialized data.';
        } else {
            $incoming = [];
            if (is_array($unserialized) && isset($unserialized['inventory']) && is_array($unserialized['inventory'])) {
                $incoming = $unserialized['inventory'];
            } elseif (is_array($unserialized)) {
                $incoming = $unserialized;
            } else {
                $error = 'Serialized data does not contain inventory items.';
            }

            if (!$error) {
                $current = [];
                if (file_exists($inventoryFile)) {
                    $current = loadInventory($inventoryFile);
                }
                $updated = mergeInventoryArrays($current, $incoming);
                saveInventory($inventoryFile, $updated);
                $summary = summarizeInventory($updated);
                $processed = true;
            }
        }
    }
}

function loadInventory(string $path): array {
    $contents = @file_get_contents($path);
    if ($contents === false) return [];
    $data = json_decode($contents, true);
    if (is_array($data)) return $data;
    return [];
}
function saveInventory(string $path, array $inventory): void {
    file_put_contents($path, json_encode($inventory, JSON_PRETTY_PRINT));
}
function mergeInventoryArrays(array $current, array $incoming): array {
    $updated = $current;
    foreach ($incoming as $item) {
        if (!is_array($item)) continue;
        $sku = isset($item['sku']) ? trim((string)$item['sku']) : null;
        if (!$sku) continue;
        $qty = 0;
        if (isset($item['quantity'])) $qty = (int)$item['quantity']; elseif (isset($item['qty'])) $qty = (int)$item['qty'];
        $price = isset($item['price']) ? (float)$item['price'] : 0.0;
        $name = isset($item['name']) ? $item['name'] : '';
        if (!isset($updated[$sku])) {
            $updated[$sku] = [
                'sku' => $sku,
                'name' => $name,
                'quantity' => (int)$qty,
                'price' => (float)$price
            ];
        } else {
            $updated[$sku]['quantity'] = (int)$updated[$sku]['quantity'] + (int)$qty;
            if ($name !== '') $updated[$sku]['name'] = $name;
            if ($price !== 0.0) $updated[$sku]['price'] = (float)$price;
        }
    }
    return $updated;
}
function summarizeInventory(array $inventory): array {
    $totalQty = 0;
    $totalValue = 0.0;
    foreach ($inventory as $item) {
        $q = isset($item['quantity']) ? (int)$item['quantity'] : 0;
        $p = isset($item['price']) ? (float)$item['price'] : 0.0;
        $totalQty += $q;
        $totalValue += $q * $p;
    }
    return ['totalQty' => $totalQty, 'totalValue' => $totalValue, 'itemCount' => count($inventory)];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Inventory Ingest - Serialized Data Processor</title>
</head>
<body>
<h1>Inventory Ingest</h1>

<?php if ($error): ?>
<p><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>
<?php if ($processed): ?>
<p>Processed incoming inventory. Items: <?php echo (int)$summary['itemCount']; ?>, Total quantity: <?php echo (int)$summary['totalQty']; ?>, Total value: <?php echo number_format($summary['totalValue'], 2); ?></p>
<?php endif; ?>

<form method="post" action="">
<label>Serialized payload:</label><br/>
<textarea name="payload" rows="15" cols="100"><?php echo htmlspecialchars($sampleSerialized, ENT_QUOTES); ?></textarea><br/>
<input type="submit" value="Process Serialized Inventory" />
</form>

<h2>Current Inventory</h2>
<?php
$inventoryData = [];
$inv = file_exists($inventoryFile) ? loadInventory($inventoryFile) : [];
foreach ($inv as $sku => $data) {
    $inventoryData[] = $data;
}
if (!empty($inventoryData)) {
    echo '<table>';
    echo '<thead><tr><th>SKU</th><th>Name</th><th>Quantity</th><th>Price</th><th>Subtotal</th></tr></thead>';
    echo '<tbody>';
    foreach ($inventoryData as $row) {
        $sku = $row['sku'] ?? '';
        $name = $row['name'] ?? '';
        $qty = isset($row['quantity']) ? (int)$row['quantity'] : 0;
        $price = isset($row['price']) ? (float)$row['price'] : 0.0;
        $subtotal = $qty * $price;
        echo '<tr>';
        echo '<td>'.htmlspecialchars($sku).'</td>';
        echo '<td>'.htmlspecialchars($name).'</td>';
        echo '<td>'.(int)$qty.'</td>';
        echo '<td>'.number_format($price, 2).'</td>';
        echo '<td>'.number_format($subtotal, 2).'</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p>No inventory yet.</p>';
}
?>

</body>
</html>
?>