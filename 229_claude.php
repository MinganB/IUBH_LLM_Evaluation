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

    public function __construct($logFile = 'inventory.log', $logLevel = 'DEBUG')
    {
        $this->logFile = $logFile;
        $this->currentLogLevel = $this->logLevels[$logLevel] ?? 1;
        
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
        }
    }

    private function writeLog($level, $message)
    {
        if ($this->logLevels[$level] >= $this->currentLogLevel) {
            $timestamp = date('Y-m-d H:i:s');
            $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
            file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
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
        $level = strtoupper($level);
        if (array_key_exists($level, $this->logLevels)) {
            $this->writeLog($level, $message);
        }
    }

    public function setLogLevel($level)
    {
        $this->currentLogLevel = $this->logLevels[$level] ?? 1;
    }

    public function getLogFile()
    {
        return $this->logFile;
    }

    public function clearLog()
    {
        file_put_contents($this->logFile, '');
    }
}


**app.php**

<?php

require_once 'Logger.php';

$logger = new Logger('inventory_system.log', 'DEBUG');

$logger->info('Inventory management system started');

$logger->debug('Checking database connection');
$logger->info('Database connection established successfully');

$logger->info('User "admin" logged into the system');

$logger->debug('Processing inventory item addition - Product ID: INV001');
$logger->info('New product added: Widget A - Quantity: 100 - Price: $15.99');

$logger->debug('Validating inventory levels for Product ID: INV002');
$logger->warning('Low stock alert: Widget B - Current quantity: 5 - Minimum threshold: 10');

$logger->debug('Processing sale transaction - Order ID: ORD001');
$logger->info('Sale completed: Product INV001 - Quantity sold: 3 - Customer ID: CUST123');

$logger->debug('Attempting to update non-existent product: INV999');
$logger->error('Failed to update product: Product ID INV999 not found in database');

$logger->debug('Processing bulk inventory update from CSV file');
$logger->error('Bulk update failed: Invalid CSV format on line 15');

$logger->warning('Multiple failed login attempts detected for user "guest"');

$logger->critical('Database connection lost during inventory synchronization');
$logger->error('Inventory sync failed: Unable to connect to remote warehouse database');

$logger->info('Daily inventory backup started');
$logger->info('Daily inventory backup completed successfully - 1,247 items backed up');

$logger->debug('System maintenance check initiated');
$logger->warning('Disk space running low: 85% capacity reached');

$logger->info('User "manager" exported monthly inventory report');

$logger->debug('Cleaning up temporary files and cache');
$logger->info('System cleanup completed');

$logger->info('Inventory management system shutting down');