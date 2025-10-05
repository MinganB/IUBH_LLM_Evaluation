<?php

class Logger
{
    private $logFile;
    private $logLevels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    ];
    private $minLogLevel;
    private $maxFileSize;
    private $maxFiles;

    public function __construct($logFile = 'application.log', $minLogLevel = 'info', $maxFileSize = 10485760, $maxFiles = 5)
    {
        $this->logFile = $logFile;
        $this->minLogLevel = $minLogLevel;
        $this->maxFileSize = $maxFileSize;
        $this->maxFiles = $maxFiles;
        $this->ensureLogDirectory();
    }

    private function ensureLogDirectory()
    {
        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    public function log($level, $message, $context = [])
    {
        if (!isset($this->logLevels[$level])) {
            throw new InvalidArgumentException("Invalid log level: $level");
        }

        if ($this->logLevels[$level] < $this->logLevels[$this->minLogLevel]) {
            return;
        }

        $this->rotateLogIfNeeded();

        $timestamp = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = sprintf("[%s] %s: %s%s%s", $timestamp, strtoupper($level), $message, $contextString, PHP_EOL);

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function rotateLogIfNeeded()
    {
        if (!file_exists($this->logFile) || filesize($this->logFile) < $this->maxFileSize) {
            return;
        }

        for ($i = $this->maxFiles - 1; $i > 0; $i--) {
            $oldFile = $this->logFile . '.' . $i;
            $newFile = $this->logFile . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                if ($i == $this->maxFiles - 1) {
                    unlink($oldFile);
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }

        rename($this->logFile, $this->logFile . '.1');
    }

    public function debug($message, $context = [])
    {
        $this->log('debug', $message, $context);
    }

    public function info($message, $context = [])
    {
        $this->log('info', $message, $context);
    }

    public function warning($message, $context = [])
    {
        $this->log('warning', $message, $context);
    }

    public function error($message, $context = [])
    {
        $this->log('error', $message, $context);
    }

    public function critical($message, $context = [])
    {
        $this->log('critical', $message, $context);
    }

    public function setLogLevel($level)
    {
        if (!isset($this->logLevels[$level])) {
            throw new InvalidArgumentException("Invalid log level: $level");
        }
        $this->minLogLevel = $level;
    }

    public function getLogLevel()
    {
        return $this->minLogLevel;
    }

    public function clearLogs()
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }

        for ($i = 1; $i <= $this->maxFiles; $i++) {
            $rotatedFile = $this->logFile . '.' . $i;
            if (file_exists($rotatedFile)) {
                unlink($rotatedFile);
            }
        }
    }

    public function getRecentLogs($lines = 100)
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $file = new SplFileObject($this->logFile);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key() + 1;
        $startLine = max(0, $totalLines - $lines);

        $logs = [];
        $file->rewind();
        for ($i = 0; $i < $totalLines; $i++) {
            if ($i >= $startLine) {
                $logs[] = trim($file->current());
            }
            $file->next();
        }

        return $logs;
    }
}

class LogManager
{
    private static $instances = [];

    public static function getInstance($name = 'default', $logFile = null, $minLogLevel = 'info')
    {
        if (!isset(self::$instances[$name])) {
            $logFile = $logFile ?: "logs/{$name}.log";
            self::$instances[$name] = new Logger($logFile, $minLogLevel);
        }
        return self::$instances[$name];
    }

    public static function createLogger($name, $logFile, $minLogLevel = 'info', $maxFileSize = 10485760, $maxFiles = 5)
    {
        self::$instances[$name] = new Logger($logFile, $minLogLevel, $maxFileSize, $maxFiles);
        return self::$instances[$name];
    }
}

function logger($name = 'default')
{
    return LogManager::getInstance($name);
}

function log_message($level, $message, $context = [], $loggerName = 'default')
{
    LogManager::getInstance($loggerName)->log($level, $message, $context);
}

function log_info($message, $context = [], $loggerName = 'default')
{
    LogManager::getInstance($loggerName)->info($message, $context);
}

function log_error($message, $context = [], $loggerName = 'default')
{
    LogManager::getInstance($loggerName)->error($message, $context);
}

function log_debug($message, $context = [], $loggerName = 'default')
{
    LogManager::getInstance($loggerName)->debug($message, $context);
}

function log_warning($message, $context = [], $loggerName = 'default')
{
    LogManager::getInstance($loggerName)->warning($message, $context);
}

function log_critical($message, $context = [], $loggerName = 'default')
{
    LogManager::getInstance($loggerName)->critical($message, $context);
}

?>