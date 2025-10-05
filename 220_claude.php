<?php

class Logger {
    
    private $logFile;
    private $logLevels = [
        'debug' => 1,
        'info' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5
    ];
    private $currentLogLevel;
    
    public function __construct($logFile = 'inventory_system.log', $logLevel = 'info') {
        $this->logFile = $logFile;
        $this->currentLogLevel = $this->logLevels[$logLevel] ?? 2;
        $this->ensureLogFileExists();
    }
    
    private function ensureLogFileExists() {
        if (!file_exists($this->logFile)) {
            $logDir = dirname($this->logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            touch($this->logFile);
            chmod($this->logFile, 0644);
        }
    }
    
    public function log($level, $message, $context = []) {
        if (!isset($this->logLevels[$level])) {
            throw new InvalidArgumentException("Invalid log level: $level");
        }
        
        if ($this->logLevels[$level] < $this->currentLogLevel) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $logEntry = "[$timestamp] [" . strtoupper($level) . "] $message$contextString" . PHP_EOL;
        
        if (file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException("Failed to write to log file: {$this->logFile}");
        }
    }
    
    public function debug($message, $context = []) {
        $this->log('debug', $message, $context);
    }
    
    public function info($message, $context = []) {
        $this->log('info', $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log('warning', $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log('error', $message, $context);
    }
    
    public function critical($message, $context = []) {
        $this->log('critical', $message, $context);
    }
    
    public function setLogLevel($level) {
        if (!isset($this->logLevels[$level])) {
            throw new InvalidArgumentException("Invalid log level: $level");
        }
        $this->currentLogLevel = $this->logLevels[$level];
    }
    
    public function getLogLevel() {
        return array_search($this->currentLogLevel, $this->logLevels);
    }
    
    public function clearLog() {
        file_put_contents($this->logFile, '');
    }
    
    public function getRecentLogs($lines = 100) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $file = new SplFileObject($this->logFile);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key() + 1;
        
        $startLine = max(0, $totalLines - $lines);
        $logs = [];
        
        $file->rewind();
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
    
    public function rotateLogs($maxSize = 10485760) {
        if (!file_exists($this->logFile)) {
            return;
        }
        
        if (filesize($this->logFile) > $maxSize) {
            $rotatedFile = $this->logFile . '.' . date('Y-m-d-H-i-s');
            rename($this->logFile, $rotatedFile);
            touch($this->logFile);
            chmod($this->logFile, 0644);
        }
    }
}

class InventoryLogger {
    
    private static $instance = null;
    private $logger;
    
    private function __construct() {
        $logDir = 'logs/';
        $this->logger = new Logger($logDir . 'inventory.log', 'info');
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function logUserAction($userId, $action, $itemId = null, $details = []) {
        $context = [
            'user_id' => $userId,
            'action' => $action,
            'item_id' => $itemId,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $message = "User $userId performed action: $action";
        if ($itemId) {
            $message .= " on item $itemId";
        }
        
        $this->logger->info($message, $context);
    }
    
    public function logInventoryChange($itemId, $changeType, $oldValue, $newValue, $userId = null) {
        $context = [
            'item_id' => $itemId,
            'change_type' => $changeType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'user_id' => $userId,
            'timestamp' => time()
        ];
        
        $message = "Inventory change: $changeType for item $itemId from '$oldValue' to '$newValue'";
        $this->logger->info($message, $context);
    }
    
    public function logSystemEvent($event, $severity = 'info', $details = []) {
        $context = [
            'event_type' => 'system',
            'details' => $details,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        $this->logger->log($severity, "System event: $event", $context);
    }
    
    public function logError($error, $context = []) {
        $errorContext = [
            'error_type' => get_class($error),
            'error_message' => $error->getMessage(),
            'error_file' => $error->getFile(),
            'error_line' => $error->getLine(),
            'stack_trace' => $error->getTraceAsString(),
            'context' => $context
        ];
        
        $this->logger->error("Application error: " . $error->getMessage(), $errorContext);
    }
    
    public function logLogin($userId, $success = true) {
        $status = $success ? 'successful' : 'failed';
        $severity = $success ? 'info' : 'warning';
        
        $context = [
            'user_id' => $userId,
            'login_status' => $status,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $this->logger->log($severity, "Login attempt $status for user $userId", $context);
    }
    
    public function logDatabaseQuery($query, $executionTime, $success = true) {
        $context = [
            'query' => $query,
            'execution_time' => $executionTime,
            'success' => $success
        ];
        
        if ($success) {
            $this->logger->debug("Database query executed", $context);
        } else {
            $this->logger->error("Database query failed", $context);
        }
    }
    
    public function logStockAlert($itemId, $currentStock, $threshold) {
        $context = [
            'item_id' => $itemId,
            'current_stock' => $currentStock,
            'threshold' => $threshold,
            'alert_type' => 'low_stock'
        ];
        
        $this->logger->warning("Low stock alert for item $itemId: $currentStock units (threshold: $threshold)", $context);
    }
    
    public function getLogger() {
        return $this->logger;
    }
}

function logMessage($level, $message, $context = []) {
    $logger = InventoryLogger::getInstance();
    $logger->getLogger()->log($level, $message, $context);
}

function logUserAction($userId, $action, $itemId = null, $details = []) {
    $logger = InventoryLogger::getInstance();
    $logger->logUserAction($userId, $action, $itemId, $details);
}

function logInventoryChange($itemId, $changeType, $oldValue, $newValue, $userId = null) {
    $logger = InventoryLogger::getInstance();
    $logger->logInventoryChange($itemId, $changeType, $oldValue, $newValue, $userId);
}

function logSystemEvent($event, $severity = 'info', $details = []) {
    $logger = InventoryLogger::getInstance();
    $logger->logSystemEvent($event, $severity, $details);
}

function logError($error, $context = []) {
    $logger = InventoryLogger::getInstance();
    $logger->logError($error, $context);
}

set_error_handler(function($severity, $message, $file, $line) {
    $error = new ErrorException($message, 0, $severity, $file, $line);
    logError($error);
    return false;
});

set_exception_handler(function($exception) {
    logError($exception);
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $exception = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
        logError($exception);
    }
});
?>