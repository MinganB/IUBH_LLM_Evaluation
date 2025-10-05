<?php

class Logger {
    private string $logFile;
    private string $logDirectory;

    public function __construct(string $logDirectory = __DIR__ . '/../logs/') {
        $this->logDirectory = rtrim($logDirectory, '/\\') . DIRECTORY_SEPARATOR;
        $this->logFile = $this->logDirectory . 'app.log';

        if (!is_dir($this->logDirectory)) {
            if (!mkdir($this->logDirectory, 0775, true)) {
                error_log('Failed to create log directory: ' . $this->logDirectory);
            }
        }
    }

    public function debug(string $message): void {
        $this->writeLog('DEBUG', $message);
    }

    public function info(string $message): void {
        $this->writeLog('INFO', $message);
    }

    public function warning(string $message): void {
        $this->writeLog('WARNING', $message);
    }

    public function error(string $message): void {
        $this->writeLog('ERROR', $message);
    }

    private function writeLog(string $level, string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] [%s]: %s" . PHP_EOL, $timestamp, $level, $message);

        if (file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            error_log('Failed to write to log file: ' . $this->logFile);
        }
    }
}

?>
<?php

require_once __DIR__ . '/classes/Logger.php';

$logger = new Logger(__DIR__ . '/logs/');

$logger->debug('User "admin_user" successfully logged in to the inventory system.');
$logger->info('New product "Wireless Mouse" (SKU: WMOUSE001) added to inventory.');
$logger->warning('Stock for "USB Keyboard" (SKU: USKEYB002) is critically low: 10 units remaining.');
$logger->error('Failed to process order #INV-2023-005 due to database connection error.');

?>