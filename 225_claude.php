<?php

class Logger {
    const DEBUG = 'DEBUG';
    const INFO = 'INFO';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    const CRITICAL = 'CRITICAL';
    
    private $logFile;
    private $maxFileSize;
    private $sensitivePatterns;
    
    public function __construct($logFile = null, $maxFileSize = 10485760) {
        $this->logFile = $logFile ?: dirname(__DIR__) . '/logs/inventory_' . date('Y-m-d') . '.log';
        $this->maxFileSize = $maxFileSize;
        $this->sensitivePatterns = [
            '/password["\']?\s*[:=]\s*["\']?[^,\]\}\s]+/i',
            '/pass["\']?\s*[:=]\s*["\']?[^,\]\}\s]+/i',
            '/pwd["\']?\s*[:=]\s*["\']?[^,\]\}\s]+/i',
            '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/',
            '/\b\d{13,19}\b/',
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
            '/ssn["\']?\s*[:=]\s*["\']?\d{3}-?\d{2}-?\d{4}/i',
            '/social["\']?\s*[:=]\s*["\']?\d{3}-?\d{2}-?\d{4}/i',
            '/\b\d{3}-?\d{2}-?\d{4}\b/',
            '/api[_-]?key["\']?\s*[:=]\s*["\']?[a-zA-Z0-9]+/i',
            '/token["\']?\s*[:=]\s*["\']?[a-zA-Z0-9]+/i',
            '/secret["\']?\s*[:=]\s*["\']?[a-zA-Z0-9]+/i'
        ];
        $this->initializeLogFile();
    }
    
    private function initializeLogFile() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxFileSize) {
            $this->rotateLogFile();
        }
    }
    
    private function rotateLogFile() {
        $timestamp = date('Y-m-d_H-i-s');
        $rotatedFile = str_replace('.log', '_' . $timestamp . '.log', $this->logFile);
        rename($this->logFile, $rotatedFile);
    }
    
    private function sanitizeMessage($message) {
        foreach ($this->sensitivePatterns as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', $message);
        }
        return $message;
    }
    
    private function getSource() {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        
        foreach ($backtrace as $trace) {
            if (isset($trace['file']) && !strpos($trace['file'], 'Logger.php')) {
                $file = basename($trace['file']);
                $line = $trace['line'] ?? 'unknown';
                $function = '';
                
                if (isset($trace['class']) && isset($trace['function'])) {
                    $function = $trace['class'] . '::' . $trace['function'];
                } elseif (isset($trace['function'])) {
                    $function = $trace['function'];
                }
                
                return $file . ':' . $line . ($function ? ' (' . $function . ')' : '');
            }
        }
        
        return 'unknown';
    }
    
    private function writeLog($level, $message, $context = []) {
        if (!in_array($level, [self::DEBUG, self::INFO, self::WARNING, self::ERROR, self::CRITICAL])) {
            $level = self::INFO;
        }
        
        $sanitizedMessage = $this->sanitizeMessage($message);
        $sanitizedContext = $this->sanitizeMessage(json_encode($context));
        
        $timestamp = date('Y-m-d H:i:s');
        $source = $this->getSource();
        $processId = getmypid();
        $memoryUsage = round(memory_get_usage() / 1024 / 1024, 2);
        
        $logEntry = sprintf(
            "[%s] [%s] [PID:%s] [MEM:%sMB] [%s] %s",
            $timestamp,
            $level,
            $processId,
            $memoryUsage,
            $source,
            $sanitizedMessage
        );
        
        if (!empty($context) && $sanitizedContext !== '[]') {
            $logEntry .= ' | Context: ' . $sanitizedContext;
        }
        
        $logEntry .= PHP_EOL;
        
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxFileSize) {
            $this->rotateLogFile();
        }
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        if ($level === self::CRITICAL || $level === self::ERROR) {
            error_log($logEntry);
        }
    }
    
    public function debug($message, $context = []) {
        $this->writeLog(self::DEBUG, $message, $context);
    }
    
    public function info($message, $context = []) {
        $this->writeLog(self::INFO, $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->writeLog(self::WARNING, $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->writeLog(self::ERROR, $message, $context);
    }
    
    public function critical($message, $context = []) {
        $this->writeLog(self::CRITICAL, $message, $context);
    }
    
    public function log($level, $message, $context = []) {
        $this->writeLog($level, $message, $context);
    }
}

class InventoryLogger {
    private static $instance = null;
    private $logger;
    
    private function __construct() {
        $this->logger = new Logger();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function debug($message, $context = []) {
        self::getInstance()->logger->debug($message, $context);
    }
    
    public static function info($message, $context = []) {
        self::getInstance()->logger->info($message, $context);
    }
    
    public static function warning($message, $context = []) {
        self::getInstance()->logger->warning($message, $context);
    }
    
    public static function error($message, $context = []) {
        self::getInstance()->logger->error($message, $context);
    }
    
    public static function critical($message, $context = []) {
        self::getInstance()->logger->critical($message, $context);
    }
    
    public static function userAction($userId, $action, $details = []) {
        $message = "User action: {$action}";
        $context = array_merge(['user_id' => $userId, 'action' => $action], $details);
        self::getInstance()->logger->info($message, $context);
    }
    
    public static function systemEvent($event, $details = []) {
        $message = "System event: {$event}";
        $context = array_merge(['event' => $event], $details);
        self::getInstance()->logger->info($message, $context);
    }
    
    public static function inventoryChange($itemId, $action, $quantity = null, $userId = null) {
        $message = "Inventory change: {$action} for item {$itemId}";
        $context = [
            'item_id' => $itemId,
            'action' => $action,
            'quantity' => $quantity,
            'user_id' => $userId
        ];
        self::getInstance()->logger->info($message, $context);
    }
    
    public static function authenticationAttempt($username, $success, $ipAddress = null) {
        $status = $success ? 'successful' : 'failed';
        $message = "Authentication attempt {$status} for user: {$username}";
        $context = [
            'username' => $username,
            'success' => $success,
            'ip_address' => $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        ];
        
        if ($success) {
            self::getInstance()->logger->info($message, $context);
        } else {
            self::getInstance()->logger->warning($message, $context);
        }
    }
    
    public static function databaseError($query, $error, $context = []) {
        $message = "Database error: {$error}";
        $sanitizedQuery = preg_replace('/VALUES\s*\([^)]*\)/i', 'VALUES ([REDACTED])', $query);
        $logContext = array_merge([
            'query' => $sanitizedQuery,
            'error' => $error
        ], $context);
        self::getInstance()->logger->error($message, $logContext);
    }
    
    public static function apiRequest($endpoint, $method, $userId = null, $responseCode = null) {
        $message = "API request: {$method} {$endpoint}";
        $context = [
            'endpoint' => $endpoint,
            'method' => $method,
            'user_id' => $userId,
            'response_code' => $responseCode,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        self::getInstance()->logger->info($message, $context);
    }
    
    public static function securityAlert($alert, $details = []) {
        $message = "Security alert: {$alert}";
        $context = array_merge([
            'alert' => $alert,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ], $details);
        self::getInstance()->logger->critical($message, $context);
    }
}

function logInventory($level, $message, $context = []) {
    switch (strtolower($level)) {
        case 'debug':
            InventoryLogger::debug($message, $context);
            break;
        case 'info':
            InventoryLogger::info($message, $context);
            break;
        case 'warning':
        case 'warn':
            InventoryLogger::warning($message, $context);
            break;
        case 'error':
            InventoryLogger::error($message, $context);
            break;
        case 'critical':
            InventoryLogger::critical($message, $context);
            break;
        default:
            InventoryLogger::info($message, $context);
            break;
    }
}

?>