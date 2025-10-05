<?php

class Logger
{
    private string $logFile;
    private const DATE_FORMAT = 'Y-m-d H:i:s';
    private const LOG_LEVELS = [
        'EMERGENCY' => 600,
        'CRITICAL'  => 500,
        'ERROR'     => 400,
        'WARNING'   => 300,
        'INFO'      => 200,
        'DEBUG'     => 100,
    ];

    public function __construct(string $logFilePath)
    {
        $this->logFile = $logFilePath;
        $logDir = dirname($this->logFile);

        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0775, true)) {
                throw new Exception("Logger: Could not create log directory '{$logDir}'. Check permissions.");
            }
        }

        if (!is_writable($logDir)) {
            throw new Exception("Logger: Log directory '{$logDir}' is not writable. Check permissions.");
        }

        if (file_exists($this->logFile) && !is_writable($this->logFile)) {
            throw new Exception("Logger: Log file '{$this->logFile}' is not writable. Check permissions.");
        }
    }

    public function emergency(string $message): void
    {
        $this->writeLog('EMERGENCY', $message);
    }

    public function critical(string $message): void
    {
        $this->writeLog('CRITICAL', $message);
    }

    public function error(string $message): void
    {
        $this->writeLog('ERROR', $message);
    }

    public function warning(string $message): void
    {
        $this->writeLog('WARNING', $message);
    }

    public function info(string $message): void
    {
        $this->writeLog('INFO', $message);
    }

    public function debug(string $message): void
    {
        $this->writeLog('DEBUG', $message);
    }

    private function writeLog(string $level, string $message): void
    {
        if (!isset(self::LOG_LEVELS[$level])) {
            $level = 'INFO';
        }

        $timestamp = (new DateTimeImmutable())->format(self::DATE_FORMAT);
        $sanitizedMessage = $this->sanitizeMessage($message);
        $logEntry = "[{$timestamp}] [{$level}] {$sanitizedMessage}" . PHP_EOL;

        if (@file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {

        }
    }

    private function sanitizeMessage(string $message): string
    {
        return preg_replace('/[\r\n\t]/', ' ', $message);
    }
}

<?php

require_once 'Logger.php';

$logDirectory = __DIR__ . '/logs';
$logFilePath = $logDirectory . '/inventory.log';

try {
    $logger = new Logger($logFilePath);

    $logger->debug('Database query for item ID 101: SELECT * FROM products WHERE id = 101');
    $logger->debug('Function `calculateStockValue` entered with parameter `category=Electronics`');

    $logger->info('User "admin" logged in from IP: 192.168.1.100');
    $logger->info('New item "Laptop X200" (SKU: LT-X200) added to inventory by user "admin".');
    $logger->info('Quantity for item "SKU: LT-X200" updated from 50 to 45 after sale. Order ID: 12345');
    $logger->info('User "john.doe" generated a weekly sales report.');

    $logger->warning('Low stock alert: Item "Smartphone Pro" (SKU: SP-P-001) quantity is 5. Reorder needed.');
    $logger->warning('Attempted unauthorized access to product update API from IP: 203.0.113.45.');
    $logger->warning('Failed to send email notification for order 12346. Mail server issue?');

    $logger->error('Database update failed for item "SKU: SP-P-001" due to constraint violation.');
    $logger->error('Product "NonExistentItem" not found when attempting to retrieve details.');
    $logger->error('Payment gateway timed out during processing of order 12347.');

    $logger->critical('Inventory synchronization service failed to connect to warehouse API. Data inconsistency possible.');
    $logger->critical('Failed to load critical configuration file "config.json". Application may not function correctly.');

    $logger->emergency('Database connection lost. All inventory operations are halted.');
    $logger->emergency('Disk space critical on log volume. System might become unresponsive.');

} catch (Exception $e) {
    error_log("Failed to initialize logger: " . $e->getMessage());
}
?>