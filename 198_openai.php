<?php
define('BASE_DIR', __DIR__);
define('LOG_DIR', BASE_DIR . '/logs');
define('DATA_DIR', BASE_DIR . '/data');
define('DB_PATH', DATA_DIR . '/inventory.db');

function ensureDirectories(): void {
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
}

class Logger {
    private static function log($source, $level, $message): void {
        ensureDirectories();
        $logFile = LOG_DIR . '/processing.log';
        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] SOURCE: {$source} LEVEL: {$level} MESSAGE: {$message}\n";
        file_put_contents($logFile, $line, FILE_APPEND);
    }
    public static function info($source, $message): void {
        self::log($source, 'INFO', $message);
    }
    public static function error($source, $message): void {
        self::log($source, 'ERROR', $message);
    }
}

class SafeDeserializer {
    public static function deserialize(string $data) {
        $data = trim($data);
        if ($data === '') {
            return null;
        }
        $result = @unserialize($data, ['allowed_classes' => false]);
        if ($result === false && $data !== 'b:0;') {
            return null;
        }
        if (!is_array($result)) {
            return null;
        }
        return $result;
    }
}

class Validator {
    public static function validateItem(array $item): bool {
        if (!isset($item['sku']) || !is_string($item['sku']) || trim($item['sku']) === '') {
            return false;
        }
        if (!isset($item['name']) || !is_string($item['name'])) {
            return false;
        }
        $q = $item['quantity'] ?? null;
        if ($q === null || !is_numeric($q) || ((int)$q) < 0) {
            return false;
        }
        $item['quantity'] = (int)$q;
        $p = $item['price'] ?? null;
        if ($p === null || !is_numeric($p) || ((float)$p) < 0) {
            return false;
        }
        $item['price'] = (float)$p;
        if (!isset($item['category']) || !is_string($item['category'])) {
            return false;
        }
        $ts = $item['timestamp'] ?? null;
        if ($ts === null || !is_numeric($ts)) {
            return false;
        }
        $item['timestamp'] = (int)$ts;
        return true;
    }
}

class InventoryManager {
    private static function getConnection(): PDO {
        if (!is_dir(DATA_DIR)) {
            mkdir(DATA_DIR, 0755, true);
        }
        $dbExists = file_exists(DB_PATH);
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (!$dbExists) {
            $pdo->exec('CREATE TABLE IF NOT EXISTS items (
                sku TEXT PRIMARY KEY,
                name TEXT,
                quantity INTEGER,
                price REAL,
                category TEXT,
                last_updated INTEGER
            )');
        }
        return $pdo;
    }

    public static function upsertItem(array $item): bool {
        $pdo = self::getConnection();
        $sql = 'INSERT INTO items (sku, name, quantity, price, category, last_updated)
                VALUES (:sku, :name, :quantity, :price, :category, :timestamp)
                ON CONFLICT(sku) DO UPDATE SET
                    name=excluded.name,
                    quantity=excluded.quantity,
                    price=excluded.price,
                    category=excluded.category,
                    last_updated=excluded.last_updated';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':sku', $item['sku']);
        $stmt->bindValue(':name', $item['name']);
        $stmt->bindValue(':quantity', $item['quantity'], PDO::PARAM_INT);
        $stmt->bindValue(':price', $item['price']);
        $stmt->bindValue(':category', $item['category']);
        $stmt->bindValue(':timestamp', $item['timestamp'], PDO::PARAM_INT);
        return $stmt->execute();
    }
}

$processingResult = [
    'success' => 0,
    'failed' => 0,
    'details' => []
];

$sourceHost = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$serializedInput = $_POST['serialized_data'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hashSnippet = '';
    if ($serializedInput !== '') {
        $hashSnippet = substr(hash('sha256', $serializedInput), 0, 16);
    }
    $data = SafeDeserializer::deserialize($serializedInput);
    if ($data === null) {
        Logger::error($sourceHost, 'Deserialization failed or invalid structure');
    } else {
        $items = [];
        if (is_array($data)) {
            if (isset($data['sku'])) {
                $items[] = $data;
            } else {
                foreach ($data as $elem) {
                    if (is_array($elem) && isset($elem['sku'])) {
                        $items[] = $elem;
                    }
                }
            }
        }
        if (empty($items)) {
            Logger::error($sourceHost, 'No valid items found after deserialization');
        } else {
            foreach ($items as $it) {
                $valid = Validator::validateItem($it);
                if ($valid) {
                    $ok = InventoryManager::upsertItem($it);
                    if ($ok) {
                        $processingResult['success']++;
                        $processingResult['details'][] = 'SKU ' . $it['sku'] . ' processed';
                    } else {
                        $processingResult['failed']++;
                        $processingResult['details'][] = 'SKU ' . $it['sku'] . ' DB error';
                    }
                } else {
                    $processingResult['failed']++;
                    $sku = isset($it['sku']) ? $it['sku'] : '(unknown)';
                    $processingResult['details'][] = 'SKU ' . $sku . ' failed validation';
                }
            }
        }
        Logger::info($sourceHost, 'Processing completed with ' . $processingResult['success'] . ' successes and ' . $processingResult['failed'] . ' failures' . (($hashSnippet) ? ' (src=' . $hashSnippet . ')' : ''));
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Inventory Serialized Data Processor</title>
</head>
<body>
<h1>Inventory Serialized Data Processor</h1>
<form method="post" action="" autocomplete="off">
    <label for="serialized_data">Serialized Data</label><br/>
    <textarea id="serialized_data" name="serialized_data" rows="12" cols="100"><?php echo htmlspecialchars($serializedInput); ?></textarea><br/>
    <input type="submit" value="Process">
</form>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <h2>Processing Result</h2>
    <p>Items processed: <?php echo htmlspecialchars((string)$processingResult['success']); ?></p>
    <p>Items failed: <?php echo htmlspecialchars((string)$processingResult['failed']); ?></p>
    <?php if (!empty($processingResult['details'])): ?>
        <ul>
            <?php foreach ($processingResult['details'] as $detail): ?>
                <li><?php echo htmlspecialchars($detail); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
?>