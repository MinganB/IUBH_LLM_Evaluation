<?php
class Logger {
    private static $instance = null;
    private $logDir;
    private $logFile;
    private $level;
    private $levels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'NOTICE' => 2,
        'WARNING' => 3,
        'ERROR' => 4,
        'CRITICAL' => 5,
        'ALERT' => 6,
        'EMERGENCY' => 7
    ];

    private function __construct($logDir = null, $logFile = null, $level = 'DEBUG') {
        $root = dirname(__DIR__);
        $this->logDir = $logDir ?? ($root . DIRECTORY_SEPARATOR . 'logs');
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0770, true);
        }
        $this->logFile = $logFile ?? ($this->logDir . DIRECTORY_SEPARATOR . 'inventory.log');
        $this->level = strtoupper($level);
    }

    public static function getInstance($logDir = null, $logFile = null, $level = 'DEBUG') {
        if (self::$instance === null) {
            self::$instance = new self($logDir, $logFile, $level);
        }
        return self::$instance;
    }

    public function log($level, $message, $context = []) {
        $level = strtoupper($level);
        if (!isset($this->levels[$level])) {
            $level = 'INFO';
        }
        if ($this->levels[$level] < $this->levels[$this->level]) {
            return;
        }
        $entry = $this->formatMessage($level, $message, $context);
        $this->write($entry);
    }

    public function debug($message, $context = []) { $this->log('DEBUG', $message, $context); }
    public function info($message, $context = []) { $this->log('INFO', $message, $context); }
    public function notice($message, $context = []) { $this->log('NOTICE', $message, $context); }
    public function warning($message, $context = []) { $this->log('WARNING', $message, $context); }
    public function error($message, $context = []) { $this->log('ERROR', $message, $context); }
    public function critical($message, $context = []) { $this->log('CRITICAL', $message, $context); }
    public function alert($message, $context = []) { $this->log('ALERT', $message, $context); }
    public function emergency($message, $context = []) { $this->log('EMERGENCY', $message, $context); }

    private function formatMessage($level, $message, $context) {
        $time = date('Y-m-d H:i:s');
        $pid = function_exists('getmypid') ? getmypid() : 0;
        $contextPart = '';
        if (!empty($context)) {
            $contextPart = ' | context: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $logLevelPadded = str_pad($level, 8, ' ', STR_PAD_RIGHT);
        $message = trim($message);
        return "[$time] [$logLevelPadded] [PID {$pid}] {$message}{$contextPart}" . PHP_EOL;
    }

    private function write($entry) {
        if (!is_file($this->logFile)) {
            $dir = dirname($this->logFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0770, true);
            }
        }
        file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    public function setLevel($level) {
        $level = strtoupper($level);
        if (isset($this->levels[$level])) {
            $this->level = $level;
        }
    }

    public function getLevel() { return $this->level; }
}
?>

<?php
require_once __DIR__ . '/../classes/Logger.php';
$logger = Logger::getInstance();

$logger->info('User login', ['user' => 'alice', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
$logger->debug('Loading inventory items', ['source' => 'inventory_service', 'action' => 'load', 'limit' => 100]);
$logger->info('Item updated', ['item_id' => 101, 'changes' => ['stock' => 42, 'location' => 'A1']]);
$logger->notice('Stock below threshold', ['item_id' => 101, 'stock' => 5, 'threshold' => 10]);
$logger->warning('High memory usage in inventory worker', ['memory_mb' => 512]);
$logger->error('Database connection failed', ['host' => 'db.internal', 'retry' => true]);
$logger->critical('Inventory item not found', ['item_id' => 9999]);
$logger->alert('Suspicious activity detected: multiple failed logins', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 'attempts' => 7]);
?>