<?php

class Logger
{
    private $logDirectory;
    private $maxFileSize;
    private $maxFiles;
    private $dateFormat;
    
    const EMERGENCY = 'EMERGENCY';
    const ALERT = 'ALERT';
    const CRITICAL = 'CRITICAL';
    const ERROR = 'ERROR';
    const WARNING = 'WARNING';
    const NOTICE = 'NOTICE';
    const INFO = 'INFO';
    const DEBUG = 'DEBUG';
    
    private $logLevels = [
        self::EMERGENCY => 0,
        self::ALERT => 1,
        self::CRITICAL => 2,
        self::ERROR => 3,
        self::WARNING => 4,
        self::NOTICE => 5,
        self::INFO => 6,
        self::DEBUG => 7
    ];
    
    public function __construct($logDirectory = '/var/log/inventory', $maxFileSize = 10485760, $maxFiles = 10)
    {
        $this->logDirectory = rtrim($logDirectory, '/');
        $this->maxFileSize = $maxFileSize;
        $this->maxFiles = $maxFiles;
        $this->dateFormat = 'Y-m-d H:i:s';
        
        $this->createLogDirectory();
    }
    
    private function createLogDirectory()
    {
        if (!is_dir($this->logDirectory)) {
            if (!mkdir($this->logDirectory, 0750, true)) {
                throw new Exception('Failed to create log directory: ' . $this->logDirectory);
            }
        }
        
        if (!is_writable($this->logDirectory)) {
            throw new Exception('Log directory is not writable: ' . $this->logDirectory);
        }
    }
    
    public function log($level, $message, array $context = [])
    {
        $level = strtoupper($level);
        
        if (!isset($this->logLevels[$level])) {
            throw new InvalidArgumentException('Invalid log level: ' . $level);
        }
        
        $sanitizedMessage = $this->sanitizeMessage($message);
        $sanitizedContext = $this->sanitizeContext($context);
        
        $logEntry = $this->formatLogEntry($level, $sanitizedMessage, $sanitizedContext);
        
        $this->writeToFile($logEntry);
    }
    
    private function sanitizeMessage($message)
    {
        $message = strip_tags($message);
        $message = preg_replace('/[\x00-\x1F\x7F]/', '', $message);
        return trim($message);
    }
    
    private function sanitizeContext(array $context)
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
            } elseif (is_array($value) || is_object($value)) {
                $sanitized[$cleanKey] = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $sanitized[$cleanKey] = 'unsupported_type';
            }
        }
        return $sanitized;
    }
    
    private function formatLogEntry($level, $message, array $context)
    {
        $timestamp = date($this->dateFormat);
        $pid = getmypid();
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'CLI';
        
        $remoteAddr = filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : 'UNKNOWN';
        $userAgent = $this->sanitizeMessage($userAgent);
        $requestUri = $this->sanitizeMessage($requestUri);
        
        $contextString = '';
        if (!empty($context)) {
            $contextString = ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        
        return sprintf(
            "[%s] %s PID:%d IP:%s URI:%s UA:%s | %s%s\n",
            $timestamp,
            $level,
            $pid,
            $remoteAddr,
            $requestUri,
            $userAgent,
            $message,
            $contextString
        );
    }
    
    private function writeToFile($logEntry)
    {
        $filename = $this->logDirectory . '/inventory_' . date('Y-m-d') . '.log';
        
        if (file_exists($filename) && filesize($filename) > $this->maxFileSize) {
            $this->rotateLogFile($filename);
        }
        
        if (file_put_contents($filename, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            throw new Exception('Failed to write to log file: ' . $filename);
        }
        
        if (file_exists($filename)) {
            chmod($filename, 0640);
        }
    }
    
    private function rotateLogFile($filename)
    {
        $pathInfo = pathinfo($filename);
        $baseName = $pathInfo['dirname'] . '/' . $pathInfo['filename'];
        $extension = $pathInfo['extension'];
        
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $baseName . '.' . $i . '.' . $extension;
            $newFile = $baseName . '.' . ($i + 1) . '.' . $extension;
            
            if (file_exists($oldFile)) {
                if ($i + 1 > $this->maxFiles) {
                    unlink($oldFile);
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }
        
        if (file_exists($filename)) {
            rename($filename, $baseName . '.1.' . $extension);
        }
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
    
    public function logUserAction($userId, $action, $resourceType, $resourceId = null, array $additionalData = [])
    {
        $context = array_merge([
            'user_id' => $userId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'session_id' => session_id()
        ], $additionalData);
        
        $message = sprintf(
            'User action: %s performed %s on %s%s',
            $userId,
            $action,
            $resourceType,
            $resourceId ? " (ID: $resourceId)" : ''
        );
        
        $this->info($message, $context);
    }
    
    public function logSystemEvent($eventType, $message, array $context = [])
    {
        $context['event_type'] = $eventType;
        $context['system_event'] = true;
        
        $this->info("System event - $eventType: $message", $context);
    }
    
    public function logSecurityEvent($eventType, $message, array $context = [])
    {
        $context['security_event'] = true;
        $context['event_type'] = $eventType;
        
        $this->warning("Security event - $eventType: $message", $context);
    }
    
    public function logInventoryChange($itemId, $changeType, $oldValue, $newValue, $userId = null)
    {
        $context = [
            'item_id' => $itemId,
            'change_type' => $changeType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'user_id' => $userId,
            'inventory_change' => true
        ];
        
        $message = sprintf(
            'Inventory change: Item %s - %s changed from %s to %s',
            $itemId,
            $changeType,
            $oldValue,
            $newValue
        );
        
        $this->info($message, $context);
    }
}


<?php

class LogManager
{
    private static $instance = null;
    private $logger;
    private $config;
    
    private function __construct()
    {
        $this->loadConfig();
        $this->logger = new Logger(
            $this->config['log_directory'],
            $this->config['max_file_size'],
            $this->config['max_files']
        );
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadConfig()
    {
        $defaultConfig = [
            'log_directory' => dirname(__DIR__) . '/logs',
            'max_file_size' => 10485760,
            'max_files' => 10
        ];
        
        $configFile = dirname(__DIR__) . '/config/logging.php';
        if (file_exists($configFile)) {
            $userConfig = include $configFile;
            $this->config = array_merge($defaultConfig, $userConfig);
        } else {
            $this->config = $defaultConfig;
        }
    }
    
    public function getLogger()
    {
        return $this->logger;
    }
    
    public function log($level, $message, array $context = [])
    {
        return $this->logger->log($level, $message, $context);
    }
    
    public function emergency($message, array $context = [])
    {
        return $this->logger->emergency($message, $context);
    }
    
    public function alert($message, array $context = [])
    {
        return $this->logger->alert($message, $context);
    }
    
    public function critical($message, array $context = [])
    {
        return $this->logger->critical($message, $context);
    }
    
    public function error($message, array $context = [])
    {
        return $this->logger->error($message, $context);
    }
    
    public function warning($message, array $context = [])
    {
        return $this->logger->warning($message, $context);
    }
    
    public function notice($message, array $context = [])
    {
        return $this->logger->notice($message, $context);
    }
    
    public function info($message, array $context = [])
    {
        return $this->logger->info($message, $context);
    }
    
    public function debug($message, array $context = [])
    {
        return $this->logger->debug($message, $context);
    }
    
    public function logUserAction($userId, $action, $resourceType, $resourceId = null, array $additionalData = [])
    {
        return $this->logger->logUserAction($userId, $action, $resourceType, $resourceId, $additionalData);
    }
    
    public function logSystemEvent($eventType, $message, array $context = [])
    {
        return $this->logger->logSystemEvent($eventType, $message, $context);
    }
    
    public function logSecurityEvent($eventType, $message, array $context = [])
    {
        return $this->logger->logSecurityEvent($eventType, $message, $context);
    }
    
    public function logInventoryChange($itemId, $changeType, $oldValue, $newValue, $userId = null)
    {
        return $this->logger->logInventoryChange($itemId, $changeType, $oldValue, $newValue, $userId);
    }
}


<?php

function getLogger()
{
    return LogManager::getInstance();
}

function logInfo($message, array $context = [])
{
    LogManager::getInstance()->info($message, $context);
}

function logError($message, array $context = [])
{
    LogManager::getInstance()->error($message, $context);
}

function logDebug($message, array $context = [])
{
    LogManager::getInstance()->debug($message, $context);
}

function logWarning($message, array $context = [])
{
    LogManager::getInstance()->warning($message, $context);
}

function logUserAction($userId, $action, $resourceType, $resourceId = null, array $additionalData = [])
{
    LogManager::getInstance()->logUserAction($userId, $action, $resourceType, $resourceId, $additionalData);
}

function logSystemEvent($eventType, $message, array $context = [])
{
    LogManager::getInstance()->logSystemEvent($eventType, $message, $context);
}

function logSecurityEvent($eventType, $message, array $context = [])
{
    LogManager::getInstance()->logSecurityEvent($eventType, $message, $context);
}

function logInventoryChange($itemId, $changeType, $oldValue, $newValue, $userId = null)
{
    LogManager::getInstance()->logInventoryChange($itemId, $changeType, $oldValue, $newValue, $userId);
}
?>