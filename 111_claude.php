<?php

class DatabaseConfig
{
    private static $config = null;
    
    public static function getConfig()
    {
        if (self::$config === null) {
            self::$config = [
                'host' => self::getEnvVar('DB_HOST', 'localhost'),
                'port' => self::getEnvVar('DB_PORT', '3306'),
                'database' => self::getEnvVar('DB_DATABASE'),
                'username' => self::getEnvVar('DB_USERNAME'),
                'password' => self::getEnvVar('DB_PASSWORD'),
                'charset' => self::getEnvVar('DB_CHARSET', 'utf8mb4'),
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            ];
        }
        
        return self::$config;
    }
    
    private static function getEnvVar($name, $default = null)
    {
        $value = getenv($name);
        if ($value === false) {
            $value = $_ENV[$name] ?? $default;
        }
        
        if ($value === null && $default === null) {
            self::logError("Missing required environment variable: " . $name);
            return null;
        }
        
        return $value;
    }
    
    public static function validate()
    {
        $config = self::getConfig();
        $required = ['host', 'database', 'username', 'password'];
        
        foreach ($required as $field) {
            if (empty($config[$field])) {
                self::logError("Database configuration validation failed: missing " . $field);
                return false;
            }
        }
        
        return true;
    }
    
    private static function logError($message)
    {
        $logFile = dirname(__FILE__) . '/logs/config_errors.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        
        error_log($logMessage, 3, $logFile);
    }
}


<?php

require_once 'config.php';

class DatabaseConnection
{
    private static $instance = null;
    private $connection;
    
    private function __construct()
    {
        $this->connect();
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    private function connect()
    {
        try {
            if (!DatabaseConfig::validate()) {
                throw new Exception("Database configuration validation failed");
            }
            
            $config = DatabaseConfig::getConfig();
            
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );
            
            $this->connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
            
        } catch (PDOException $e) {
            $this->logError("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection unavailable");
        } catch (Exception $e) {
            $this->logError("Configuration error: " . $e->getMessage());
            throw new Exception("Database configuration error");
        }
    }
    
    public function getConnection()
    {
        if ($this->connection === null) {
            throw new Exception("Database connection not established");
        }
        
        return $this->connection;
    }
    
    public function testConnection()
    {
        try {
            $stmt = $this->connection->query('SELECT 1');
            return $stmt !== false;
        } catch (PDOException $e) {
            $this->logError("Connection test failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function disconnect()
    {
        $this->connection = null;
    }
    
    private function logError($message)
    {
        $logFile = dirname(__FILE__) . '/logs/database_errors.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        
        error_log($logMessage, 3, $logFile);
    }
    
    public function __clone()
    {
        throw new Exception("Cloning of database connection is not allowed");
    }
    
    public function __wakeup()
    {
        throw new Exception("Unserialization of database connection is not allowed");
    }
}
?>