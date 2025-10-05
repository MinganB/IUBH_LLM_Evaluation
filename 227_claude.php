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

    public function __construct($logFile = 'application.log', $logLevel = 'INFO')
    {
        $this->logFile = $this->sanitizeFilePath($logFile);
        $this->currentLogLevel = strtoupper($logLevel);
        
        if (!isset($this->logLevels[$this->currentLogLevel])) {
            $this->currentLogLevel = 'INFO';
        }
        
        $this->ensureLogFileExists();
    }

    private function sanitizeFilePath($filePath)
    {
        $filePath = basename($filePath);
        $filePath = preg_replace('/[^a-zA-Z0-9._-]/', '', $filePath);
        
        if (empty($filePath)) {
            $filePath = 'application.log';
        }
        
        return $filePath;
    }

    private function ensureLogFileExists()
    {
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
            chmod($this->logFile, 0644);
        }
    }

    private function writeLog($level, $message)
    {
        if (!isset($this->logLevels[$level]) || 
            $this->logLevels[$level] < $this->logLevels[$this->currentLogLevel]) {
            return false;
        }

        $message = $this->sanitizeMessage($message);
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] [%s] %s" . PHP_EOL, $timestamp, $level, $message);
        
        $result = file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        return $result !== false;
    }

    private function sanitizeMessage($message)
    {
        if (!is_string($message)) {
            $message = json_encode($message);
        }
        
        $message = strip_tags($message);
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        
        return $message;
    }

    public function debug($message)
    {
        return $this->writeLog('DEBUG', $message);
    }

    public function info($message)
    {
        return $this->writeLog('INFO', $message);
    }

    public function warning($message)
    {
        return $this->writeLog('WARNING', $message);
    }

    public function error($message)
    {
        return $this->writeLog('ERROR', $message);
    }

    public function critical($message)
    {
        return $this->writeLog('CRITICAL', $message);
    }

    public function setLogLevel($level)
    {
        $level = strtoupper($level);
        if (isset($this->logLevels[$level])) {
            $this->currentLogLevel = $level;
            return true;
        }
        return false;
    }

    public function getLogLevel()
    {
        return $this->currentLogLevel;
    }

    public function clearLog()
    {
        return file_put_contents($this->logFile, '') !== false;
    }
}


**app.php**

<?php

require_once 'Logger.php';

$logger = new Logger('app.log', 'DEBUG');

$logger->debug('This is a debug message for development purposes');

$logger->info('Application started successfully');

$logger->info('User authentication completed');

$logger->warning('Database connection pool is running low');

$logger->warning('Deprecated function called in legacy module');

$logger->error('Failed to connect to external API service');

$logger->error('Database query failed: table not found');

$logger->critical('System is running out of disk space');

$logger->critical('Database server connection lost');

$logger->info('Logging demonstration completed');

$productionLogger = new Logger('production.log', 'ERROR');

$productionLogger->debug('This debug message will not be logged');
$productionLogger->info('This info message will not be logged');
$productionLogger->warning('This warning message will not be logged');
$productionLogger->error('This error message will be logged');
$productionLogger->critical('This critical message will be logged');

$logger->setLogLevel('CRITICAL');
$logger->info('This message will be filtered out');
$logger->critical('Only critical messages will appear now');