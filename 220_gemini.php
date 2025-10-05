<?php

class Logger
{
    private string $logFilePath;
    private array $logLevels = [
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7,
    ];
    private int $currentMinLogLevelWeight;

    public function __construct(string $logDirectory, string $fileNamePrefix = 'app', string $minLogLevel = 'info')
    {
        if (!is_dir($logDirectory)) {
            if (!mkdir($logDirectory, 0777, true)) {
                throw new \RuntimeException("Log directory '{$logDirectory}' does not exist and could not be created.");
            }
        }
        if (!is_writable($logDirectory)) {
            throw new \RuntimeException("Log directory '{$logDirectory}' is not writable.");
        }

        $this->logFilePath = $logDirectory . DIRECTORY_SEPARATOR . $fileNamePrefix . '_' . date('Y-m-d') . '.log';
        $this->setMinLogLevel($minLogLevel);
    }

    public function setMinLogLevel(string $level): void
    {
        $level = strtolower($level);
        if (!isset($this->logLevels[$level])) {
            error_log("Invalid log level '{$level}' provided. Defaulting to 'info'.", E_USER_WARNING);
            $this->currentMinLogLevelWeight = $this->logLevels['info'];
            return;
        }
        $this->currentMinLogLevelWeight = $this->logLevels[$level];
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);

        if (!isset($this->logLevels[$level])) {
            error_log("Attempted to log with an invalid level: {$level}. Message: {$message}", E_USER_WARNING);
            $level = 'info';
        }

        if ($this->logLevels[$level] > $this->currentMinLogLevelWeight) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[{$timestamp}] [{$level}] {$message}";

        if (!empty($context)) {
            $jsonContext = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($jsonContext === false) {
                error_log("Failed to encode log context: " . json_last_error_msg(), E_USER_WARNING);
                $logLine .= ' [Context Encoding Error]';
            } else {
                $logLine .= ' ' . $jsonContext;
            }
        }
        $logLine .= PHP_EOL;

        try {
            file_put_contents($this->logFilePath, $logLine, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            error_log("Logger failed to write to file '{$this->logFilePath}': {$e->getMessage()} - Original Log: {$logLine}");
        }
    }
}
?>