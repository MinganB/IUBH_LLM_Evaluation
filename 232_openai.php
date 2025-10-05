<?php
declare(strict_types=1);

class Logger {
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_ERROR = 'ERROR';

    private static ?Logger $instance = null;
    private string $logDir;
    private string $logFilePrefix = 'inventory';

    private function __construct(string $logDir = null) {
        $this->logDir = $logDir ?? dirname(__DIR__) . '/logs';
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }
    }

    public static function getInstance(string $logDir = null): Logger {
        if (self::$instance === null) {
            self::$instance = new self($logDir);
        }
        return self::$instance;
    }

    private function getLogFilePath(): string {
        $date = date('Y-m-d');
        $filename = $this->logDir . DIRECTORY_SEPARATOR . $this->logFilePrefix . '_' . $date . '.log';
        if (!file_exists($filename)) {
            @touch($filename);
        }
        return $filename;
    }

    private function formatMessage(string $level, string $message, array $context): string {
        $time = date('Y-m-d H:i:s');
        $contextPart = '';
        if (!empty($context)) {
            $contextPart = ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        return "[$time] {$level}: {$message}{$contextPart}\n";
    }

    private function writeLog(string $level, string $message, array $context = []): void {
        $logFile = $this->getLogFilePath();
        $logLine = $this->formatMessage($level, $message, $context);
        if (!is_writable($logFile)) {
            @chmod($logFile, 0666);
        }
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    public function log(string $level, string $message, array $context = []): void {
        $this->writeLog($level, $message, $context);
    }

    public function debug(string $message, array $context = []): void {
        $this->writeLog(self::LEVEL_DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void {
        $this->writeLog(self::LEVEL_INFO, $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->writeLog(self::LEVEL_ERROR, $message, $context);
    }
}
?><?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Logger.php';

$logger = Logger::getInstance();

$logger->info('User login', [
    'user_id' => 101,
    'username' => 'jdoe',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
]);

$logger->debug('Inventory list retrieved', [
    'warehouse' => 'WH-01',
    'count' => 128
]);

$logger->error('Stock update failed', [
    'item_id' => 204,
    'requested' => 15,
    'reason' => 'Insufficient stock'
]);

$logger->info('New item created', [
    'item_id' => 503,
    'name' => 'UltraWidget',
    'sku' => 'UW-503'
]);
?>