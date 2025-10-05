<?php
class Logger {
    private string $logFile;

    public function __construct(string $logFile = null) {
        $this->logFile = $logFile ?? __DIR__ . '/app.log';
        if (!file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }
        if (!is_writable($this->logFile)) {
            @chmod($this->logFile, 0644);
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
        $timestamp = (new DateTime())->format('Y-m-d H:i:s');
        $formatted = sprintf("[%s] [%s]: %s", $timestamp, $level, $message);
        $fp = fopen($this->logFile, 'a');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $formatted . PHP_EOL);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }
}
?> 

<?php
require_once __DIR__ . '/Logger.php';

$logger = new Logger();

$logger->debug('Inventory module startup: user session initiated.');
$logger->info('User login: user_id=101, username=jdoe.');
$logger->warning('Stock low: item_id=2048, remaining=4 units.');
$logger->error('Database error: failed to update inventory item_id=2048.');
?>