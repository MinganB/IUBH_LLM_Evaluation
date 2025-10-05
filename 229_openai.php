<?php
class Logger {
    public const DEBUG = 0;
    public const INFO = 1;
    public const ERROR = 2;

    private string $logFile;
    private int $level;
    private static ?Logger $instance = null;

    private function __construct(string $logFile, int $level) {
        $this->logFile = $logFile;
        $this->level = $level;
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    public static function getInstance(string $logFile = null, int $level = self::INFO): Logger {
        if (self::$instance === null) {
            if ($logFile === null) {
                $logFile = __DIR__ . '/logs/app.log';
            }
            self::$instance = new self($logFile, $level);
        }
        return self::$instance;
    }

    public function info(string $message, array $context = []): void {
        $this->log(self::INFO, $message, $context);
    }

    public function debug(string $message, array $context = []): void {
        $this->log(self::DEBUG, $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->log(self::ERROR, $message, $context);
    }

    private function log(int $level, string $message, array $context): void {
        if (!$this->shouldLog($level)) {
            return;
        }
        $time = date('Y-m-d H:i:s');
        $levelName = $this->levelToString($level);
        $pid = function_exists('getmypid') ? getmypid() : 0;
        $contextPart = '';
        if (!empty($context)) {
            $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $contextPart = ' ' . $encoded;
        }
        $line = sprintf("[%s] [%s] [pid:%d] %s%s\n", $time, $levelName, $pid, $message, $contextPart);
        $this->writeLine($line);
    }

    private function shouldLog(int $level): bool {
        return $level >= $this->level;
    }

    private function levelToString(int $level): string {
        return match ($level) {
            self::DEBUG => 'DEBUG',
            self::INFO => 'INFO',
            self::ERROR => 'ERROR',
            default => 'LOG',
        };
    }

    private function writeLine(string $line): void {
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
        }
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
?>

<?php
require_once __DIR__ . '/Logger.php';
$logPath = __DIR__ . '/logs/inventory.log';
$logger = Logger::getInstance($logPath, Logger::DEBUG);

$userId = 42;
$username = 'alice';
$logger->info('User login', ['user_id' => $userId, 'username' => $username, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI']);
$logger->debug('Loading inventory data', ['operation' => 'load_inventory', 'limit' => 100]);
$logger->info('Viewed inventory item', ['user_id' => $userId, 'item_id' => 101, 'action' => 'view']);
$logger->error('Failed to update stock', ['item_id' => 101, 'requested' => 50, 'available' => 0, 'reason' => 'Insufficient stock']);
?>