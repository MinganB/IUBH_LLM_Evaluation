<?php

class Logger {
    private $logFile;
    private $logLevels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];
    private $currentLogLevel;
    private $sensitivePatterns = [
        '/password["\s]*[:=]["\s]*[^,}\s]*/i',
        '/passwd["\s]*[:=]["\s]*[^,}\s]*/i',
        '/pwd["\s]*[:=]["\s]*[^,}\s]*/i',
        '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/',
        '/\b\d{3}-\d{2}-\d{4}\b/',
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
        '/api[_-]?key["\s]*[:=]["\s]*[^,}\s]*/i',
        '/token["\s]*[:=]["\s]*[^,}\s]*/i',
        '/secret["\s]*[:=]["\s]*[^,}\s]*/i'
    ];

    public function __construct($logFile = 'inventory_system.log', $logLevel = 'INFO') {
        $this->logFile = $logFile;
        $this->currentLogLevel = $this->logLevels[strtoupper($logLevel)] ?? $this->logLevels['INFO'];
    }

    private function sanitizeMessage($message) {
        foreach ($this->sensitivePatterns as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', $message);
        }
        return $message;
    }

    private function writeLog($level, $message, $source = null) {
        if ($this->logLevels[$level] < $this->currentLogLevel) {
            return;
        }

        $sanitizedMessage = $this->sanitizeMessage($message);
        $timestamp = date('Y-m-d H:i:s');
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        
        if ($source === null) {
            $source = isset($backtrace[2]['class']) 
                ? $backtrace[2]['class'] . '::' . $backtrace[2]['function']
                : ($backtrace[2]['function'] ?? 'unknown');
        }

        $logEntry = sprintf(
            "[%s] [%s] [%s] %s" . PHP_EOL,
            $timestamp,
            $level,
            $source,
            $sanitizedMessage
        );

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function debug($message, $source = null) {
        $this->writeLog('DEBUG', $message, $source);
    }

    public function info($message, $source = null) {
        $this->writeLog('INFO', $message, $source);
    }

    public function warning($message, $source = null) {
        $this->writeLog('WARNING', $message, $source);
    }

    public function error($message, $source = null) {
        $this->writeLog('ERROR', $message, $source);
    }

    public function critical($message, $source = null) {
        $this->writeLog('CRITICAL', $message, $source);
    }

    public function setLogLevel($level) {
        $level = strtoupper($level);
        if (isset($this->logLevels[$level])) {
            $this->currentLogLevel = $this->logLevels[$level];
        }
    }

    public function getLogFile() {
        return $this->logFile;
    }
}


<?php

require_once 'Logger.php';

class InventoryManager {
    private $logger;
    private $products = [];

    public function __construct() {
        $this->logger = new Logger('inventory.log', 'DEBUG');
    }

    public function addProduct($productId, $name, $quantity, $price) {
        $this->logger->info("Adding new product: ID={$productId}, Name={$name}, Quantity={$quantity}, Price={$price}", 'InventoryManager::addProduct');
        
        if ($quantity < 0) {
            $this->logger->error("Invalid quantity {$quantity} for product {$productId}", 'InventoryManager::addProduct');
            return false;
        }

        $this->products[$productId] = [
            'name' => $name,
            'quantity' => $quantity,
            'price' => $price
        ];

        $this->logger->debug("Product {$productId} successfully added to inventory", 'InventoryManager::addProduct');
        return true;
    }

    public function updateStock($productId, $newQuantity) {
        $this->logger->debug("Attempting to update stock for product {$productId} to {$newQuantity}", 'InventoryManager::updateStock');

        if (!isset($this->products[$productId])) {
            $this->logger->error("Product {$productId} not found in inventory", 'InventoryManager::updateStock');
            return false;
        }

        $oldQuantity = $this->products[$productId]['quantity'];
        $this->products[$productId]['quantity'] = $newQuantity;
        
        $this->logger->info("Stock updated for product {$productId}: {$oldQuantity} -> {$newQuantity}", 'InventoryManager::updateStock');

        if ($newQuantity < 5) {
            $this->logger->warning("Low stock alert: Product {$productId} has only {$newQuantity} units remaining", 'InventoryManager::updateStock');
        }

        return true;
    }

    public function processOrder($orderId, $productId, $quantity, $userCredentials) {
        $this->logger->info("Processing order {$orderId} for product {$productId}, quantity: {$quantity}", 'InventoryManager::processOrder');
        
        $sensitiveData = json_encode($userCredentials);
        $this->logger->debug("User credentials provided: {$sensitiveData}", 'InventoryManager::processOrder');

        if (!isset($this->products[$productId])) {
            $this->logger->error("Order {$orderId} failed: Product {$productId} not found", 'InventoryManager::processOrder');
            return false;
        }

        if ($this->products[$productId]['quantity'] < $quantity) {
            $this->logger->error("Order {$orderId} failed: Insufficient stock. Requested: {$quantity}, Available: {$this->products[$productId]['quantity']}", 'InventoryManager::processOrder');
            return false;
        }

        $this->products[$productId]['quantity'] -= $quantity;
        $this->logger->info("Order {$orderId} completed successfully", 'InventoryManager::processOrder');
        
        return true;
    }

    public function generateReport() {
        $this->logger->info("Generating inventory report", 'InventoryManager::generateReport');
        
        try {
            $totalProducts = count($this->products);
            $totalValue = array_sum(array_map(function($product) {
                return $product['quantity'] * $product['price'];
            }, $this->products));

            $this->logger->info("Report generated: {$totalProducts} products, total value: \${$totalValue}", 'InventoryManager::generateReport');
            
            return [
                'total_products' => $totalProducts,
                'total_value' => $totalValue,
                'products' => $this->products
            ];
        } catch (Exception $e) {
            $this->logger->critical("Failed to generate report: " . $e->getMessage(), 'InventoryManager::generateReport');
            return false;
        }
    }
}

$inventory = new InventoryManager();

$inventory->addProduct('PROD001', 'Laptop Computer', 15, 999.99);
$inventory->addProduct('PROD002', 'Wireless Mouse', 50, 29.99);
$inventory->addProduct('PROD003', 'Keyboard', -5, 79.99);

$inventory->updateStock('PROD001', 3);
$inventory->updateStock('PROD999', 10);

$userCredentials = [
    'username' => 'john_doe',
    'password' => 'secret123',
    'email' => 'john@example.com',
    'credit_card' => '4532-1234-5678-9012',
    'ssn' => '123-45-6789'
];

$inventory->processOrder('ORD001', 'PROD001', 2, $userCredentials);
$inventory->processOrder('ORD002', 'PROD002', 100, $userCredentials);
$inventory->processOrder('ORD003', 'PROD999', 5, $userCredentials);

$report = $inventory->generateReport();

$standaloneLogger = new Logger('app_events.log', 'INFO');
$standaloneLogger->info('Inventory management system started');
$standaloneLogger->debug('Debug mode enabled for troubleshooting');
$standaloneLogger->warning('System running in development mode');
$standaloneLogger->error('Connection timeout occurred');
$standaloneLogger->critical('Database connection failed');
?>