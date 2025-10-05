<?php

class Logger
{
    public const EMERGENCY = 'emergency';
    public const ALERT     = 'alert';
    public const CRITICAL  = 'critical';
    public const ERROR     = 'error';
    public const WARNING   = 'warning';
    public const NOTICE    = 'notice';
    public const INFO      = 'info';
    public const DEBUG     = 'debug';

    protected string $logFilePath;
    protected array $logLevels;

    public function __construct(string $logDirectory, string $logFileName = 'app.log')
    {
        $this->logLevels = [
            self::EMERGENCY,
            self::ALERT,
            self::CRITICAL,
            self::ERROR,
            self::WARNING,
            self::NOTICE,
            self::INFO,
            self::DEBUG,
        ];

        $logFileName = basename($logFileName);
        if (empty($logFileName)) {
            $logFileName = 'app.log';
        }

        if (!is_dir($logDirectory)) {
            if (!mkdir($logDirectory, 0755, true)) {
                error_log("Logger: Failed to create log directory: {$logDirectory}");
                throw new \RuntimeException("Failed to create log directory: {$logDirectory}");
            }
        } elseif (!is_writable($logDirectory)) {
            error_log("Logger: Log directory is not writable: {$logDirectory}");
            throw new \RuntimeException("Log directory is not writable: {$logDirectory}");
        }

        $this->logFilePath = rtrim($logDirectory, '/') . '/' . $logFileName;
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if (!in_array($level, $this->logLevels)) {
            error_log("Logger: Invalid log level '{$level}' provided. Defaulting to INFO.");
            $level = self::INFO;
        }

        $entry = $this->formatLogEntry($level, $message, $context);

        if (false === @file_put_contents($this->logFilePath, $entry . PHP_EOL, FILE_APPEND | LOCK_EX)) {
            error_log("Logger: Failed to write to log file: {$this->logFilePath}. Message: {$entry}");
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    protected function formatLogEntry(string $level, string $message, array $context = []): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $level = strtoupper($level);

        $contextString = '';
        if (!empty($context)) {
            try {
                $contextString = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $contextString = ' (JSON_ERROR: ' . json_last_error_msg() . ')';
                }
            } catch (\Throwable $e) {
                $contextString = ' (Context serialization failed: ' . $e->getMessage() . ')';
            }
        }

        return sprintf('[%s] [%s] %s %s', $timestamp, $level, $message, $contextString);
    }
}
?>