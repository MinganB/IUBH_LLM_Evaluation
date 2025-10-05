**Logger.php**

<?php

class Logger
{
    private $logFile;

    public function __construct($logFile = 'app.log')
    {
        $this->logFile = $logFile;
    }

    public function debug($message)
    {
        $this->writeToLog('DEBUG', $message);
    }

    public function info($message)
    {
        $this->writeToLog('INFO', $message);
    }

    public function warning($message)
    {
        $this->writeToLog('WARNING', $message);
    }

    public function error($message)
    {
        $this->writeToLog('ERROR', $message);
    }

    private function writeToLog($level, $message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}]: {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}


**app.php**

<?php

require_once 'Logger.php';

$logger = new Logger();

$logger->debug('Inventory system startup initiated');
$logger->info('User logged in successfully - UserID: 12345');
$logger->warning('Low stock alert - Product SKU: ABC123 has only 5 units remaining');
$logger->error('Database connection failed - Unable to update inventory records');