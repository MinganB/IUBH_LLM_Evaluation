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

    public function __construct($logFile = 'logs/app.log')
    {
        $this->logFile = $logFile;
        $this->ensureLogDirectory();
    }

    private function ensureLogDirectory()
    {
        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function writeLog($level, $message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
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

    public function log($level, $message)
    {
        if (isset($this->logLevels[strtoupper($level)])) {
            $this->writeLog(strtoupper($level), $message);
        }
    }
}


<?php

require_once 'Logger.php';

$logger = new Logger('logs/inventory_system.log');

$logger->info('Inventory management system started');

$logger->debug('User authentication process initiated for user ID: 12345');

$logger->info('User admin@example.com logged in successfully');

$logger->info('Product SKU-001 added to inventory with quantity: 100');

$logger->warning('Low stock alert: Product SKU-002 has only 5 units remaining');

$logger->error('Failed to update product SKU-003: Database connection timeout');

$logger->info('Inventory report generated for date range: 2024-01-01 to 2024-01-31');

$logger->debug('Cache cleared for product categories');

$logger->error('Payment processing failed for order ID: ORD-789: Invalid card number');

$logger->critical('Database server connection lost - system unavailable');

$logger->warning('Multiple failed login attempts detected for user: suspicious@domain.com');

$logger->info('Backup process completed successfully at ' . date('Y-m-d H:i:s'));

$logger->debug('API request processed: GET /api/products/search?query=laptop');

$logger->error('File upload failed: Maximum file size exceeded for product image');

$logger->info('User admin@example.com logged out');

$logger->info('Inventory management system shutdown');
?>