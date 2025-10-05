<?php
define('LOG_FILE', __DIR__ . '/logs/processing.log');
define('INVENTORY_FILE', __DIR__ . '/storage/inventory.json');

function ensure_dirs() {
  $logDir = dirname(LOG_FILE);
  $invDir = dirname(INVENTORY_FILE);
  if (!is_dir($logDir)) { mkdir($logDir, 0750, true); }
  if (!is_dir($invDir)) { mkdir($invDir, 0750, true); }
  if (!file_exists(LOG_FILE)) { file_put_contents(LOG_FILE, ''); @chmod(LOG_FILE, 0600); }
}
ensure_dirs();

function write_log($message) {
  $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
  file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function safe_unserialize($data) {
  if (!is_string($data)) return false;
  $result = @unserialize($data, ['allowed_classes' => false]);
  if ($result === false && $data !== 'N;' && $data !== 'b:0;') {
    return false;
  }
  return $result;
}

function is_valid_item($row) {
  if (!is_array($row)) return false;
  if (!isset($row['sku'], $row['name'], $row['quantity'], $row['price'])) return false;
  if (!is_string($row['sku']) || trim($row['sku']) === '') return false;
  if (!is_string($row['name']) || trim($row['name']) === '') return false;
  $q = $row['quantity'];
  $p = $row['price'];
  if (!is_numeric($q) || (int)$q < 0) return false;
  if (!is_numeric($p) || (float)$p < 0) return false;
  return true;
}
function normalize_item($row) {
  return [
    'sku' => (string)$row['sku'],
    'name' => (string)$row['name'],
    'quantity' => (int)$row['quantity'],
    'price' => (float)$row['price']
  ];
}
function validate_inventory_items($data) {
  $items = [];
  if (is_array($data)) {
    if (array_keys($data) === range(0, count($data) - 1)) {
      foreach ($data as $row) {
        if (!is_valid_item($row)) return false;
        $items[] = normalize_item($row);
      }
      return $items;
    } elseif (isset($data['sku'], $data['name'], $data['quantity'], $data['price'])) {
      if (!is_valid_item($data)) return false;
      $items[] = normalize_item($data);
      return $items;
    }
  }
  return false;
}
function load_inventory($path) {
  if (!file_exists($path)) return [];
  $content = @file_get_contents($path);
  if ($content === false) return [];
  $data = @json_decode($content, true);
  if (!is_array($data)) return [];
  return $data;
}
function save_inventory($path, $data) {
  $tmp = $path . '.tmp';
  $json = json_encode($data, JSON_UNESCAPED_UNICODE);
  if ($json === false) return false;
  if (false === @file_put_contents($tmp, $json, LOCK_EX)) return false;
  return @rename($tmp, $path);
}
function process_inventory_items($items) {
  $current = load_inventory(INVENTORY_FILE);
  foreach ($items as $it) {
    $entry = [
      'sku' => $it['sku'],
      'name' => $it['name'],
      'quantity' => (int)$it['quantity'],
      'price' => (float)$it['price'],
      'processed_at' => date('Y-m-d H:i:s')
    ];
    $current[] = $entry;
  }
  return save_inventory(INVENTORY_FILE, $current);
}

$items_to_display = [];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $source = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $raw = isset($_POST['serialized_data']) ? $_POST['serialized_data'] : '';
  write_log("source=$source action=deserialize status=attempt data_preview=" . substr($raw, 0, 256));
  $des = safe_unserialize($raw);
  if ($des === false) {
    $error = 'Invalid serialized data.';
    write_log("source=$source action=deserialize status=failure reason=deserialization_failed data_preview=" . substr($raw, 0, 256));
  } else {
    $validated = validate_inventory_items($des);
    if ($validated === false) {
      $error = 'Invalid data schema.';
      write_log("source=$source action=deserialize status=failure reason=schema_invalid data_preview=" . substr($raw, 0, 256));
    } else {
      $ok = process_inventory_items($validated);
      if (!$ok) {
        $error = 'Processing failed.';
        write_log("source=$source action=process status=failure items=" . count($validated));
      } else {
        $items_to_display = $validated;
        write_log("source=$source action=process status=success items=" . count($validated));
      }
    }
  }
}
?>
<!doctype html>
<html>
<head><title>Inventory Data Processor</title></head>
<body>
<h1>Inventory Data Processor</h1>
<form method="post" action="">
<label for="serialized_data">Serialized Data</label><br>
<textarea id="serialized_data" name="serialized_data" rows="12" cols="80"><?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  echo htmlspecialchars(isset($_POST['serialized_data']) ? $_POST['serialized_data'] : '');
}
?></textarea><br>
<input type="submit" value="Process">
</form>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($error !== null) {
    echo '<p>' . htmlspecialchars($error) . '</p>';
  } else {
    echo '<p>Items processed: ' . count($items_to_display) . '</p>';
    if (!empty($items_to_display)) {
      echo '<table border="1" cellpadding="4" cellspacing="0">';
      echo '<tr><th>SKU</th><th>Name</th><th>Quantity</th><th>Price</th><th>Processed At</th></tr>';
      foreach ($items_to_display as $it) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($it['sku']) . '</td>';
        echo '<td>' . htmlspecialchars($it['name']) . '</td>';
        echo '<td>' . htmlspecialchars($it['quantity']) . '</td>';
        echo '<td>' . number_format((float)$it['price'], 2) . '</td>';
        echo '<td>' . htmlspecialchars(isset($it['processed_at']) ? $it['processed_at'] : '') . '</td>';
        echo '</tr>';
      }
      echo '</table>';
    }
  }
} ?>

</body>
</html>
?>