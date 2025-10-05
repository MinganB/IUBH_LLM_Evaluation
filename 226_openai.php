<?php
class Logger {
    private string $logDir;
    private string $logFile;
    private int $maxBytes;
    private int $maxFiles;

    public function __construct(string $logDir = '', string $logFileName = 'app.log', int $maxBytes = 10485760, int $maxFiles = 5) {
        $dir = $logDir ?: __DIR__ . '/logs';
        $this->logDir = rtrim($dir, '/\\');
        if (!is_dir($this->logDir) && !mkdir($this->logDir, 0777, true) && !is_dir($this->logDir)) {
            throw new \RuntimeException('Unable to create log directory: ' . $this->logDir);
        }
        $this->logFile = rtrim($this->logDir, '/\\') . '/' . ltrim($logFileName, '/');
        $this->maxBytes = $maxBytes > 0 ? $maxBytes : 0;
        $this->maxFiles = $maxFiles > 0 ? $maxFiles : 1;
    }

    public function log(string $level, string $message): void
    {
        $level = strtoupper($level);
        $logLine = "[" . date('Y-m-d H:i:s') . "] [" . $level . "] " . $message . PHP_EOL;
        if ($this->maxBytes > 0 && file_exists($this->logFile) && filesize($this->logFile) >= $this->maxBytes) {
            $this->rotate();
        }
        $result = @file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        if ($result === false) {
            throw new \RuntimeException('Unable to write to log file: ' . $this->logFile);
        }
    }

    public function debug(string $message): void
    {
        $this->log('DEBUG', $message);
    }

    public function info(string $message): void
    {
        $this->log('INFO', $message);
    }

    public function error(string $message): void
    {
        $this->log('ERROR', $message);
    }

    private function rotate(): void
    {
        $base = $this->logFile;
        if (!file_exists($base)) {
            return;
        }
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $old = $base . '.' . $i;
            $new = $base . '.' . ($i + 1);
            if (file_exists($old)) {
                rename($old, $new);
            }
        }
        $first = $base . '.1';
        if (file_exists($first)) {
            unlink($first);
        }
        rename($base, $first);
    }
}
?>

<?php
require_once __DIR__ . '/Logger.php';
$logDir = __DIR__ . '/logs';
$logger = new Logger($logDir, 'app.log', 4 * 1024 * 1024, 5);
$logger->debug('Debug message');
$logger->info('Info message');
$logger->error('Error message');
?>