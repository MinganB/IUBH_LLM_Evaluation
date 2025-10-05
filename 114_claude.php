<?php

class DatabaseConfig
{
    private static $instance = null;
    private $config = [];
    
    private function __construct()
    {
        $this->loadConfig();
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
        $requiredEnvVars = [
            'DB_HOST',
            'DB_PORT',
            'DB_NAME',
            'DB_USERNAME',
            'DB_PASSWORD',
            'DB_CHARSET'
        ];
        
        foreach ($requiredEnvVars as $var) {
            $value = getenv($var);
            if ($value === false || empty($value)) {
                $this->logError("Missing or empty environment variable: " . $var);
                throw new Exception("Configuration error occurred");
            }
            $this->config[$var] = $value;
        }
    }
    
    public function get($key)
    {
        if (!isset($this->config[$key])) {
            $this->logError("Attempted to access undefined configuration key: " . $key);
            return null;
        }
        return $this->config[$key];
    }
    
    public function getAll()
    {
        return $this->config;
    }
    
    private function logError($message)
    {
        $logDir = '/var/log/blog-app';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/config-errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function __clone() {}
    private function __wakeup() {}
}


<?php

class DatabaseConnection
{
    private static $instance = null;
    private $connection = null;
    private $config;
    
    private function __construct()
    {
        try {
            $this->config = DatabaseConfig::getInstance();
            $this->connect();
        } catch (Exception $e) {
            $this->logError("Database configuration initialization failed");
            throw new Exception("Database connection cannot be established");
        }
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
            $host = $this->config->get('DB_HOST');
            $port = $this->config->get('DB_PORT');
            $dbname = $this->config->get('DB_NAME');
            $username = $this->config->get('DB_USERNAME');
            $password = $this->config->get('DB_PASSWORD');
            $charset = $this->config->get('DB_CHARSET');
            
            if (!$host || !$port || !$dbname || !$username || !$password || !$charset) {
                throw new Exception("Invalid database configuration");
            }
            
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8_unicode_ci"
            ];
            
            $this->connection = new PDO($dsn, $username, $password, $options);
            
        } catch (PDOException $e) {
            $this->logError("Database PDO connection failed: " . $e->getCode());
            throw new Exception("Database connection failed");
        } catch (Exception $e) {
            $this->logError("Database connection error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public function getConnection()
    {
        if ($this->connection === null) {
            throw new Exception("Database connection not available");
        }
        return $this->connection;
    }
    
    public function testConnection()
    {
        try {
            $stmt = $this->connection->query('SELECT 1');
            return $stmt !== false;
        } catch (PDOException $e) {
            $this->logError("Database connection test failed: " . $e->getCode());
            return false;
        }
    }
    
    public function closeConnection()
    {
        $this->connection = null;
    }
    
    private function logError($message)
    {
        $logDir = '/var/log/blog-app';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/database-errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function __clone() {}
    private function __wakeup() {}
    
    public function __destruct()
    {
        $this->closeConnection();
    }
}


<?php

class BlogDatabase
{
    private $db;
    
    public function __construct()
    {
        try {
            $dbConnection = DatabaseConnection::getInstance();
            $this->db = $dbConnection->getConnection();
        } catch (Exception $e) {
            $this->logError("Failed to initialize blog database: " . $e->getMessage());
            throw new Exception("Blog database initialization failed");
        }
    }
    
    public function getAllPosts($limit = 10, $offset = 0)
    {
        try {
            $stmt = $this->db->prepare("SELECT id, title, content, created_at, updated_at FROM blog_posts ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $stmt->bindParam(1, $limit, PDO::PARAM_INT);
            $stmt->bindParam(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError("Failed to fetch blog posts: " . $e->getCode());
            return [];
        }
    }
    
    public function getPostById($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT id, title, content, created_at, updated_at FROM blog_posts WHERE id = ?");
            $stmt->bindParam(1, $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            $this->logError("Failed to fetch blog post by ID: " . $e->getCode());
            return null;
        }
    }
    
    public function createPost($title, $content)
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO blog_posts (title, content, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $stmt->bindParam(1, $title, PDO::PARAM_STR);
            $stmt->bindParam(2, $content, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            $this->logError("Failed to create blog post: " . $e->getCode());
            return false;
        }
    }
    
    public function updatePost($id, $title, $content)
    {
        try {
            $stmt = $this->db->prepare("UPDATE blog_posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bindParam(1, $title, PDO::PARAM_STR);
            $stmt->bindParam(2, $content, PDO::PARAM_STR);
            $stmt->bindParam(3, $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            $this->logError("Failed to update blog post: " . $e->getCode());
            return false;
        }
    }
    
    public function deletePost($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM blog_posts WHERE id = ?");
            $stmt->bindParam(1, $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            $this->logError("Failed to delete blog post: " . $e->getCode());
            return false;
        }
    }
    
    private function logError($message)
    {
        $logDir = '/var/log/blog-app';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/blog-errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}


<?php

require_once 'DatabaseConfig.php';
require_once 'DatabaseConnection.php';
require_once 'BlogDatabase.php';

try {
    $blogDb = new BlogDatabase();
    
    $posts = $blogDb->getAllPosts(5);
    
    if (!empty($posts)) {
        echo "Successfully connected to database and retrieved blog posts.";
    } else {
        echo "Database connection established but no posts found.";
    }
    
} catch (Exception $e) {
    echo "Application initialization failed. Please check system logs.";
}
?>