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
        $sanitizedMessage = filter_var($message, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        $logEntry = "[{$timestamp}] [{$level}]: {$sanitizedMessage}" . PHP_EOL;
        
        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}


**app.php**

<?php

require_once 'Logger.php';

$logger = new Logger();

$logger->debug('This is a debug message');
$logger->info('This is an info message');
$logger->warning('This is a warning message');
$logger->error('This is an error message');