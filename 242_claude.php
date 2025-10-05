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
        $sanitizedMessage = filter_var($message, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        $logEntry = "[{$timestamp}] [{$level}]: {$sanitizedMessage}" . PHP_EOL;
        
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir) && !mkdir($logDir, 0755, true)) {
            return false;
        }

        if (file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            return false;
        }
        
        return true;
    }
}


<?php

require_once 'Logger.php';

$logger = new Logger('app.log');

$logger->debug('User session started for inventory management system');
$logger->info('Product inventory loaded successfully');
$logger->warning('Low stock alert: Product ID 12345 has only 5 items remaining');
$logger->error('Database connection failed while updating inventory');
?>