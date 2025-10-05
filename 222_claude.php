<?php

class InventoryLogger
{
    private $logFile;
    private $logLevels = [
        'debug' => 1,
        'info' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5
    ];
    private $currentLogLevel;
    private $sensitivePatterns = [
        '/password["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i',
        '/pass["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i',
        '/pwd["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i',
        '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/',
        '/\b\d{3}-\d{2}-\d{4}\b/',
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
        '/api[_-]?key["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i',
        '/token["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i',
        '/secret["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i',
        '/credit[_-]?card["\']?\s*[:=]\s*["\']?[^"\'\s,}]+/i'
    ];
    private $replacementText = '[REDACTED]';

    public function __construct($logFile = null, $logLevel = 'info')
    {
        $this->logFile = $logFile ?: dirname(__FILE__) . '/inventory_system.log';
        $this->currentLogLevel = $this->logLevels[$logLevel] ?? $this->logLevels['info'];
        $this->ensureLogFileExists();
    }

    private function ensureLogFileExists()
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
            chmod($this->logFile, 0644);
        }
    }

    private function sanitizeMessage($message)
    {
        foreach ($this->sensitivePatterns as $pattern) {
            $message = preg_replace($pattern, $this->replacementText, $message);
        }
        
        if (is_array($message) || is_object($message)) {
            $message = $this->sanitizeComplexData($message);
        }
        
        return $message;
    }

    private function sanitizeComplexData($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($this->isSensitiveKey($key)) {
                    $data[$key] = $this->replacementText;
                } elseif (is_array($value) || is_object($value)) {
                    $data[$key] = $this->sanitizeComplexData($value);
                } elseif (is_string($value)) {
                    $data[$key] = $this->sanitizeMessage($value);
                }
            }
        } elseif (is_object($data)) {
            $data = (array) $data;
            foreach ($data as $key => $value) {
                if ($this->isSensitiveKey($key)) {
                    $data[$key] = $this->replacementText;
                } elseif (is_array($value) || is_object($value)) {
                    $data[$key] = $this->sanitizeComplexData($value);
                } elseif (is_string($value)) {
                    $data[$key] = $this->sanitizeMessage($value);
                }
            }
            $data = (object) $data;
        }
        
        return $data;
    }

    private function isSensitiveKey($key)
    {
        $sensitiveKeys = [
            'password', 'pass', 'pwd', 'passwd', 'secret', 'token',
            'api_key', 'apikey', 'credit_card', 'creditcard', 'cc',
            'ssn', 'social_security', 'email', 'phone', 'address'
        ];
        
        return in_array(strtolower($key), $sensitiveKeys);
    }

    private function getCallerInfo()
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        
        if (isset($backtrace[2])) {
            $caller = $backtrace[2];
            $file = basename($caller['file'] ?? 'unknown');
            $line = $caller['line'] ?? 0;
            $function = $caller['function'] ?? 'unknown';
            $class = isset($caller['class']) ? $caller['class'] . '::' : '';
            
            return $file . ':' . $line . ' ' . $class . $function . '()';
        }
        
        return 'unknown';
    }

    private function formatMessage($level, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $source = $this->getCallerInfo();
        $level = strtoupper($level);
        
        $sanitizedMessage = $this->sanitizeMessage($message);
        $sanitizedContext = !empty($context) ? $this->sanitizeComplexData($context) : null;
        
        if (is_array($sanitizedMessage) || is_object($sanitizedMessage)) {
            $sanitizedMessage = json_encode($sanitizedMessage);
        }
        
        $logEntry = "[{$timestamp}] [{$level}] [{$source}] {$sanitizedMessage}";
        
        if ($sanitizedContext) {
            $logEntry .= ' Context: ' . json_encode($sanitizedContext);
        }
        
        return $logEntry . PHP_EOL;
    }

    private function writeLog($formattedMessage)
    {
        file_put_contents($this->logFile, $formattedMessage, FILE_APPEND | LOCK_EX);
    }

    private function shouldLog($level)
    {
        return isset($this->logLevels[$level]) && $this->logLevels[$level] >= $this->currentLogLevel;
    }

    public function log($level, $message, $context = [])
    {
        if (!$this->shouldLog($level)) {
            return;
        }
        
        $formattedMessage = $this->formatMessage($level, $message, $context);
        $this->writeLog($formattedMessage);
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
        if (isset($this->logLevels[$level])) {
            $this->currentLogLevel = $this->logLevels[$level];
        }
    }

    public function getLogFile()
    {
        return $this->logFile;
    }

    public function clearLog()
    {
        file_put_contents($this->logFile, '');
    }

    public function getRecentLogs($lines = 100)
    {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $file = new SplFileObject($this->logFile, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        
        $startLine = max(0, $totalLines - $lines);
        $logs = [];
        
        $file->seek($startLine);
        while (!$file->eof()) {
            $line = trim($file->current());
            if (!empty($line)) {
                $logs[] = $line;
            }
            $file->next();
        }
        
        return $logs;
    }
}

$logger = new InventoryLogger();

function logInventoryEvent($level, $message, $context = [])
{
    global $logger;
    $logger->log($level, $message, $context);
}

function logUserAction($userId, $action, $details = [])
{
    global $logger;
    $message = "User {$userId} performed action: {$action}";
    $logger->info($message, $details);
}

function logSystemError($error, $context = [])
{
    global $logger;
    $logger->error($error, $context);
}

function logInventoryChange($itemId, $change, $quantity, $userId)
{
    global $logger;
    $message = "Inventory change - Item: {$itemId}, Change: {$change}, Quantity: {$quantity}, User: {$userId}";
    $logger->info($message);
}

function logSecurityEvent($event, $userId = null, $details = [])
{
    global $logger;
    $message = "Security event: {$event}";
    if ($userId) {
        $message .= " - User: {$userId}";
    }
    $logger->warning($message, $details);
}
?>