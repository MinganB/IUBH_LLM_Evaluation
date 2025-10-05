<?php

namespace InventorySystem;

class Logger
{
    private $logDirectory;
    private $logFile;
    private $dateFormat;
    private $maxFileSize;
    private $maxFiles;

    const EMERGENCY = 0;
    const ALERT = 1;
    const CRITICAL = 2;
    const ERROR = 3;
    const WARNING = 4;
    const NOTICE = 5;
    const INFO = 6;
    const DEBUG = 7;

    private $logLevels = [
        0 => 'EMERGENCY',
        1 => 'ALERT',
        2 => 'CRITICAL',
        3 => 'ERROR',
        4 => 'WARNING',
        5 => 'NOTICE',
        6 => 'INFO',
        7 => 'DEBUG'
    ];

    public function __construct($logDirectory = '../logs/', $maxFileSize = 5242880, $maxFiles = 5)
    {
        $this->logDirectory = rtrim($logDirectory, '/') . '/';
        $this->maxFileSize = $maxFileSize;
        $this->maxFiles = $maxFiles;
        $this->dateFormat = 'Y-m-d H:i:s';
        $this->logFile = $this->logDirectory . 'inventory_' . date('Y-m-d') . '.log';
        
        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0755, true);
        }
    }

    public function log($level, $message, array $context = [])
    {
        $levelName = $this->getLevelName($level);
        $timestamp = date($this->dateFormat);
        $contextString = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] {$levelName}: {$message}{$contextString}" . PHP_EOL;

        $this->writeLog($logEntry);
        $this->rotateLogIfNeeded();
    }

    public function emergency($message, array $context = [])
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = [])
    {
        $this->log(self::ALERT, $message, $context);
    }

    public function critical($message, array $context = [])
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    public function error($message, array $context = [])
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function warning($message, array $context = [])
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function notice($message, array $context = [])
    {
        $this->log(self::NOTICE, $message, $context);
    }

    public function info($message, array $context = [])
    {
        $this->log(self::INFO, $message, $context);
    }

    public function debug($message, array $context = [])
    {
        $this->log(self::DEBUG, $message, $context);
    }

    private function getLevelName($level)
    {
        if (is_string($level)) {
            $level = strtoupper($level);
            $levelKey = array_search($level, $this->logLevels);
            return $levelKey !== false ? $level : 'INFO';
        }

        return isset($this->logLevels[$level]) ? $this->logLevels[$level] : 'INFO';
    }

    private function writeLog($logEntry)
    {
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function rotateLogIfNeeded()
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        if (filesize($this->logFile) >= $this->maxFileSize) {
            $this->rotateLogFiles();
        }
    }

    private function rotateLogFiles()
    {
        $baseFileName = $this->logDirectory . 'inventory_' . date('Y-m-d');
        
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $baseFileName . '.' . $i . '.log';
            $newFile = $baseFileName . '.' . ($i + 1) . '.log';
            
            if (file_exists($oldFile)) {
                if ($i + 1 > $this->maxFiles) {
                    unlink($oldFile);
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }
        
        if (file_exists($this->logFile)) {
            rename($this->logFile, $baseFileName . '.1.log');
        }
    }

    public function getLogEntries($date = null, $level = null, $limit = 100)
    {
        $logFile = $date ? $this->logDirectory . 'inventory_' . $date . '.log' : $this->logFile;
        
        if (!file_exists($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $entries = [];

        foreach (array_reverse($lines) as $line) {
            if ($level) {
                $levelName = $this->getLevelName($level);
                if (strpos($line, $levelName . ':') === false) {
                    continue;
                }
            }
            
            $entries[] = $line;
            
            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    public function clearLogs($date = null)
    {
        if ($date) {
            $logFile = $this->logDirectory . 'inventory_' . $date . '.log';
            if (file_exists($logFile)) {
                unlink($logFile);
            }
        } else {
            $files = glob($this->logDirectory . 'inventory_*.log*');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
}


<?php

namespace InventorySystem;

require_once '../classes/Logger.php';

class LoggerManager
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
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function logUserAction($userId, $action, $details = [])
    {
        $context = array_merge([
            'user_id' => $userId,
            'action' => $action,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ], $details);

        $this->logger->info("User action: {$action}", $context);
    }

    public function logSystemEvent($event, $details = [])
    {
        $context = array_merge([
            'event' => $event,
            'timestamp' => time(),
            'memory_usage' => memory_get_usage(true)
        ], $details);

        $this->logger->info("System event: {$event}", $context);
    }

    public function logInventoryChange($itemId, $changeType, $oldValue, $newValue, $userId = null)
    {
        $context = [
            'item_id' => $itemId,
            'change_type' => $changeType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'user_id' => $userId
        ];

        $this->logger->info("Inventory change: {$changeType} for item {$itemId}", $context);
    }

    public function logError($error, $context = [])
    {
        $errorContext = array_merge([
            'file' => debug_backtrace()[0]['file'] ?? 'unknown',
            'line' => debug_backtrace()[0]['line'] ?? 'unknown',
            'timestamp' => time()
        ], $context);

        $this->logger->error($error, $errorContext);
    }

    public function logDatabaseQuery($query, $executionTime, $parameters = [])
    {
        $context = [
            'query' => $query,
            'execution_time' => $executionTime,
            'parameters' => $parameters
        ];

        $this->logger->debug("Database query executed", $context);
    }

    public function logSecurityEvent($eventType, $details = [])
    {
        $context = array_merge([
            'event_type' => $eventType,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => time()
        ], $details);

        $this->logger->warning("Security event: {$eventType}", $context);
    }
}


<?php

namespace InventorySystem;

require_once '../classes/LoggerManager.php';

class AuditLogger
{
    private $loggerManager;

    public function __construct()
    {
        $this->loggerManager = LoggerManager::getInstance();
    }

    public function logLogin($userId, $success = true, $failureReason = null)
    {
        if ($success) {
            $this->loggerManager->logUserAction($userId, 'login', ['status' => 'success']);
        } else {
            $this->loggerManager->logSecurityEvent('failed_login', [
                'user_id' => $userId,
                'reason' => $failureReason
            ]);
        }
    }

    public function logLogout($userId)
    {
        $this->loggerManager->logUserAction($userId, 'logout', ['status' => 'success']);
    }

    public function logItemCreation($itemId, $itemData, $userId)
    {
        $this->loggerManager->logInventoryChange($itemId, 'item_created', null, $itemData, $userId);
    }

    public function logItemUpdate($itemId, $oldData, $newData, $userId)
    {
        $this->loggerManager->logInventoryChange($itemId, 'item_updated', $oldData, $newData, $userId);
    }

    public function logItemDeletion($itemId, $itemData, $userId)
    {
        $this->loggerManager->logInventoryChange($itemId, 'item_deleted', $itemData, null, $userId);
    }

    public function logStockUpdate($itemId, $oldQuantity, $newQuantity, $userId, $reason = 'manual_adjustment')
    {
        $this->loggerManager->logInventoryChange($itemId, 'stock_updated', $oldQuantity, $newQuantity, $userId);
        $this->loggerManager->getLogger()->info("Stock adjustment reason: {$reason}", [
            'item_id' => $itemId,
            'reason' => $reason,
            'user_id' => $userId
        ]);
    }

    public function logPermissionChange($targetUserId, $permission, $granted, $adminUserId)
    {
        $action = $granted ? 'permission_granted' : 'permission_revoked';
        $this->loggerManager->logUserAction($adminUserId, $action, [
            'target_user_id' => $targetUserId,
            'permission' => $permission
        ]);
    }

    public function logDataExport($exportType, $userId, $recordCount = null)
    {
        $this->loggerManager->logUserAction($userId, 'data_export', [
            'export_type' => $exportType,
            'record_count' => $recordCount
        ]);
    }

    public function logDataImport($importType, $userId, $recordCount = null, $errors = [])
    {
        $this->loggerManager->logUserAction($userId, 'data_import', [
            'import_type' => $importType,
            'record_count' => $recordCount,
            'error_count' => count($errors)
        ]);

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->loggerManager->logError("Import error: {$error}", [
                    'import_type' => $importType,
                    'user_id' => $userId
                ]);
            }
        }
    }

    public function logConfigurationChange($setting, $oldValue, $newValue, $userId)
    {
        $this->loggerManager->logUserAction($userId, 'configuration_change', [
            'setting' => $setting,
            'old_value' => $oldValue,
            'new_value' => $newValue
        ]);
    }
}


<?php

namespace InventorySystem;

require_once '../classes/LoggerManager.php';

function logUserAction($userId, $action, $details = [])
{
    $loggerManager = LoggerManager::getInstance();
    $loggerManager->logUserAction($userId, $action, $details);
}

function logSystemEvent($event, $details = [])
{
    $loggerManager = LoggerManager::getInstance();
    $loggerManager->logSystemEvent($event, $details);
}

function logError($error, $context = [])
{
    $loggerManager = LoggerManager::getInstance();
    $loggerManager->logError($error, $context);
}

function logInfo($message, $context = [])
{
    $logger = LoggerManager::getInstance()->getLogger();
    $logger->info($message, $context);
}

function logDebug($message, $context = [])
{
    $logger = LoggerManager::getInstance()->getLogger();
    $logger->debug($message, $context);
}

function logWarning($message, $context = [])
{
    $logger = LoggerManager::getInstance()->getLogger();
    $logger->warning($message, $context);
}

function logCritical($message, $context = [])
{
    $logger = LoggerManager::getInstance()->getLogger();
    $logger->critical($message, $context);
}

function getLogEntries($date = null, $level = null, $limit = 100)
{
    $logger = LoggerManager::getInstance()->getLogger();
    return $logger->getLogEntries($date, $level, $limit);
}

set_error_handler(function($severity, $message, $file, $line) {
    $loggerManager = LoggerManager::getInstance();
    $loggerManager->logError("PHP Error: {$message}", [
        'severity' => $severity,
        'file' => $file,
        'line' => $line
    ]);
});

set_exception_handler(function($exception) {
    $loggerManager = LoggerManager::getInstance();
    $loggerManager->logError("Uncaught Exception: " . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR])) {
        $loggerManager = LoggerManager::getInstance();
        $loggerManager->logError("Fatal Error: " . $error['message'], [
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});
?>