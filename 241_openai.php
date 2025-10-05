<?php
class Logger {
    private $logFile;

    public function __construct($logFile = null) {
        if ($logFile !== null) {
            $this->logFile = $logFile;
        } else {
            $this->logFile = $this->defaultLogPath();
        }
    }

    public function debug($message) {
        $this->writeLog('DEBUG', $message);
    }

    public function info($message) {
        $this->writeLog('INFO', $message);
    }

    public function warning($message) {
        $this->writeLog('WARNING', $message);
    }

    public function error($message) {
        $this->writeLog('ERROR', $message);
    }

    private function defaultLogPath() {
        $classesDir = dirname(__FILE__);
        $rootDir = dirname($classesDir);
        $logPath = $rootDir . DIRECTORY_SEPARATOR . 'app.log';
        return $logPath;
    }

    private function writeLog($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[" . $timestamp . "] [" . $level . "]: " . $message . PHP_EOL;

        $logFile = $this->logFile;
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
}
?>

<?php
require_once __DIR__ . '/classes/Logger.php';

$logger = new Logger();

$logger->debug('Inventory module initialized');
$logger->info('User login: user_id=42');
$logger->warning('Low stock warning for item_id=987');
$logger->error('Database connection failed while syncing inventory.');
?>