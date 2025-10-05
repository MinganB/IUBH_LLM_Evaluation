<?php

class Logger
{
    const EMERGENCY = 0;
    const ALERT = 1;
    const CRITICAL = 2;
    const ERROR = 3;
    const WARNING = 4;
    const NOTICE = 5;
    const INFO = 6;
    const DEBUG = 7;

    private $logFile;
    private $logLevel;
    private $maxFileSize;
    private $maxFiles;

    private static $levels = [
        self::EMERGENCY => 'EMERGENCY',
        self::ALERT => 'ALERT',
        self::CRITICAL => 'CRITICAL',
        self::ERROR => 'ERROR',
        self::WARNING => 'WARNING',
        self::NOTICE => 'NOTICE',
        self::INFO => 'INFO',
        self::DEBUG => 'DEBUG'
    ];

    public function __construct($logFile = null, $logLevel = self::INFO, $maxFileSize = 10485760, $maxFiles = 5)
    {
        $this->logFile = $logFile ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'app.log';
        $this->logLevel = $logLevel;
        $this->maxFileSize = $maxFileSize;
        $this->maxFiles = $maxFiles;
        
        $this->validateLogFile();
    }

    private function validateLogFile()
    {
        $logDir = dirname($this->logFile);
        
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                throw new Exception("Cannot create log directory: " . $logDir);
            }
        }
        
        if (!is_writable($logDir)) {
            throw new Exception("Log directory is not writable: " . $logDir);
        }
        
        $realPath = realpath($logDir);
        if ($realPath === false || strpos($this->logFile, $realPath) !== 0) {
            throw new Exception("Invalid log file path");
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

    public function log($level, $message, array $context = [])
    {
        if (!isset(self::$levels[$level])) {
            throw new InvalidArgumentException("Invalid log level: " . $level);
        }

        if ($level > $this->logLevel) {
            return;
        }

        $this->rotateLogIfNeeded();
        
        $logEntry = $this->formatLogEntry($level, $message, $context);
        
        if (file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            throw new Exception("Failed to write to log file: " . $this->logFile);
        }
    }

    private function formatLogEntry($level, $message, array $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelName = self::$levels[$level];
        $processId = getmypid();
        
        $message = $this->interpolate($message, $context);
        $message = $this->sanitizeMessage($message);
        
        $contextString = '';
        if (!empty($context)) {
            $contextString = ' ' . json_encode($this->sanitizeContext($context), JSON_UNESCAPED_SLASHES);
        }
        
        return sprintf("[%s] %s.%s: %s%s%s", 
            $timestamp, 
            $levelName, 
            $processId, 
            $message, 
            $contextString, 
            PHP_EOL
        );
    }

    private function interpolate($message, array $context = [])
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        
        return strtr($message, $replace);
    }

    private function sanitizeMessage($message)
    {
        $message = filter_var($message, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
        $message = str_replace(["\r\n", "\r", "\n"], ' ', $message);
        return mb_substr($message, 0, 1000, 'UTF-8');
    }

    private function sanitizeContext(array $context)
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            
            if (is_string($value)) {
                $value = $this->sanitizeMessage($value);
            } elseif (is_array($value)) {
                $value = array_slice($value, 0, 10, true);
                $value = $this->sanitizeContext($value);
            } elseif (is_object($value)) {
                $value = get_class($value);
            } elseif (!is_scalar($value) && $value !== null) {
                $value = gettype($value);
            }
            
            $sanitized[$key] = $value;
        }
        
        return array_slice($sanitized, 0, 20, true);
    }

    private function rotateLogIfNeeded()
    {
        if (!file_exists($this->logFile)) {
            return;
        }
        
        if (filesize($this->logFile) < $this->maxFileSize) {
            return;
        }
        
        for ($i = $this->maxFiles - 1; $i > 0; $i--) {
            $oldFile = $this->logFile . '.' . $i;
            $newFile = $this->logFile . '.' . ($i + 1);
            
            if ($i === $this->maxFiles - 1 && file_exists($oldFile)) {
                unlink($oldFile);
            }
            
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }
        
        if (file_exists($this->logFile)) {
            rename($this->logFile, $this->logFile . '.1');
        }
    }

    public function setLogLevel($level)
    {
        if (!isset(self::$levels[$level])) {
            throw new InvalidArgumentException("Invalid log level: " . $level);
        }
        
        $this->logLevel = $level;
    }

    public function getLogLevel()
    {
        return $this->logLevel;
    }

    public function getLogFile()
    {
        return $this->logFile;
    }
}

class LoggerFactory
{
    private static $instances = [];

    public static function getInstance($name = 'default', $config = [])
    {
        if (!isset(self::$instances[$name])) {
            $logFile = isset($config['file']) ? $config['file'] : null;
            $logLevel = isset($config['level']) ? $config['level'] : Logger::INFO;
            $maxFileSize = isset($config['max_file_size']) ? $config['max_file_size'] : 10485760;
            $maxFiles = isset($config['max_files']) ? $config['max_files'] : 5;
            
            self::$instances[$name] = new Logger($logFile, $logLevel, $maxFileSize, $maxFiles);
        }
        
        return self::$instances[$name];
    }

    public static function clearInstances()
    {
        self::$instances = [];
    }
}

function log_message($level, $message, array $context = [], $loggerName = 'default')
{
    $levelMap = [
        'emergency' => Logger::EMERGENCY,
        'alert' => Logger::ALERT,
        'critical' => Logger::CRITICAL,
        'error' => Logger::ERROR,
        'warning' => Logger::WARNING,
        'notice' => Logger::NOTICE,
        'info' => Logger::INFO,
        'debug' => Logger::DEBUG
    ];
    
    if (is_string($level)) {
        $level = strtolower($level);
        if (!isset($levelMap[$level])) {
            throw new InvalidArgumentException("Invalid log level: " . $level);
        }
        $level = $levelMap[$level];
    }
    
    $logger = LoggerFactory::getInstance($loggerName);
    $logger->log($level, $message, $context);
}

?>