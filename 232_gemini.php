<?php

// classes/Logger.php

class Logger
{
    const DEBUG = 100;
    const INFO = 200;
    const WARNING = 300;
    const ERROR = 400;
    const CRITICAL = 500;

    protected string $logFile;
    protected int $minLogLevel;

    protected static array $logLevelMap = [
        self::DEBUG => 'DEBUG',
        self::INFO => 'INFO',
        self::WARNING => 'WARNING',
        self::ERROR => 'ERROR',
        self::CRITICAL => 'CRITICAL',
    ];

    public function __construct(string $logFile, int $minLogLevel = self::DEBUG)
    {
        $this->logFile = $logFile;
        $this->minLogLevel = $minLogLevel;
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
    }

    protected function log(int $level, string $message): void
    {
        if ($level < $this->minLogLevel) {
            return;
        }

        $levelName = self::$logLevelMap[$level] ?? 'UNKNOWN';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] [%s] %s\n", $timestamp, $levelName, $message);

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function debug(string $message): void
    {
        $this->log(self::DEBUG, $message);
    }

    public function info(string $message): void
    {
        $this->log(self::INFO, $message);
    }

    public function warning(string $message): void
    {
        $this->log(self::WARNING, $message);
    }

    public function error(string $message): void
    {
        $this->log(self::ERROR, $message);
    }

    public function critical(string $message): void
    {
        $this->log(self::CRITICAL, $message);
    }
}

?>
<?php

// app.php

require_once __DIR__ . '/classes/Logger.php';

$logFilePath = __DIR__ . '/logs/application.log';

$logger = new Logger($logFilePath, Logger::DEBUG);

$logger->debug('User "john.doe" accessed product list page.');
$logger->info('New inventory item "SKU-001" added by "admin".');
$logger->warning('Low stock alert for item "SKU-005". Current stock: 10 units.');
$logger->error('Failed to update order "ORD-123": Database connection lost.');
$logger->critical('System shutdown initiated due to critical error: Unhandled exception in main process.');

echo "Log messages have been written to '{$logFilePath}'.\n";

$infoOnlyLogger = new Logger($logFilePath, Logger::INFO);
$infoOnlyLogger->debug('This debug message should NOT appear in the log if the logger is configured for INFO level or higher.');
$infoOnlyLogger->info('This info message WILL appear in the log even with INFO level or higher.');
echo "Additional log messages written with a different minimum log level.\n";

$userId = 123;
$productId = 456;
$quantity = 5;
$logger->info("User ID {$userId} updated product ID {$productId} quantity to {$quantity}.");

try {
    throw new Exception("Simulated fatal error during data processing.");
} catch (Exception $e) {
    $logger->error("Caught exception: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
}

?>