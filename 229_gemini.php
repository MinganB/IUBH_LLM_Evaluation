<?php

class Logger
{
    private string $logFilePath;

    public function __construct(string $logFilePath)
    {
        $this->logFilePath = $logFilePath;
        $logDir = dirname($this->logFilePath);
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0775, true)) {
                throw new Exception("Logger: Could not create log directory {$logDir}");
            }
        }
        if (!is_writable($logDir)) {
            throw new Exception("Logger: Log directory {$logDir} is not writable");
        }
    }

    private function write(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}";

        if (!empty($context)) {
            $logEntry .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $logEntry .= PHP_EOL;

        if (file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            error_log("Logger: Failed to write to log file: {$this->logFilePath}");
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }
}

<?php

require_once 'Logger.php';

$logDirectory = __DIR__ . '/logs';
$logFilePath = $logDirectory . '/inventory_management.log';

try {
    $logger = new Logger($logFilePath);

    $logger->debug('User accessed product list.', [
        'userId' => 123,
        'page' => 'products/list'
    ]);

    $logger->info('New product added to inventory.', [
        'productId' => 'SKU001',
        'productName' => 'Laptop Pro',
        'quantity' => 50,
        'addedBy' => 'Admin'
    ]);

    $logger->info('Inventory updated for product SKU001.', [
        'productId' => 'SKU001',
        'oldQuantity' => 50,
        'newQuantity' => 45,
        'reason' => 'Sales order #1001'
    ]);

    $logger->error('Failed to update product price.', [
        'productId' => 'SKU002',
        'attemptedPrice' => 1200.00,
        'reason' => 'Database connection failed',
        'errorCode' => 500
    ]);

    $logger->error('Attempted to retrieve details for non-existent product.', [
        'productId' => 'NONEXISTENT_SKU',
        'action' => 'view_product_details'
    ]);

    $logger->info('User John Doe logged in successfully.', [
        'userId' => 456,
        'ipAddress' => '192.168.1.100'
    ]);

    $logger->debug('Scheduled inventory reconciliation started.', [
        'processId' => 'INV_REC_001',
        'startTime' => date('H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Application Critical Error: Could not initialize logger. " . $e->getMessage());
    exit(1);
}
?>