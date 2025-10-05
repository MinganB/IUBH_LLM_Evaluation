<?php

class Logger
{
    private const LOG_FILE = 'app.log';

    public function debug(string $message): void
    {
        $this->writeLog('DEBUG', $message);
    }

    public function info(string $message): void
    {
        $this->writeLog('INFO', $message);
    }

    public function warning(string $message): void
    {
        $this->writeLog('WARNING', $message);
    }

    public function error(string $message): void
    {
        $this->writeLog('ERROR', $message);
    }

    private function writeLog(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] [%s]: %s\n", $timestamp, $level, $message);
        file_put_contents(self::LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

<?php

require_once 'Logger.php';

$logger = new Logger();

$logger->debug('User "john.doe" accessed product list page.');
$logger->info('Product "SKU001" (Laptop) quantity updated from 10 to 8 by admin.');
$logger->warning('Attempted stock adjustment for non-existent product "SKU999".');
$logger->error('Database connection failed during order processing. Order ID: 12345.');

echo "Log messages have been written to app.log.\n";
?>