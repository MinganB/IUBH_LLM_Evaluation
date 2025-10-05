<?php

// Logger.php
class Logger
{
    private const LOG_FILE = 'app.log';

    public function debug(string $message): void
    {
        $this->writeLog('debug', $message);
    }

    public function info(string $message): void
    {
        $this->writeLog('info', $message);
    }

    public function warning(string $message): void
    {
        $this->writeLog('warning', $message);
    }

    public function error(string $message): void
    {
        $this->writeLog('error', $message);
    }

    private function writeLog(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = sprintf('[%s] [%s]: %s' . PHP_EOL, $timestamp, strtoupper($level), $message);

        $logFilePath = __DIR__ . '/' . self::LOG_FILE;

        if (file_put_contents($logFilePath, $logMessage, FILE_APPEND | LOCK_EX) === false) {
            error_log('Failed to write to log file: ' . $logFilePath);
        }
    }
}

<?php

// app.php
require_once 'Logger.php';

$logger = new Logger();

$logger->debug('User "john.doe" accessed product list page.');
$logger->info('New product "Laptop XYZ" added to inventory by admin.');
$logger->warning('Inventory for product "Mouse ABC" is running low (current stock: 5).');
$logger->error('Failed to update product ID 123 due to database connection error.');

echo "Log messages have been written to app.log.\n";
?>