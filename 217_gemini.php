<?php

class Logger
{
    private string $logFilePath;
    private int $minLogLevelNumeric;

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

    public function __construct(string $logFilePath, string $minLogLevel = 'info')
    {
        $this->logFilePath = $logFilePath;
        $this->setMinLogLevel($minLogLevel);
        $this->ensureLogDirectoryExists();
    }

    private function setMinLogLevel(string $level): void
    {
        $level = strtolower($level);
        if (!isset($this->logLevels[$level])) {
            throw new InvalidArgumentException("Invalid minimum log level: {$level}");
        }
        $this->minLogLevelNumeric = $this->logLevels[$level];
    }

    private function ensureLogDirectoryExists(): void
    {
        $logDirectory = dirname($this->logFilePath);
        if (!is_dir($logDirectory)) {
            if (!mkdir($logDirectory, 0775, true) && !is_dir($logDirectory)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $logDirectory));
            }
        }
    }

    public function log(string $level, string $message): bool
    {
        $level = strtolower($level);

        if (!isset($this->logLevels[$level])) {
            return false;
        }

        if ($this->logLevels[$level] < $this->minLogLevelNumeric) {
            return false;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] [%s] %s\n", $timestamp, strtoupper($level), $message);

        $result = @file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX);

        return $result !== false;
    }
}
?>