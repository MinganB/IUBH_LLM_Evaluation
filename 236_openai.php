<?php
class Logger {
    private $logFile;
    private $dateFormat = 'Y-m-d H:i:s';
    public function __construct($logFilePath = null) {
        $path = $logFilePath ?? (__DIR__ . DIRECTORY_SEPARATOR . 'app.log');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->logFile = $path;
        if (!is_file($this->logFile)) {
            touch($this->logFile);
            @chmod($this->logFile, 0664);
        }
        if (!is_writable($this->logFile)) {
            @chmod($this->logFile, 0664);
        }
        if (!is_writable($this->logFile)) {
            $this->logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'app.log';
            if (!is_file($this->logFile)) {
                touch($this->logFile);
            }
        }
    }
    public function debug($message) { $this->log('DEBUG', $message); }
    public function info($message) { $this->log('INFO', $message); }
    public function warning($message) { $this->log('WARNING', $message); }
    public function error($message) { $this->log('ERROR', $message); }

    private function log($level, $message) {
        $timestamp = (new DateTime())->format($this->dateFormat);
        $line = "[$timestamp] [$level]: $message" . PHP_EOL;
        $fp = fopen($this->logFile, 'a');
        if ($fp !== false) {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $line);
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }
}
?>

<?php
require_once 'Logger.php';
$logger = new Logger();
$logger->debug('Debug message');
$logger->info('Info message');
$logger->warning('Warning message');
$logger->error('Error message');
?>