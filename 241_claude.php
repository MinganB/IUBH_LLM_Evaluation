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

    private function writeLog($level, $message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level]: $message" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}


<?php

require_once 'Logger.php';

$logger = new Logger();

$logger->debug('User login attempt for username: admin');
$logger->info('Inventory item added: Product ID 12345');
$logger->warning('Low stock alert: Product ID 67890 has only 5 items remaining');
$logger->error('Database connection failed during inventory update');
?>