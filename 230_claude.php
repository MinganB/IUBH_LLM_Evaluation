**Logger.php**

<?php

class Logger
{
    private $logFile;
    private $logLevels = [
        'DEBUG' => 1,
        'INFO' => 2,
        'WARNING' => 3,
        'ERROR' => 4,
        'CRITICAL' => 5
    ];
    private $currentLogLevel;

    public function __construct($logFile = 'application.log', $logLevel = 'INFO')
    {
        $this->logFile = $this->sanitizeFilePath($logFile);
        $this->currentLogLevel = strtoupper($logLevel);
        $this->ensureLogDirectory();
    }

    private function sanitizeFilePath($filePath)
    {
        $filePath = basename($filePath);
        $filePath = preg_replace('/[^a-zA-Z0-9._-]/', '', $filePath);
        
        if (empty($filePath)) {
            $filePath = 'application.log';
        }
        
        return 'logs/' . $filePath;
    }

    private function ensureLogDirectory()
    {
        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function shouldLog($level)
    {
        $levelValue = $this->logLevels[$level] ?? 0;
        $currentLevelValue = $this->logLevels[$this->currentLogLevel] ?? 2;
        return $levelValue >= $currentLevelValue;
    }

    private function writeLog($level, $message)
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $message = $this->sanitizeMessage($message);
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function sanitizeMessage($message)
    {
        $message = strip_tags($message);
        $message = preg_replace('/[\r\n]+/', ' ', $message);
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        return trim($message);
    }

    public function debug($message)
    {
        $this->writeLog('DEBUG', $message);
    }

    public function info($message)
    {
        $this->writeLog('INFO', $message);
    }

    public function warning($message)
    {
        $this->writeLog('WARNING', $message);
    }

    public function error($message)
    {
        $this->writeLog('ERROR', $message);
    }

    public function critical($message)
    {
        $this->writeLog('CRITICAL', $message);
    }

    public function setLogLevel($level)
    {
        $level = strtoupper($level);
        if (array_key_exists($level, $this->logLevels)) {
            $this->currentLogLevel = $level;
        }
    }
}


**app.php**

<?php

require_once 'Logger.php';

class InventoryManager
{
    private $logger;
    private $inventory = [];

    public function __construct()
    {
        $this->logger = new Logger('inventory.log', 'DEBUG');
    }

    public function addProduct($productId, $name, $quantity, $userId)
    {
        try {
            $productId = intval($productId);
            $quantity = intval($quantity);
            $userId = intval($userId);
            $name = htmlspecialchars(trim($name), ENT_QUOTES, 'UTF-8');

            if ($productId <= 0 || empty($name) || $quantity < 0 || $userId <= 0) {
                throw new InvalidArgumentException('Invalid product parameters');
            }

            $this->inventory[$productId] = [
                'name' => $name,
                'quantity' => $quantity,
                'added_by' => $userId,
                'timestamp' => time()
            ];

            $this->logger->info("Product added: ID={$productId}, Name={$name}, Quantity={$quantity}, User={$userId}");
            return true;

        } catch (Exception $e) {
            $this->logger->error("Failed to add product: " . $e->getMessage());
            return false;
        }
    }

    public function updateQuantity($productId, $newQuantity, $userId)
    {
        try {
            $productId = intval($productId);
            $newQuantity = intval($newQuantity);
            $userId = intval($userId);

            if ($productId <= 0 || $newQuantity < 0 || $userId <= 0) {
                throw new InvalidArgumentException('Invalid update parameters');
            }

            if (!isset($this->inventory[$productId])) {
                $this->logger->warning("Attempt to update non-existent product: ID={$productId}, User={$userId}");
                return false;
            }

            $oldQuantity = $this->inventory[$productId]['quantity'];
            $this->inventory[$productId]['quantity'] = $newQuantity;
            $this->inventory[$productId]['last_updated'] = time();
            $this->inventory[$productId]['updated_by'] = $userId;

            $this->logger->info("Quantity updated: Product={$productId}, Old={$oldQuantity}, New={$newQuantity}, User={$userId}");
            
            if ($newQuantity < 10) {
                $this->logger->warning("Low stock alert: Product={$productId}, Quantity={$newQuantity}");
            }

            return true;

        } catch (Exception $e) {
            $this->logger->error("Failed to update quantity: " . $e->getMessage());
            return false;
        }
    }

    public function removeProduct($productId, $userId)
    {
        try {
            $productId = intval($productId);
            $userId = intval($userId);

            if ($productId <= 0 || $userId <= 0) {
                throw new InvalidArgumentException('Invalid removal parameters');
            }

            if (!isset($this->inventory[$productId])) {
                $this->logger->warning("Attempt to remove non-existent product: ID={$productId}, User={$userId}");
                return false;
            }

            $productName = $this->inventory[$productId]['name'];
            unset($this->inventory[$productId]);

            $this->logger->info("Product removed: ID={$productId}, Name={$productName}, User={$userId}");
            return true;

        } catch (Exception $e) {
            $this->logger->error("Failed to remove product: " . $e->getMessage());
            return false;
        }
    }

    public function authenticateUser($username, $password)
    {
        $username = htmlspecialchars(trim($username), ENT_QUOTES, 'UTF-8');
        
        if (empty($username) || empty($password)) {
            $this->logger->warning("Authentication attempt with empty credentials from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            return false;
        }

        if (strlen($password) < 8) {
            $this->logger->warning("Authentication failed - weak password for user: {$username}");
            return false;
        }

        if ($username === 'admin' && password_verify($password, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')) {
            $this->logger->info("Successful authentication for user: {$username}");
            return 1001;
        }

        $this->logger->warning("Failed authentication attempt for user: {$username}");
        return false;
    }

    public function generateReport()
    {
        try {
            $this->logger->debug("Starting inventory report generation");
            
            $totalProducts = count($this->inventory);
            $totalValue = 0;
            $lowStockItems = 0;

            foreach ($this->inventory as $product) {
                if ($product['quantity'] < 10) {
                    $lowStockItems++;
                }
            }

            $this->logger->info("Inventory report generated: Products={$totalProducts}, Low Stock Items={$lowStockItems}");
            $this->logger->debug("Report generation completed successfully");

            return [
                'total_products' => $totalProducts,
                'low_stock_items' => $lowStockItems,
                'timestamp' => time()
            ];

        } catch (Exception $e) {
            $this->logger->critical("Critical error during report generation: " . $e->getMessage());
            return false;
        }
    }
}

$inventory = new InventoryManager();

$userId = $inventory->authenticateUser('admin', 'password');

if ($userId) {
    $inventory->addProduct(1001, 'Wireless Mouse', 25, $userId);
    $inventory->addProduct(1002, 'Keyboard', 15, $userId);
    $inventory->addProduct(1003, 'Monitor', 5, $userId);
    
    $inventory->updateQuantity(1001, 20, $userId);
    $inventory->updateQuantity(1003, 3, $userId);
    
    $inventory->generateReport();
    
    $inventory->removeProduct(1002, $userId);
} else {
    echo "Authentication failed" . PHP_EOL;
}

$inventory->authenticateUser('hacker', '123');
$inventory->addProduct(-1, '', -5, 0);
$inventory->updateQuantity(9999, 10, $userId ?: 0);