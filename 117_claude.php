<?php

class DatabaseConfig
{
    private static $instance = null;
    private $host;
    private $database;
    private $username;
    private $password;
    private $port;
    private $charset;

    private function __construct()
    {
        $this->loadConfiguration();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadConfiguration()
    {
        $this->host = $this->getEnvVar('DB_HOST', 'localhost');
        $this->database = $this->getEnvVar('DB_DATABASE');
        $this->username = $this->getEnvVar('DB_USERNAME');
        $this->password = $this->getEnvVar('DB_PASSWORD');
        $this->port = $this->getEnvVar('DB_PORT', '3306');
        $this->charset = $this->getEnvVar('DB_CHARSET', 'utf8mb4');
    }

    private function getEnvVar($key, $default = null)
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }
        
        if ($value === null && $default === null) {
            $this->logError("Missing required environment variable: {$key}");
            throw new Exception("Configuration error occurred");
        }
        
        return $value;
    }

    private function logError($message)
    {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/config_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] CONFIG ERROR: {$message}" . PHP_EOL;
        
        error_log($logMessage, 3, $logFile);
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getDatabase()
    {
        return $this->database;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getCharset()
    {
        return $this->charset;
    }

    public function getDSN()
    {
        return "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset={$this->charset}";
    }

    public function validate()
    {
        $required = ['host', 'database', 'username', 'password'];
        foreach ($required as $field) {
            if (empty($this->$field)) {
                $this->logError("Invalid configuration: missing {$field}");
                return false;
            }
        }
        return true;
    }
}


<?php

class DatabaseConnection
{
    private static $instance = null;
    private $pdo;
    private $config;

    private function __construct()
    {
        $this->config = DatabaseConfig::getInstance();
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
            if (!$this->config->validate()) {
                throw new Exception("Database configuration validation failed");
            }

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $this->config->getCharset(),
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_TIMEOUT => 30
            ];

            $this->pdo = new PDO(
                $this->config->getDSN(),
                $this->config->getUsername(),
                $this->config->getPassword(),
                $options
            );

        } catch (PDOException $e) {
            $this->logError("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection unavailable");
        } catch (Exception $e) {
            $this->logError("Configuration error: " . $e->getMessage());
            throw new Exception("Database configuration error");
        }
    }

    private function logError($message)
    {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/database_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] DB ERROR: {$message}" . PHP_EOL;
        
        error_log($logMessage, 3, $logFile);
    }

    public function getConnection()
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }

    public function testConnection()
    {
        try {
            $stmt = $this->pdo->query('SELECT 1');
            return $stmt !== false;
        } catch (PDOException $e) {
            $this->logError("Connection test failed: " . $e->getMessage());
            return false;
        }
    }

    public function closeConnection()
    {
        $this->pdo = null;
    }

    public function __destruct()
    {
        $this->closeConnection();
    }
}


<?php

require_once __DIR__ . '/../classes/DatabaseConfig.php';
require_once __DIR__ . '/../classes/DatabaseConnection.php';

class BlogDatabase
{
    private $connection;
    private $pdo;

    public function __construct()
    {
        try {
            $this->connection = DatabaseConnection::getInstance();
            $this->pdo = $this->connection->getConnection();
        } catch (Exception $e) {
            $this->logError("Failed to initialize blog database: " . $e->getMessage());
            throw new Exception("Database initialization failed");
        }
    }

    private function logError($message)
    {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/blog_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] BLOG DB ERROR: {$message}" . PHP_EOL;
        
        error_log($logMessage, 3, $logFile);
    }

    public function getAllPosts($limit = 10, $offset = 0)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT id, title, content, author, created_at, updated_at FROM blog_posts ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError("Failed to retrieve posts: " . $e->getMessage());
            return [];
        }
    }

    public function getPostById($id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT id, title, content, author, created_at, updated_at FROM blog_posts WHERE id = :id");
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            $this->logError("Failed to retrieve post ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function createPost($title, $content, $author)
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO blog_posts (title, content, author, created_at, updated_at) VALUES (:title, :content, :author, NOW(), NOW())");
            $stmt->bindValue(':title', $title, PDO::PARAM_STR);
            $stmt->bindValue(':content', $content, PDO::PARAM_STR);
            $stmt->bindValue(':author', $author, PDO::PARAM_STR);
            $stmt->execute();
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->logError("Failed to create post: " . $e->getMessage());
            return false;
        }
    }

    public function updatePost($id, $title, $content)
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE blog_posts SET title = :title, content = :content, updated_at = NOW() WHERE id = :id");
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $stmt->bindValue(':title', $title, PDO::PARAM_STR);
            $stmt->bindValue(':content', $content, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError("Failed to update post ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function deletePost($id)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM blog_posts WHERE id = :id");
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->logError("Failed to delete post ID {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function getPostCount()
    {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM blog_posts");
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->logError("Failed to get post count: " . $e->getMessage());
            return 0;
        }
    }
}
?>