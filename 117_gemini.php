<?php
// File: classes/AppConfig.php

class AppConfig
{
    private static $config = [];
    private static $logFile = __DIR__ . '/../logs/app_errors.log';

    private function __construct() {}
    private function __clone() {}

    public static function get(string $key, $default = null)
    {
        if (empty(self::$config)) {
            self::loadConfig();
        }

        if (array_key_exists($key, self::$config)) {
            return self::$config[$key];
        }

        if ($default === null && self::isCriticalKey($key)) {
            self::logError("Critical configuration key '{$key}' is missing.");
            return null;
        }

        return $default;
    }

    private static function loadConfig()
    {
        self::$config['DB_HOST'] = self::getEnv('DB_HOST', 'localhost');
        self::$config['DB_NAME'] = self::getEnv('DB_NAME');
        self::$config['DB_USER'] = self::getEnv('DB_USER');
        self::$config['DB_PASS'] = self::getEnv('DB_PASS');
        self::$config['DB_PORT'] = (int) self::getEnv('DB_PORT', 3306);
    }

    private static function getEnv(string $envKey, $defaultValue = null)
    {
        $value = getenv($envKey);
        if ($value === false || $value === null || $value === '') {
            if ($defaultValue === null) {
                self::logError("Environment variable '{$envKey}' is not set.");
            }
            return $defaultValue;
        }
        return $value;
    }

    private static function logError(string $message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] ERROR: {$message}" . PHP_EOL;
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    private static function isCriticalKey(string $key): bool
    {
        $criticalKeys = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        return in_array($key, $criticalKeys);
    }
}

<?php
// File: classes/Database.php

require_once __DIR__ . '/AppConfig.php';

class Database
{
    private static $instance = null;
    private $connection;
    private static $logFile = __DIR__ . '/../logs/app_errors.log';

    private function __construct()
    {
        $dbHost = AppConfig::get('DB_HOST');
        $dbName = AppConfig::get('DB_NAME');
        $dbUser = AppConfig::get('DB_USER');
        $dbPass = AppConfig::get('DB_PASS');
        $dbPort = AppConfig::get('DB_PORT');

        if (!$dbHost || !$dbName || !$dbUser || !$dbPass) {
            self::logError('Failed to retrieve essential database configuration.');
            $this->connection = null;
            return;
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $this->connection = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
            $this->connection->set_charset("utf8mb4");
        } catch (mysqli_sql_exception $e) {
            self::logError('MySQL connection error: ' . $e->getMessage());
            $this->connection = null;
        }
    }

    private function __clone() {}

    public static function getInstance(): ?Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): ?mysqli
    {
        return $this->connection;
    }

    private static function logError(string $message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] ERROR: {$message}" . PHP_EOL;
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

<?php
// File: public/index.php (Example usage)

require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance();
$conn = $db ? $db->getConnection() : null;

if ($conn) {
    try {
        $result = $conn->query("SELECT id, title, content FROM posts ORDER BY created_at DESC LIMIT 5");
        if ($result) {
            $posts = $result->fetch_all(MYSQLI_ASSOC);
            echo "<h1>Latest Blog Posts</h1>";
            foreach ($posts as $post) {
                echo "<h2>" . htmlspecialchars($post['title']) . "</h2>";
                echo "<p>" . htmlspecialchars(substr($post['content'], 0, 150)) . "...</p>";
            }
            $result->free();
        } else {
            error_log("Database query failed in index.php: " . $conn->error);
            echo "An error occurred while fetching posts.";
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Database query exception in index.php: " . $e->getMessage());
        echo "An unexpected database error occurred.";
    }
} else {
    echo "<h1>Application Error</h1>";
    echo "<p>We are currently experiencing technical difficulties. Please try again later.</p>";
}
?>