<?php
class Logger {
    private $logFile;

    public function __construct($logFile = null) {
        $this->logFile = $logFile ?? (dirname(__DIR__) . '/app.log');
        $this->ensureLogFile();
    }

    public function debug($message) { $this->write('DEBUG', $message); }
    public function info($message) { $this->write('INFO', $message); }
    public function warning($message) { $this->write('WARNING', $message); }
    public function error($message) { $this->write('ERROR', $message); }

    private function write($level, $message) {
        if (!is_string($message)) {
            if (is_scalar($message)) {
                $message = (string) $message;
            } else {
                $message = is_object($message) ? json_encode($message) : print_r($message, true);
            }
        }
        $this->writeToLog($level, $message);
    }

    private function writeToLog($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[$timestamp] [$level]: $message" . PHP_EOL;
        $this->ensureLogFile();
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        @chmod($this->logFile, 0644);
    }

    private function ensureLogFile() {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!file_exists($this->logFile)) {
            @touch($this->logFile);
            @chmod($this->logFile, 0644);
        }
    }
}
?>

<?php
require_once __DIR__ . '/../classes/Logger.php';

$logger = new Logger();
$logger->debug('Inventory module loaded.');
$logger->info('User authenticated.');
$logger->warning('Stock level approaching reorder threshold.');
$logger->error('Inventory DB connection error encountered.');
?>