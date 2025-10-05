<?php

class Logger
{
    private string $logFilePath;
    private array $logLevels = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 250,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
        'alert'     => 550,
        'emergency' => 600,
    ];

    public function __construct(string $logFilePath)
    {
        $this->logFilePath = $logFilePath;
        $this->ensureLogFileExists();
    }

    private function ensureLogFileExists(): void
    {
        $dir = dirname($this->logFilePath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }
        }
        if (!file_exists($this->logFilePath)) {
            if (file_put_contents($this->logFilePath, '') === false) {
                throw new \RuntimeException("Could not create log file: {$this->logFilePath}");
            }
            if (!chmod($this->logFilePath, 0664)) {
                throw new \RuntimeException("Could not set permissions for log file: {$this->logFilePath}");
            }
        }
        if (!is_writable($this->logFilePath)) {
            throw new \RuntimeException("Log file is not writable: {$this->logFilePath}");
        }
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);
        if (!isset($this->logLevels[$level])) {
            throw new \InvalidArgumentException("Invalid log level '{$level}' provided.");
        }

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [". strtoupper($level) ."] {$message}";

        if (!empty($context)) {
            $logEntry .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $logEntry .= PHP_EOL;

        if (file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            // In a production environment, logging failures should ideally be reported
            // via a secondary channel (e.g., stderr, a different emergency log, or a monitoring system).
            // For this module, we assume the initial writability check is sufficient for most cases.
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }
}
?>