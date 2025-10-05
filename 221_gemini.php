<?php

class InventoryLogger
{
    private string $logDirectory;
    private string $logFileNamePrefix;
    private array $logLevels;
    private int $minimumLogLevel;

    public const LEVEL_DEBUG = 1;
    public const LEVEL_INFO = 2;
    public const LEVEL_NOTICE = 3;
    public const LEVEL_WARNING = 4;
    public const LEVEL_ERROR = 5;
    public const LEVEL_CRITICAL = 6;
    public const LEVEL_ALERT = 7;
    public const LEVEL_EMERGENCY = 8;

    public function __construct(string $logDirectory = '/var/log/inventory_system', string $logFileNamePrefix = 'inventory_log_', int $minimumLogLevel = self::LEVEL_DEBUG)
    {
        $this->logDirectory = rtrim($logDirectory, '/\\');
        $this->logFileNamePrefix = $logFileNamePrefix;
        $this->minimumLogLevel = $minimumLogLevel;

        $this->logLevels = [
            'DEBUG' => self::LEVEL_DEBUG,
            'INFO' => self::LEVEL_INFO,
            'NOTICE' => self::LEVEL_NOTICE,
            'WARNING' => self::LEVEL_WARNING,
            'ERROR' => self::LEVEL_ERROR,
            'CRITICAL' => self::LEVEL_CRITICAL,
            'ALERT' => self::LEVEL_ALERT,
            'EMERGENCY' => self::LEVEL_EMERGENCY,
        ];

        $this->ensureLogDirectoryExists();
    }

    private function ensureLogDirectoryExists(): void
    {
        if (!is_dir($this->logDirectory)) {
            if (!mkdir($this->logDirectory, 0755, true) && !is_dir($this->logDirectory)) {
                throw new \RuntimeException(sprintf('Log directory "%s" could not be created.', $this->logDirectory));
            }
        }

        if (!is_writable($this->logDirectory)) {
            throw new \RuntimeException(sprintf('Log directory "%s" is not writable.', $this->logDirectory));
        }
    }

    public function log(string $level, string $message, array $context = []): bool
    {
        $level = strtoupper($level);

        if (!isset($this->logLevels[$level])) {
            $level = 'INFO';
        }

        $logLevelValue = $this->logLevels[$level];

        if ($logLevelValue < $this->minimumLogLevel) {
            return true;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = sprintf("[%s] [%s] %s", $timestamp, $level, $message);

        if (!empty($context)) {
            $logMessage .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $logMessage .= PHP_EOL;

        $logFilePath = $this->getLogFilePath();

        $result = @file_put_contents($logFilePath, $logMessage, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            error_log(sprintf('Failed to write log message to "%s". Message: "%s"', $logFilePath, $logMessage));
            return false;
        }

        clearstatcache(true, $logFilePath);
        if (filesize($logFilePath) === (int)$result) {
            chmod($logFilePath, 0644);
        }

        return true;
    }

    private function getLogFilePath(): string
    {
        $date = date('Y-m-d');
        $safeFileNamePrefix = basename($this->logFileNamePrefix);
        return $this->logDirectory . DIRECTORY_SEPARATOR . $safeFileNamePrefix . $date . '.log';
    }

    public function debug(string $message, array $context = []): bool
    {
        return $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): bool
    {
        return $this->log('info', $message, $context);
    }

    public function notice(string $message, array $context = []): bool
    {
        return $this->log('notice', $message, $context);
    }

    public function warning(string $message, array $context = []): bool
    {
        return $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): bool
    {
        return $this->log('error', $message, $context);
    }

    public function critical(string $message, array $context = []): bool
    {
        return $this->log('critical', $message, $context);
    }

    public function alert(string $message, array $context = []): bool
    {
        return $this->log('alert', $message, $context);
    }

    public function emergency(string $message, array $context = []): bool
    {
        return $this->log('emergency', $message, $context);
    }
}
?>