index.html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Inventory Processor</title>
</head>
<body>
  <form action="process.php" method="POST" id="payloadForm">
    <label for="payload">JSON payload</label><br/>
    <textarea id="payload" name="payload" rows="12" cols="80" placeholder='{"user_id":123,"product_id":456,"quantity":10,"action":"increase"}'></textarea><br/>
    <button type="submit">Submit</button>
  </form>
</body>
</html>

process.php
<?php
header('Content-Type: application/json');

$result = ['success' => false, 'message' => 'Invalid request.'];

$raw = $_POST['payload'] ?? '';
$raw = is_string($raw) ? trim($raw) : '';

if ($raw === '') {
  $result['message'] = 'No payload provided';
  echo json_encode($result);
  exit;
}

$decoded = json_decode($raw);
if (!$decoded || !is_object($decoded) || !property_exists($decoded, 'user_id')) {
  $result['message'] = 'Invalid JSON payload or missing user_id';
  echo json_encode($result);
  exit;
}

$user_id = $decoded->user_id;
$product_id = property_exists($decoded, 'product_id') ? $decoded->product_id : null;
$quantity = property_exists($decoded, 'quantity') ? $decoded->quantity : null;
$action = property_exists($decoded, 'action') ? strtolower($decoded->action) : 'increase';

if (!is_numeric($user_id) || $product_id === null || !is_numeric($product_id) || !is_numeric($quantity)) {
  $result['message'] = 'Missing or invalid product_id/quantity';
  echo json_encode($result);
  exit;
}

$quantity = (int)$quantity;
$action = in_array($action, ['increase','decrease','set']) ? $action : 'increase';

$pdo = null;
try {
  $host = getenv('DB_HOST') ?: '127.0.0.1';
  $db   = getenv('DB_NAME') ?: 'inventorydb';
  $user = getenv('DB_USER') ?: 'dbuser';
  $pass = getenv('DB_PASS') ?: '';
  $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  $pdo->beginTransaction();

  $select = $pdo->prepare('SELECT stock FROM inventory WHERE product_id = :pid FOR UPDATE');
  $select->execute([':pid' => $product_id]);
  $row = $select->fetch();

  if ($row === false) {
    $stock = 0;
    $insert = $pdo->prepare('INSERT INTO inventory (product_id, stock) VALUES (:pid, :stock)');
    $insert->execute([':pid' => $product_id, ':stock' => $stock]);
  } else {
    $stock = (int)$row['stock'];
  }

  if ($action === 'increase') {
    $stock += $quantity;
  } elseif ($action === 'decrease') {
    $stock -= $quantity;
    if ($stock < 0) $stock = 0;
  } else { // set
    $stock = $quantity;
  }

  $update = $pdo->prepare('UPDATE inventory SET stock = :stock WHERE product_id = :pid');
  $update->execute([':stock' => $stock, ':pid' => $product_id]);

  $pdo->commit();

  $result['success'] = true;
  $result['message'] = 'User ' . $user_id . ': updated product ' . $product_id . '; new stock ' . $stock;
} catch (Exception $e) {
  if ($pdo) {
    try { $pdo->rollBack(); } catch (Exception $e2) {}
  }
  error_log($e->getMessage());
  $result['message'] = 'Database operation failed';
}

echo json_encode($result);
?>