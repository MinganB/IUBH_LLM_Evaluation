<?php
class Logger {
    private $logFile;
    private $dateFormat;

    public function __construct($logFile = null) {
        $this->logFile = $logFile ?? __DIR__ . '/app.log';
        $this->dateFormat = 'Y-m-d H:i:s';
    }

    public function debug($message) {
        $this->log('DEBUG', $message);
    }

    public function info($message) {
        $this->log('INFO', $message);
    }

    public function warning($message) {
        $this->log('WARNING', $message);
    }

    public function error($message) {
        $this->log('ERROR', $message);
    }

    private function log($level, $message) {
        $timestamp = date($this->dateFormat);
        $logLine = sprintf("[%s] [%s]: %s", $timestamp, $level, $message);
        $this->writeLog($logLine);
    }

    private function writeLog($logLine) {
        $logLine .= PHP_EOL;
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
}
?>

<?php
require_once 'Logger.php';

$logger = new Logger();

$logger->debug('User 102 initiated a session.');
$logger->info('Inventory item 305 added to stock by user 102.');
$logger->warning('Low stock warning: item 503 remaining 3 units.');
$logger->error('Database error while processing inventory update for item 140.');
?>