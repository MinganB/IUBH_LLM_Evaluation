<?php

class Logger
{
    private string $logFilePath;

    public function __construct()
    {
        $this->logFilePath = __DIR__ . '/app.log';
    }

    public function debug(string $message): void
    {
        $this->writeLog($message, 'DEBUG');
    }

    public function info(string $message): void
    {
        $this->writeLog($message, 'INFO');
    }

    public function warning(string $message): void
    {
        $this->writeLog($message, 'WARNING');
    }

    public function error(string $message): void
    {
        $this->writeLog($message, 'ERROR');
    }

    private function writeLog(string $message, string $level): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] [%s]: %s\n", $timestamp, $level, $message);

        file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

<?php

require_once 'Logger.php';

$logger = new Logger();

$logger->debug('User "admin" successfully logged in from IP 192.168.1.10.');
$logger->info('New product "Wireless Mouse X" (SKU: WM-X001) added to inventory by user "john.doe".');
$logger->warning('Low stock alert: Product "USB-C Adapter" (SKU: USBC-A005) has only 10 units remaining.');
$logger->error('Critical error: Database connection failed during product update for SKU "HD-500GB". Inventory synchronization aborted.');

echo "Log messages generated. Check app.log for details.\n";
?>