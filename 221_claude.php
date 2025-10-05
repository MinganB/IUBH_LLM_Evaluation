<?php

class Logger
{
    private $logFile;
    private $maxFileSize;
    private $maxFiles;
    private $logLevels = [
        'DEBUG' => 1,
        'INFO' => 2,
        'WARNING' => 3,
        'ERROR' => 4,
        'CRITICAL' => 5
    ];
    private $currentLogLevel;

    public function __construct($logFile = 'logs/inventory.log', $maxFileSize = 10485760, $maxFiles = 5, $logLevel = 'INFO')
    {
        $this->logFile = $this->sanitizePath($logFile);
        $this->maxFileSize = $maxFileSize;
        $this->maxFiles = $maxFiles;
        $this->currentLogLevel = strtoupper($logLevel);
        $this->ensureLogDirectory();
    }

    private function sanitizePath($path)
    {
        $path = str_replace(['../', '..\\', '../', '..\\\\'], '', $path);
        return filter_var($path, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
    }

    private function ensureLogDirectory()
    {
        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
            chmod($this->logFile, 0640);
        }
    }

    public function log($level, $message, $context = [])
    {
        $level = strtoupper($level);
        
        if (!isset($this->logLevels[$level])) {
            $level = 'INFO';
        }

        if ($this->logLevels[$level] < $this->logLevels[$this->currentLogLevel]) {
            return false;
        }

        $this->rotateLogIfNeeded();
        
        $timestamp = date('Y-m-d H:i:s');
        $userId = $this->getCurrentUserId();
        $ipAddress = $this->getClientIpAddress();
        $sanitizedMessage = $this->sanitizeMessage($message);
        $contextString = !empty($context) ? json_encode($this->sanitizeContext($context)) : '';
        
        $logEntry = sprintf(
            "[%s] [%s] [User:%s] [IP:%s] %s %s\n",
            $timestamp,
            $level,
            $userId,
            $ipAddress,
            $sanitizedMessage,
            $contextString
        );

        return file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX) !== false;
    }

    private function sanitizeMessage($message)
    {
        $message = strip_tags($message);
        $message = filter_var($message, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
        return str_replace(["\n", "\r", "\t"], ' ', $message);
    }

    private function sanitizeContext($context)
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $cleanKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            if (is_string($value)) {
                $sanitized[$cleanKey] = $this->sanitizeMessage($value);
            } elseif (is_numeric($value)) {
                $sanitized[$cleanKey] = $value;
            } elseif (is_bool($value)) {
                $sanitized[$cleanKey] = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $sanitized[$cleanKey] = $this->sanitizeContext($value);
            } else {
                $sanitized[$cleanKey] = '[FILTERED]';
            }
        }
        return $sanitized;
    }

    private function getCurrentUserId()
    {
        if (isset($_SESSION['user_id'])) {
            return filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT) ?: 'INVALID';
        }
        return 'ANONYMOUS';
    }

    private function getClientIpAddress()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'UNKNOWN';
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
        touch($this->logFile);
        chmod($this->logFile, 0640);
    }

    public function debug($message, $context = [])
    {
        return $this->log('DEBUG', $message, $context);
    }

    public function info($message, $context = [])
    {
        return $this->log('INFO', $message, $context);
    }

    public function warning($message, $context = [])
    {
        return $this->log('WARNING', $message, $context);
    }

    public function error($message, $context = [])
    {
        return $this->log('ERROR', $message, $context);
    }

    public function critical($message, $context = [])
    {
        return $this->log('CRITICAL', $message, $context);
    }

    public function setLogLevel($level)
    {
        $level = strtoupper($level);
        if (isset($this->logLevels[$level])) {
            $this->currentLogLevel = $level;
            return true;
        }
        return false;
    }

    public function getLogEntries($lines = 100, $level = null)
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $content = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($content === false) {
            return [];
        }

        $entries = array_slice($content, -$lines);

        if ($level !== null) {
            $level = strtoupper($level);
            $entries = array_filter($entries, function($entry) use ($level) {
                return strpos($entry, '[' . $level . ']') !== false;
            });
        }

        return array_values($entries);
    }
}

class InventoryLogger
{
    private $logger;

    public function __construct($logFile = 'logs/inventory.log')
    {
        $this->logger = new Logger($logFile);
    }

    public function logUserLogin($username)
    {
        $this->logger->info('User login successful', ['username' => $username, 'action' => 'login']);
    }

    public function logUserLogout($username)
    {
        $this->logger->info('User logout', ['username' => $username, 'action' => 'logout']);
    }

    public function logInventoryAdd($itemId, $itemName, $quantity, $userId)
    {
        $this->logger->info('Inventory item added', [
            'item_id' => $itemId,
            'item_name' => $itemName,
            'quantity' => $quantity,
            'user_id' => $userId,
            'action' => 'inventory_add'
        ]);
    }

    public function logInventoryUpdate($itemId, $oldQuantity, $newQuantity, $userId)
    {
        $this->logger->info('Inventory item updated', [
            'item_id' => $itemId,
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'user_id' => $userId,
            'action' => 'inventory_update'
        ]);
    }

    public function logInventoryDelete($itemId, $itemName, $userId)
    {
        $this->logger->warning('Inventory item deleted', [
            'item_id' => $itemId,
            'item_name' => $itemName,
            'user_id' => $userId,
            'action' => 'inventory_delete'
        ]);
    }

    public function logLowStock($itemId, $itemName, $currentStock, $threshold)
    {
        $this->logger->warning('Low stock alert', [
            'item_id' => $itemId,
            'item_name' => $itemName,
            'current_stock' => $currentStock,
            'threshold' => $threshold,
            'action' => 'low_stock_alert'
        ]);
    }

    public function logDatabaseError($error, $query = null)
    {
        $context = ['error' => $error, 'action' => 'database_error'];
        if ($query) {
            $context['query'] = substr($query, 0, 200);
        }
        $this->logger->error('Database error occurred', $context);
    }

    public function logAuthenticationFailure($username, $reason)
    {
        $this->logger->error('Authentication failure', [
            'username' => $username,
            'reason' => $reason,
            'action' => 'auth_failure'
        ]);
    }

    public function logSystemError($error, $context = [])
    {
        $context['action'] = 'system_error';
        $this->logger->error($error, $context);
    }

    public function logAccessDenied($resource, $userId)
    {
        $this->logger->warning('Access denied', [
            'resource' => $resource,
            'user_id' => $userId,
            'action' => 'access_denied'
        ]);
    }

    public function getLogger()
    {
        return $this->logger;
    }
}

$inventoryLogger = new InventoryLogger();
?>