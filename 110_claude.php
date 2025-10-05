<?php
return [
    'database' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'name' => $_ENV['DB_NAME'] ?? '',
        'username' => $_ENV['DB_USERNAME'] ?? '',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci'
        ]
    ]
];


<?php
class DatabaseConnection
{
    private static $instance = null;
    private $connection;
    private $config;

    private function __construct()
    {
        $this->config = require_once __DIR__ . '/config.php';
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
            $dbConfig = $this->config['database'];
            
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['name'],
                $dbConfig['charset']
            );

            $this->connection = new PDO(
                $dsn,
                $dbConfig['username'],
                $dbConfig['password'],
                $dbConfig['options']
            );
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }

    public function getConnection()
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Query execution failed: ' . $e->getMessage());
            throw new Exception('Query execution failed');
        }
    }

    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    public function commit()
    {
        return $this->connection->commit();
    }

    public function rollback()
    {
        return $this->connection->rollback();
    }

    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    private function __clone() {}

    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton');
    }
}


<?php
class ConfigValidator
{
    public static function validateDatabaseConfig()
    {
        $requiredEnvVars = ['DB_HOST', 'DB_NAME', 'DB_USERNAME', 'DB_PASSWORD'];
        
        foreach ($requiredEnvVars as $var) {
            if (empty($_ENV[$var])) {
                throw new Exception("Required environment variable {$var} is not set");
            }
        }

        if (!filter_var($_ENV['DB_PORT'] ?? 3306, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535]
        ])) {
            throw new Exception("Invalid database port");
        }

        return true;
    }
}


<?php
require_once 'DatabaseConnection.php';
require_once 'ConfigValidator.php';

try {
    ConfigValidator::validateDatabaseConfig();
    
    $db = DatabaseConnection::getInstance();
    $connection = $db->getConnection();
    
    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch();
    
    if ($result['test'] === 1) {
        echo "Database connection successful";
    }
    
} catch (Exception $e) {
    error_log('Application error: ' . $e->getMessage());
    http_response_code(500);
    echo "Application error occurred";
}
?>