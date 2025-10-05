<?php

class Logger
{
    const DEBUG = 'DEBUG';
    const INFO = 'INFO';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    const CRITICAL = 'CRITICAL';

    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    private function writeLog(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] %s: %s\n", $timestamp, $level, $message);
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function debug(string $message): void
    {
        $this->writeLog(self::DEBUG, $message);
    }

    public function info(string $message): void
    {
        $this->writeLog(self::INFO, $message);
    }

    public function warning(string $message): void
    {
        $this->writeLog(self::WARNING, $message);
    }

    public function error(string $message): void
    {
        $this->writeLog(self::ERROR, $message);
    }

    public function critical(string $message): void
    {
        $this->writeLog(self::CRITICAL, $message);
    }
}
<?php

require_once 'Logger.php';

$logFile = __DIR__ . '/application.log';
$logger = new Logger($logFile);

$logger->info('Application started successfully.');
$logger->debug('User session ID: abcdef12345. Request path: /dashboard');
$logger->warning('Deprecated function called in module XYZ.');
$logger->error('Database connection failed: Access denied for user \'guest\'.');
$logger->critical('Unhandled exception caught: Fatal error in core system.');

echo "Log messages have been written to {$logFile}\n";
?>