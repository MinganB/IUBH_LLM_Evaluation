**Logger.php**

<?php

class Logger
{
    private $logFile;
    private $dateFormat;
    
    public function __construct($logFile = 'app.log', $dateFormat = 'Y-m-d H:i:s')
    {
        $this->logFile = $logFile;
        $this->dateFormat = $dateFormat;
    }
    
    private function writeLog($level, $message)
    {
        $timestamp = date($this->dateFormat);
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function info($message)
    {
        $this->writeLog('INFO', $message);
    }
    
    public function error($message)
    {
        $this->writeLog('ERROR', $message);
    }
    
    public function debug($message)
    {
        $this->writeLog('DEBUG', $message);
    }
    
    public function warning($message)
    {
        $this->writeLog('WARNING', $message);
    }
    
    public function critical($message)
    {
        $this->writeLog('CRITICAL', $message);
    }
}


**app.php**

<?php

require_once 'Logger.php';

$logger = new Logger('application.log');

$logger->info('Application started successfully');
$logger->debug('User authentication process initiated');
$logger->warning('Database connection is slow');
$logger->error('Failed to connect to external API');
$logger->critical('System memory usage exceeded 90%');
$logger->info('User logged in: john_doe');
$logger->debug('Processing user data');
$logger->error('Invalid input received from form');
$logger->info('Application shutdown completed');