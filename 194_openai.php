<?php
session_start();

$messages = [];
$inventoryItems = [];
$processed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? '';
    if (empty($token) || !isset($_SESSION['inventory_csrf']) || !hash_equals($_SESSION['inventory_csrf'], $token)) {
        $messages[] = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        $serialized = $_POST['serialized'] ?? '';
        if (!is_string($serialized)) {
            $messages[] = 'Serialized data must be a string.';
        } else {
            if (strlen($serialized) > 1000000) {
                $messages[] = 'Serialized data is too large.';
            } else {
                $data = @unserialize($serialized, ["allowed_classes" => false]);
                if ($data === false) {
                    $messages[] = 'Failed to deserialize data. Ensure it is valid serialized inventory.';
                } else {
                    $items = [];
                    if (is_array($data)) {
                        if (isset($data[0]) && is_array($data[0])) {
                            $items = $data;
                        } elseif (isset($data['id']) || isset($data['name'])) {
                            $items = [$data];
                        } else {
                            foreach ($data as $v) {
                                if (is_array($v)) $items[] = $v;
                            }
                        }
                    }
                    if (empty($items)) {
                        $messages[] = 'No valid inventory items found in data.';
                    } else {
                        $sanitized = [];
                        $count = 0;
                        foreach ($items as $it) {
                            if (!is_array($it)) continue;
                            $id = isset($it['id']) ? (int)$it['id'] : 0;
                            $name = isset($it['name']) ? trim((string)$it['name']) : '';
                            $qty = isset($it['qty']) ? (int)$it['qty'] : (isset($it['quantity']) ? (int)$it['quantity'] : 0);
                            $price = isset($it['price']) ? (float)$it['price'] : (isset($it['cost']) ? (float)$it['cost'] : 0.0);
                            if ($name === '' && $id === 0) continue;
                            $sanitized[] = [
                                'id' => $id,
                                'name' => $name,
                                'quantity' => $qty,
                                'price' => $price
                            ];
                            $count++;
                        }
                        if ($count === 0) {
                            $messages[] = 'No valid items after validation.';
                        } else {
                            $inventoryItems = $sanitized;
                            $messages[] = 'Deserialization and validation successful. '.$count.' item(s) found.';
                            $processed = true;
                        }
                    }
                }
            }
        }
    }
}

if (!isset($_SESSION['inventory_csrf'])) {
    $_SESSION['inventory_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['inventory_csrf'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Inventory Import - Serialized Data</title>
</head>
<body>
<h1>Inventory Import</h1>

<?php if (!empty($messages)) { ?>
<div role="alert" aria-live="polite">
  <?php foreach ($messages as $msg) { ?>
  <p><?php echo htmlspecialchars($msg); ?></p>
  <?php } ?>
</div>
<?php } ?>

<form method="post" action="" autocomplete="off">
  <div>
    <label for="serialized">Serialized Data</label><br>
    <textarea id="serialized" name="serialized" rows="12" cols="80"><?php
      if (isset($_POST['serialized']) && $_POST['serialized'] !== '') {
          echo htmlspecialchars($_POST['serialized']);
      }
    ?></textarea>
  </div>
  <input type="hidden" name="_token" value="<?php echo htmlspecialchars($csrf); ?>">
  <div>
    <button type="submit">Deserialize Data</button>
  </div>
</form>

<?php if ($processed && !empty($inventoryItems)) { ?>
  <h2>Inventory Items</h2>
  <table border="1" cellpadding="5" cellspacing="0" aria-describedby="inventory-table">
    <thead>
      <tr><th>ID</th><th>Name</th><th>Quantity</th><th>Price</th><th>Line Total</th></tr>
    </thead>
    <tbody>
      <?php 
      $grandTotal = 0.0;
      foreach ($inventoryItems as $item) {
        $line = $item['quantity'] * $item['price'];
        $grandTotal += $line;
      ?>
      <tr>
        <td><?php echo htmlspecialchars((string)$item['id']); ?></td>
        <td><?php echo htmlspecialchars($item['name']); ?></td>
        <td><?php echo htmlspecialchars((string)$item['quantity']); ?></td>
        <td><?php echo htmlspecialchars(number_format($item['price'], 2)); ?></td>
        <td><?php echo htmlspecialchars(number_format($line, 2)); ?></td>
      </tr>
      <?php } ?>
    </tbody>
    <tfoot>
      <tr><td colspan="2"><strong>Total</strong></td><td></td><td></td><td><strong><?php echo htmlspecialchars(number_format($grandTotal, 2)); ?></strong></td></tr>
    </tfoot>
  </table>
<?php } ?>

</body>
</html>
?>