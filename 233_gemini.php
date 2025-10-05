<?php

class Logger
{
    const LEVEL_DEBUG = 100;
    const LEVEL_INFO = 200;
    const LEVEL_WARNING = 300;
    const LEVEL_ERROR = 400;
    const LEVEL_CRITICAL = 500;

    protected static $logLevels = [
        self::LEVEL_DEBUG => 'DEBUG',
        self::LEVEL_INFO => 'INFO',
        self::LEVEL_WARNING => 'WARNING',
        self::LEVEL_ERROR => 'ERROR',
        self::LEVEL_CRITICAL => 'CRITICAL',
    ];

    protected $logFilePath;
    protected $minLogLevel;

    public function __construct(string $logFilePath, int $minLogLevel = self::LEVEL_INFO)
    {
        $this->logFilePath = $logFilePath;
        $this->minLogLevel = $minLogLevel;

        $logDir = dirname($logFilePath);
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0775, true) && !is_dir($logDir)) {
                error_log("Failed to create log directory: {$logDir}");
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $logDir));
            }
        }

        if (!is_writable($logDir)) {
            error_log("Log directory not writable: {$logDir}");
            throw new \RuntimeException(sprintf('Log directory "%s" is not writable', $logDir));
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
    }

    protected function log(int $level, string $message, array $context = []): void
    {
        if ($level < $this->minLogLevel) {
            return;
        }

        $levelName = self::$logLevels[$level] ?? 'UNKNOWN';
        $timestamp = date('Y-m-d H:i:s');

        $sanitizedMessage = str_replace(["\r", "\n"], '', $message);

        $logEntry = sprintf("[%s] [%s] %s", $timestamp, $levelName, $sanitizedMessage);

        if (!empty($context)) {
            $logEntry .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $logEntry .= PHP_EOL;

        if (file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            error_log(sprintf('Failed to write to log file "%s": %s', $this->logFilePath, $logEntry));
        }
    }
}
<?php

define('APP_ROOT', __DIR__);

require_once APP_ROOT . '/classes/Logger.php';

$logFilePath = APP_ROOT . '/logs/app.log';

try {
    $logger = new Logger($logFilePath, Logger::LEVEL_DEBUG);

    $logger->info('User logged in', ['user_id' => 123, 'username' => 'john.doe', 'ip_address' => '192.168.1.100']);
    $logger->debug('Database query executed', ['query' => 'SELECT * FROM products WHERE id=1', 'duration_ms' => 5]);
    $logger->warning('Inventory item running low', ['item_id' => 456, 'sku' => 'ABC-001', 'current_stock' => 5]);
    $logger->error('Failed to update product stock', ['item_id' => 789, 'sku' => 'XYZ-002', 'error' => 'Database connection lost']);
    $logger->critical('System critical error: Payment gateway inaccessible', ['gateway_id' => 'stripe', 'error_code' => 503]);
    $logger->info('New product added', ['product_id' => 101, 'name' => 'Widget Pro', 'price' => 29.99]);

    echo "Log messages generated. Check " . htmlspecialchars($logFilePath);

} catch (\RuntimeException $e) {
    echo "Error setting up logger: " . htmlspecialchars($e->getMessage());
    error_log("Error in app.php: " . $e->getMessage());
}
?>