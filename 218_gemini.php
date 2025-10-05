<?php

class Logger
{
    private string $logFilePath;
    private string $dateFormat;
    private array $logLevels = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7,
    ];
    private int $minLogLevel;

    public function __construct(string $logFilePath, string $minLevel = 'info')
    {
        $this->logFilePath = $this->sanitizeLogPath($logFilePath);
        $this->dateFormat = 'Y-m-d H:i:s';

        if (!isset($this->logLevels[$minLevel])) {
            $minLevel = 'info';
        }
        $this->minLogLevel = $this->logLevels[$minLevel];

        $logDir = dirname($this->logFilePath);
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                throw new \RuntimeException("Failed to create log directory: {$logDir}");
            }
        }
        if (!is_writable($logDir)) {
            throw new \RuntimeException("Log directory is not writable: {$logDir}");
        }
    }

    private function sanitizeLogPath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $path = preg_replace('/' . preg_quote(DIRECTORY_SEPARATOR, '/') . '{2,}/', DIRECTORY_SEPARATOR, $path);

        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $safeParts = [];
        foreach ($parts as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                array_pop($safeParts);
            } else {
                $safeParts[] = $part;
            }
        }
        $finalPath = implode(DIRECTORY_SEPARATOR, $safeParts);

        if (substr($path, 0, strlen(DIRECTORY_SEPARATOR)) === DIRECTORY_SEPARATOR || (strlen($path) > 1 && $path[1] === ':' && ctype_alpha($path[0]))) {
            if (strpos($finalPath, DIRECTORY_SEPARATOR) !== 0 && !(strlen($finalPath) > 1 && $finalPath[1] === ':' && ctype_alpha($finalPath[0]))) {
                $finalPath = DIRECTORY_SEPARATOR . $finalPath;
            }
        }

        if (empty($finalPath)) {
            throw new \InvalidArgumentException("Invalid log file path provided: path resolved to empty string.");
        }

        return $finalPath;
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if (!isset($this->logLevels[$level])) {
            $level = 'info';
        }

        if ($this->logLevels[$level] < $this->minLogLevel) {
            return;
        }

        $logEntry = $this->formatMessage($level, $message, $context);
        $this->writeLog($logEntry);
    }

    private function formatMessage(string $level, string $message, array $context): string
    {
        $timestamp = date($this->dateFormat);
        $logLine = "[{$timestamp}] [{$level}] " . $this->interpolate($message, $context);
        return $logLine . PHP_EOL;
    }

    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_array($val) || is_object($val)) {
                $replace['{' . $key . '}'] = json_encode($val);
            } else {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }
        return strtr($message, $replace);
    }

    private function writeLog(string $logEntry): void
    {
        if (file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException("Failed to write to log file: {$this->logFilePath}");
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