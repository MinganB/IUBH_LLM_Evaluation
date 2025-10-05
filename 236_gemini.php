<?php

class Logger {
    private string $logFilePath;

    public function __construct(string $logFilePath = 'app.log') {
        $this->logFilePath = $logFilePath;
    }

    public function debug(string $message): void {
        $this->writeLog('DEBUG', $message);
    }

    public function info(string $message): void {
        $this->writeLog('INFO', $message);
    }

    public function warning(string $message): void {
        $this->writeLog('WARNING', $message);
    }

    public function error(string $message): void {
        $this->writeLog('ERROR', $message);
    }

    private function writeLog(string $level, string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] [%s]: %s\n", $timestamp, $level, $message);

        if (file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            error_log("Failed to write to log file: " . $this->logFilePath);
            throw new \RuntimeException("Failed to write to log file.");
        }
    }
}

<?php

require_once 'Logger.php';

$logger = new Logger();

try {
    $logger->debug('This is a detailed debug message for developers.');
    $logger->info('User logged in successfully from IP: 192.168.1.100');
    $logger->warning('Memory usage is at 80%. Consider optimizing.');
    $logger->error('Database connection failed: Could not connect to MySQL server.');

    echo "Log messages written to app.log\n";
} catch (\RuntimeException $e) {
    echo "An error occurred while writing to the log: " . $e->getMessage() . "\n";
}
?>