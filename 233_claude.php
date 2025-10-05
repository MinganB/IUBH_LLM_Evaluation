<?php

class Logger
{
    private $logFile;
    private $logLevels = [
        'DEBUG' => 1,
        'INFO' => 2,
        'WARNING' => 3,
        'ERROR' => 4,
        'CRITICAL' => 5
    ];
    private $currentLogLevel;

    public function __construct($logFile = 'application.log', $logLevel = 'INFO')
    {
        $this->logFile = $this->sanitizeFilePath($logFile);
        $this->currentLogLevel = strtoupper($logLevel);
        $this->ensureLogDirectoryExists();
    }

    private function sanitizeFilePath($path)
    {
        $path = basename($path);
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $path)) {
            $path = 'application.log';
        }
        return __DIR__ . '/../logs/' . $path;
    }

    private function ensureLogDirectoryExists()
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
    }

    private function writeLog($level, $message, $context = [])
    {
        if (!isset($this->logLevels[$level]) || !isset($this->logLevels[$this->currentLogLevel])) {
            return false;
        }

        if ($this->logLevels[$level] < $this->logLevels[$this->currentLogLevel]) {
            return false;
        }

        $timestamp = date('Y-m-d H:i:s');
        $sanitizedMessage = $this->sanitizeMessage($message);
        $contextString = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        $sessionId = session_id() ?: 'N/A';
        $userIP = $this->getUserIP();
        
        $logEntry = sprintf(
            "[%s] [%s] [Session: %s] [IP: %s] %s%s" . PHP_EOL,
            $timestamp,
            $level,
            $sessionId,
            $userIP,
            $sanitizedMessage,
            $contextString
        );

        return file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX) !== false;
    }

    private function sanitizeMessage($message)
    {
        $message = strip_tags($message);
        $message = preg_replace('/[\x00-\x1F\x7F]/', '', $message);
        return htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    }

    private function getUserIP()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }

    public function debug($message, $context = [])
    {
        return $this->writeLog('DEBUG', $message, $context);
    }

    public function info($message, $context = [])
    {
        return $this->writeLog('INFO', $message, $context);
    }

    public function warning($message, $context = [])
    {
        return $this->writeLog('WARNING', $message, $context);
    }

    public function error($message, $context = [])
    {
        return $this->writeLog('ERROR', $message, $context);
    }

    public function critical($message, $context = [])
    {
        return $this->writeLog('CRITICAL', $message, $context);
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

    public function getLogLevel()
    {
        return $this->currentLogLevel;
    }

    public function rotateLog($maxSize = 10485760)
    {
        if (file_exists($this->logFile) && filesize($this->logFile) > $maxSize) {
            $rotatedFile = $this->logFile . '.' . date('Y-m-d-H-i-s');
            return rename($this->logFile, $rotatedFile);
        }
        return false;
    }
}


<?php

require_once __DIR__ . '/../classes/Logger.php';

session_start();

$logger = new Logger('inventory_system.log', 'DEBUG');

$logger->info('Application started', ['user_id' => $_SESSION['user_id'] ?? 'anonymous']);

$logger->debug('User authentication attempt', [
    'username' => 'admin_user',
    'timestamp' => time(),
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);

$logger->info('User successfully logged in', [
    'user_id' => 12345,
    'username' => 'admin_user',
    'role' => 'administrator'
]);

$logger->info('Inventory item added', [
    'item_id' => 'INV-001',
    'item_name' => 'Wireless Mouse',
    'quantity' => 50,
    'user_id' => 12345
]);

$logger->warning('Low inventory alert', [
    'item_id' => 'INV-002',
    'item_name' => 'USB Keyboard',
    'current_quantity' => 3,
    'minimum_threshold' => 10
]);

$logger->info('Inventory item updated', [
    'item_id' => 'INV-001',
    'field_updated' => 'quantity',
    'old_value' => 50,
    'new_value' => 45,
    'user_id' => 12345
]);

$logger->error('Database connection failed', [
    'error_code' => 'DB_CONN_001',
    'database_host' => 'localhost',
    'error_message' => 'Connection timeout'
]);

$logger->error('Invalid inventory operation', [
    'operation' => 'remove_item',
    'item_id' => 'INV-999',
    'error' => 'Item not found',
    'user_id' => 12345
]);

$logger->critical('Security breach detected', [
    'attack_type' => 'SQL_INJECTION',
    'target_endpoint' => '/api/inventory/search',
    'blocked' => true,
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);

$logger->info('User logged out', [
    'user_id' => 12345,
    'session_duration' => 1800
]);

$logger->rotateLog();

$logger->info('Application shutdown completed');
?>