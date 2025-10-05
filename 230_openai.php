<?php
class Logger {
    const DEBUG = 0;
    const INFO = 1;
    const ERROR = 2;

    protected $logFile;
    protected $level;
    protected $maxFileSize;
    protected $dateFormat;

    public function __construct($logFile = null, $level = self::DEBUG, $maxFileSize = 10485760) {
        if ($logFile === null) {
            $dir = __DIR__ . '/logs';
        } else {
            $dir = dirname($logFile);
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if ($logFile === null) {
            $logFile = $dir . '/app.log';
        }
        $this->logFile = $logFile;
        $this->level = $level;
        $this->maxFileSize = $maxFileSize;
        $this->dateFormat = 'Y-m-d H:i:s';
        if (!is_file($this->logFile)) {
            file_put_contents($this->logFile, '');
        }
    }

    public function debug($message, $context = []) {
        if ($this->level > self::DEBUG) {
            return;
        }
        $this->log(self::DEBUG, $message, $context);
    }

    public function info($message, $context = []) {
        if ($this->level > self::INFO) {
            return;
        }
        $this->log(self::INFO, $message, $context);
    }

    public function error($message, $context = []) {
        if ($this->level > self::ERROR) {
            return;
        }
        $this->log(self::ERROR, $message, $context);
    }

    protected function log($level, $message, $context) {
        $timestamp = date($this->dateFormat);
        $pid = function_exists('getmypid') ? getmypid() : 0;
        $levelLabel = $this->levelToLabel($level);
        $contextPart = '';
        if (!empty($context)) {
            $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                $sanitized = preg_replace('/[\r\n]+/', ' ', $json);
                $contextPart = ' ' . $sanitized;
            }
        }
        $line = sprintf('[%s] [%s] [pid:%d]%s %s%s', $timestamp, $levelLabel, $pid, '', $message, $contextPart) . PHP_EOL;
        if (is_file($this->logFile) && (filesize($this->logFile) >= $this->maxFileSize)) {
            $this->rotateLogFile();
        }
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    protected function levelToLabel($level) {
        switch ($level) {
            case self::DEBUG: return 'DEBUG';
            case self::INFO: return 'INFO';
            case self::ERROR: return 'ERROR';
            default: return 'LOG';
        }
    }

    protected function rotateLogFile() {
        $dir = dirname($this->logFile);
        $base = basename($this->logFile);
        $ts = date('YmdHis');
        $rotated = $dir . '/' . $ts . '_' . $base;
        if (rename($this->logFile, $rotated)) {
            file_put_contents($this->logFile, '');
        } else {
            file_put_contents($this->logFile, '');
        }
    }
}
?> 

<?php
require_once 'Logger.php';
$logger = new Logger(null, Logger::DEBUG, 10485760);

$logger->info('User login', ['user_id' => 42, 'username' => 'jdoe', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);

$logger->debug('Inventory fetch started', ['section' => 'inventory', 'operation' => 'fetch']);

$requested = 5;
$available = 2;
$sku = 'ABC123';
if ($available < $requested) {
    $logger->error('Insufficient stock', ['sku' => $sku, 'available' => $available, 'requested' => $requested]);
} else {
    $logger->info('Stock sufficient', ['sku' => $sku, 'available' => $available]);
}
$logger->debug('Inventory update simulated', ['sku' => $sku, 'change' => -$requested]);
?>