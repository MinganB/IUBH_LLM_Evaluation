<?php
class Logger {
    private string $logFile;
    private int $level;
    private int $maxFileSize;
    private int $backupCount;
    private string $dateFormat;
    private bool $enabled;

    const LEVEL_DEBUG = 10;
    const LEVEL_INFO = 20;
    const LEVEL_WARNING = 30;
    const LEVEL_ERROR = 40;

    private array $levelNames = [
        self::LEVEL_DEBUG => 'DEBUG',
        self::LEVEL_INFO => 'INFO',
        self::LEVEL_WARNING => 'WARNING',
        self::LEVEL_ERROR => 'ERROR',
    ];

    public function __construct(string $logFile, int $level = self::LEVEL_INFO, int $maxFileSize = 10 * 1024 * 1024, int $backupCount = 5, string $dateFormat = 'Y-m-d H:i:s') {
        $this->logFile = $logFile;
        $this->level = $level;
        $this->maxFileSize = max(1024, $maxFileSize);
        $this->backupCount = max(1, $backupCount);
        $this->dateFormat = $dateFormat;
        $this->enabled = true;
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    public function log(int $level, string $message, array $context = []): void {
        if (!$this->enabled) return;
        if ($level < $this->level) return;
        $levelName = $this->levelNames[$level] ?? 'LEVEL' . $level;
        $formattedMessage = $this->formatMessage($levelName, $message, $context);
        $this->rotateIfNeeded();
        $this->writeLogLine($formattedMessage);
    }

    public function debug(string $message, array $context = []): void {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): void {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    private function formatMessage(string $levelName, string $message, array $context): string {
        $timestamp = (new DateTime())->format($this->dateFormat);
        $contextPart = '';
        if (!empty($context)) {
            $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                $contextPart = ' ' . $json;
            } else {
                $contextPart = ' ' . print_r($context, true);
            }
        }
        $sanitizedMessage = rtrim($message);
        return sprintf("[%s] %s: %s%s", $timestamp, $levelName, $sanitizedMessage, $contextPart) . PHP_EOL;
    }

    private function writeLogLine(string $line): void {
        if (!$this->enabled) return;
        $fh = @fopen($this->logFile, 'a');
        if (!$fh) return;
        if (flock($fh, LOCK_EX)) {
            fwrite($fh, $line);
            fflush($fh);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
    }

    private function rotateIfNeeded(): void {
        if (!is_file($this->logFile)) return;
        clearstatcache();
        $size = filesize($this->logFile);
        if ($size <= $this->maxFileSize) return;
        for ($i = $this->backupCount; $i >= 1; $i--) {
            $old = $this->logFile . '.' . $i;
            $new = $this->logFile . '.' . ($i + 1);
            if (is_file($old)) {
                rename($old, $new);
            }
        }
        rename($this->logFile, $this->logFile . '.1');
        for ($i = $this->backupCount + 1; $i <= $this->backupCount + 2; $i++) {
            $f = $this->logFile . '.' . $i;
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }

    public function setLevel(int $level): void {
        $this->level = $level;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function enable(): void {
        $this->enabled = true;
    }

    public function disable(): void {
        $this->enabled = false;
    }

    public function getLogFile(): string {
        return $this->logFile;
    }
}
?> 

<?php
require_once __DIR__ . '/Logger.php';

$logPath = __DIR__ . '/logs/app.log';
$logger = new Logger($logPath, Logger::LEVEL_DEBUG, 5 * 1024 * 1024, 5, 'Y-m-d H:i:s');

$logger->debug('Debugging application started', ['env' => $_SERVER['ENV'] ?? 'production']);
$logger->info('Application initialized', ['user' => $_SERVER['REMOTE_USER'] ?? 'guest']);
$logger->warning('Cache miss', ['cacheKey' => 'home_page']);
$logger->error('Unhandled exception occurred', ['exception' => 'RuntimeException', 'code' => 500]);
$logger->info('User action', ['userId' => 123, 'action' => 'login']);
?>