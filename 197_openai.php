<?php
declare(strict_types=1);

session_start();

define('MAX_PAYLOAD_BYTES', 1024 * 1024);

function autoload_class($class) {
    $paths = [
        __DIR__ . '/../classes/' . $class . '.php',
        __DIR__ . '/../handlers/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require $path;
            return;
        }
    }
}
spl_autoload_register('autoload_class');

function getDbConnection(): ?PDO {
    $host = getenv('DB_HOST');
    $dbname = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');

    if ($host && $dbname) {
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $user ?? '', $pass ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            // Fall through to SQLite fallback
        }
    }

    $sqlitePath = __DIR__ . '/../data/inventory.sqlite';
    try {
        $dir = dirname($sqlitePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $pdo = new PDO('sqlite:' . $sqlitePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS inventory_items (
                sku VARCHAR(64) PRIMARY KEY,
                name VARCHAR(255),
                quantity INT,
                price DECIMAL(10,2),
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}

if (!class_exists('InventoryService')) {
    class InventoryService {
        private PDO $pdo;
        public function __construct(PDO $pdo) {
            $this->pdo = $pdo;
        }
        public function upsertItem(string $sku, string $name, int $qty, float $price): bool {
            $stmt = $this->pdo->prepare("
                INSERT INTO inventory_items (sku, name, quantity, price, updated_at)
                VALUES (:sku, :name, :qty, :price, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    name = :name2,
                    quantity = :qty2,
                    price = :price2,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $params = [
                ':sku' => $sku,
                ':name' => $name,
                ':qty' => $qty,
                ':price' => $price,
                ':name2' => $name,
                ':qty2' => $qty,
                ':price2' => $price,
            ];
            return $stmt->execute($params);
        }
    }
}

if (!class_exists('InventoryHandler')) {
    class InventoryHandler {
        private ?InventoryService $service;
        public function __construct(?InventoryService $service) {
            $this->service = $service;
        }
        public function process(array $items): array {
            $results = [];
            if (!$this->service) {
                foreach ($items as $idx => $it) {
                    $results[] = [
                        'index' => $idx,
                        'sku' => isset($it['sku']) ? (string)$it['sku'] : '',
                        'status' => 'DB Unavailable'
                    ];
                }
                return $results;
            }
            foreach ($items as $idx => $it) {
                $sku = isset($it['sku']) ? (string)$it['sku'] : '';
                $name = isset($it['name']) ? (string)$it['name'] : '';
                $qty = isset($it['quantity']) ? (int)$it['quantity'] : 0;
                $price = isset($it['price']) ? (float)$it['price'] : 0.0;

                if ($sku === '') {
                    $results[] = ['index' => $idx, 'sku' => '', 'status' => 'Invalid: missing sku'];
                    continue;
                }
                try {
                    $ok = $this->service->upsertItem($sku, $name, $qty, $price);
                    $results[] = ['index' => $idx, 'sku' => $sku, 'status' => $ok ? 'Updated' : 'Failed'];
                } catch (Exception $e) {
                    $results[] = ['index' => $idx, 'sku' => $sku, 'status' => 'Error: ' . $e->getMessage()];
                }
            }
            return $results;
        }
    }
}

$pdo = getDbConnection();

$processError = '';
$results = [];
$processTime = '';
$processedCount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payload'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        $processError = 'Invalid CSRF token';
    } else {
        $payload = $_POST['payload'];
        $payloadBytes = function_exists('mb_strlen') ? mb_strlen($payload, '8bit') : strlen($payload);
        if ($payloadBytes > MAX_PAYLOAD_BYTES) {
            $processError = 'Payload too large';
        } else {
            $data = @unserialize($payload, ['allowed_classes' => false]);
            if (!is_array($data)) {
                $processError = 'Invalid serialized data';
            } else {
                $service = $pdo ? new InventoryService($pdo) : null;
                $handler = new InventoryHandler($service);
                $start = microtime(true);
                $results = $handler->process($data);
                $duration = microtime(true) - $start;
                $processTime = number_format($duration, 4) . 's';
                $processedCount = count($data);
            }
        }
    }
} else {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Inventory - Serialized Data Processor</title>
</head>
<body>
<h1>Inventory Update - Serialized Data Processor</h1>
<form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">
  <textarea name="payload" rows="12" cols="80" placeholder="Paste PHP serialized data here (array of items). Example: a:2:{s:3:"sku";s:6:"ABC123";s:8:"quantity";i:10;}"></textarea><br/>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
  <button type="submit">Process Serialized Data</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payload']) && empty($processError)) {
    if (!empty($results)) {
        echo "<h2>Processing Results</h2>";
        echo "<p>Items processed: ".htmlspecialchars((string)$processedCount, ENT_QUOTES, 'UTF-8')."</p>";
        echo "<p>Processing time: ".$processTime."</p>";
        echo "<table border=\"1\" cellpadding=\"4\" cellspacing=\"0\"><tr><th>Index</th><th>SKU</th><th>Status</th></tr>";
        foreach ($results as $r) {
            echo "<tr><td>".htmlspecialchars((string)$r['index'], ENT_QUOTES, 'UTF-8')."</td><td>".htmlspecialchars((string)($r['sku'] ?? ''), ENT_QUOTES, 'UTF-8')."</td><td>".htmlspecialchars((string)$r['status'], ENT_QUOTES, 'UTF-8')."</td></tr>";
        }
        echo "</table>";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($processError)) {
    echo "<p style=\"color:red;\">Error: ".htmlspecialchars($processError, ENT_QUOTES, 'UTF-8')."</p>";
}
?>

</body>
</html>
?>