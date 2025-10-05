<?php

class Logger
{
    private $logLevels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    ];

    private $currentLevel;
    private $logFile;
    private $sensitivePatterns = [
        '/password["\']?\s*[:=]\s*["\']?[^,}\]"\'\s]+/i',
        '/passwd["\']?\s*[:=]\s*["\']?[^,}\]"\'\s]+/i',
        '/pwd["\']?\s*[:=]\s*["\']?[^,}\]"\'\s]+/i',
        '/pass["\']?\s*[:=]\s*["\']?[^,}\]"\'\s]+/i',
        '/\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|3[47][0-9]{13}|3[0-9]{13}|6(?:011|5[0-9]{2})[0-9]{12})\b/',
        '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/',
        '/\b\d{3}-\d{2}-\d{4}\b/',
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
        '/token["\']?\s*[:=]\s*["\']?[^,}\]"\'\s]+/i',
        '/key["\']?\s*[:=]\s*["\']?[^,}\]"\'\s]+/i',
        '/secret["\']?\s*[:=]\s*["\']?[^,}\]"\'\s]+/i',
        '/api_key["\']?\s*[:=]\s*["\']?[^,}\]"\'\s]+/i',
        '/apikey["\']?\s*[:=]\s*["\']?[^,}\]"\'\s]+/i',
        '/auth["\']?\s*[:=]\s*["\']?[^,}\]"\'\s]+/i',
        '/authorization["\']?\s*[:=]\s*["\']?[^,}\]"\'\s]+/i',
        '/bearer["\']?\s*[:=]\s*["\']?[^,}\]"\'\s]+/i'
    ];

    public function __construct($logFile = 'application.log', $level = 'info')
    {
        $this->logFile = $logFile;
        $this->currentLevel = $this->logLevels[$level] ?? 1;
    }

    public function setLevel($level)
    {
        if (isset($this->logLevels[$level])) {
            $this->currentLevel = $this->logLevels[$level];
        }
    }

    public function log($level, $message, $context = [])
    {
        if (!isset($this->logLevels[$level]) || $this->logLevels[$level] < $this->currentLevel) {
            return false;
        }

        $sanitizedMessage = $this->sanitizeMessage($message);
        $sanitizedContext = $this->sanitizeContext($context);
        
        $logEntry = $this->formatLogEntry($level, $sanitizedMessage, $sanitizedContext);
        
        return $this->writeLog($logEntry);
    }

    public function debug($message, $context = [])
    {
        return $this->log('debug', $message, $context);
    }

    public function info($message, $context = [])
    {
        return $this->log('info', $message, $context);
    }

    public function warning($message, $context = [])
    {
        return $this->log('warning', $message, $context);
    }

    public function error($message, $context = [])
    {
        return $this->log('error', $message, $context);
    }

    public function critical($message, $context = [])
    {
        return $this->log('critical', $message, $context);
    }

    private function sanitizeMessage($message)
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }

        foreach ($this->sensitivePatterns as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', $message);
        }

        return $message;
    }

    private function sanitizeContext($context)
    {
        if (!is_array($context)) {
            return [];
        }

        $sanitized = [];
        foreach ($context as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                $sanitized[$key] = '[REDACTED]';
            } else if (is_string($value)) {
                $sanitized[$key] = $this->sanitizeMessage($value);
            } else if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function isSensitiveKey($key)
    {
        $sensitiveKeys = [
            'password', 'passwd', 'pwd', 'pass', 'token', 'key', 'secret', 
            'api_key', 'apikey', 'auth', 'authorization', 'bearer', 'credit_card',
            'cc_number', 'card_number', 'ssn', 'social_security', 'email'
        ];

        return in_array(strtolower($key), $sensitiveKeys);
    }

    private function formatLogEntry($level, $message, $context)
    {
        $timestamp = date('Y-m-d H:i:s');
        $source = $this->getSource();
        
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'message' => $message,
            'source' => $source
        ];

        if (!empty($context)) {
            $logEntry['context'] = $context;
        }

        return json_encode($logEntry) . PHP_EOL;
    }

    private function getSource()
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        foreach ($backtrace as $trace) {
            if (isset($trace['file']) && $trace['file'] !== __FILE__) {
                return [
                    'file' => basename($trace['file']),
                    'line' => $trace['line'] ?? null,
                    'function' => $trace['function'] ?? null
                ];
            }
        }

        return [
            'file' => 'unknown',
            'line' => null,
            'function' => null
        ];
    }

    private function writeLog($logEntry)
    {
        $result = file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        return $result !== false;
    }

    public function clearLog()
    {
        return file_put_contents($this->logFile, '');
    }

    public function getLogFile()
    {
        return $this->logFile;
    }
}

class LogManager
{
    private static $instance = null;
    private $logger;

    private function __construct()
    {
        $this->logger = new Logger();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new LogManager();
        }
        return self::$instance;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function configure($logFile = 'application.log', $level = 'info')
    {
        $this->logger = new Logger($logFile, $level);
    }
}

function logger()
{
    return LogManager::getInstance()->getLogger();
}

function logInfo($message, $context = [])
{
    return logger()->info($message, $context);
}

function logError($message, $context = [])
{
    return logger()->error($message, $context);
}

function logDebug($message, $context = [])
{
    return logger()->debug($message, $context);
}

function logWarning($message, $context = [])
{
    return logger()->warning($message, $context);
}

function logCritical($message, $context = [])
{
    return logger()->critical($message, $context);
}
?>