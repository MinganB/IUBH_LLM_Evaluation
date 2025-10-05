<?php
class Logger {
    private $logFile;
    private $dateFormat;

    public function __construct($logFile = 'app.log', $dateFormat = 'Y-m-d H:i:s') {
        $this->logFile = $logFile;
        $this->dateFormat = $dateFormat;
    }

    private function write($level, $message) {
        $now = new DateTime();
        $timestamp = $now->format($this->dateFormat);
        $logLine = '[' . $timestamp . '] [' . strtoupper($level) . ']: ' . $message;
        file_put_contents($this->logFile, $logLine . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public function debug($message) {
        $this->write('debug', $message);
    }

    public function info($message) {
        $this->write('info', $message);
    }

    public function warning($message) {
        $this->write('warning', $message);
    }

    public function error($message) {
        $this->write('error', $message);
    }
}
?>

<?php
require_once 'Logger.php';
$logger = new Logger();
$logger->debug('This is a debug message.');
$logger->info('This is an info message.');
$logger->warning('This is a warning message.');
$logger->error('This is an error message.');
?>