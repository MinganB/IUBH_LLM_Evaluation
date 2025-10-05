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
        $sanitizedMessage = htmlspecialchars(strip_tags($message), ENT_QUOTES, 'UTF-8');
        $logEntry = "[{$timestamp}] [{$level}]: {$sanitizedMessage}" . PHP_EOL;
        
        $handle = fopen($this->logFile, 'a');
        if ($handle !== false) {
            flock($handle, LOCK_EX);
            fwrite($handle, $logEntry);
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

?>


**app.php**

<?php

require_once 'Logger.php';

$logger = new Logger();

$logger->debug('Inventory system initialized successfully');
$logger->info('User authenticated and logged into inventory management system');
$logger->warning('Low stock alert for product ID 12345 - only 5 items remaining');
$logger->error('Database connection failed while updating inventory records');

?>