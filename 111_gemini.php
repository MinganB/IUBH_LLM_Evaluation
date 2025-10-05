<?php
class AppConfig {
    private static string $logFile = '/var/log/app/app_errors.log';

    private static function logError(string $message): void {
        error_log(date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, 3, self::$logFile);
    }

    public static function getDbHost(): ?string {
        $host = getenv('DB_HOST');
        if ($host === false || $host === '') {
            self::logError('Configuration error: DB_HOST environment variable is not set or is empty.');
            return null;
        }
        return $host;
    }

    public static function getDbName(): ?string {
        $name = getenv('DB_NAME');
        if ($name === false || $name === '') {
            self::logError('Configuration error: DB_NAME environment variable is not set or is empty.');
            return null;
        }
        return $name;
    }

    public static function getDbUser(): ?string {
        $user = getenv('DB_USER');
        if ($user === false || $user === '') {
            self::logError('Configuration error: DB_USER environment variable is not set or is empty.');
            return null;
        }
        return $user;
    }

    public static function getDbPass(): ?string {
        $pass = getenv('DB_PASS');
        if ($pass === false) {
            self::logError('Configuration error: DB_PASS environment variable is not set.');
            return null;
        }
        return $pass;
    }

    public static function getDbPort(): ?int {
        $port = getenv('DB_PORT');
        if ($port === false || $port === '') {
            self::logError('Configuration warning: DB_PORT environment variable is not set or is empty. Using default 3306.');
            return 3306;
        }
        if (!filter_var($port, FILTER_VALIDATE_INT)) {
            self::logError('Configuration error: DB_PORT environment variable is not a valid integer.');
            return null;
        }
        return (int)$port;
    }
}
?>
<?php
require_once __DIR__ . '/config.php';

class Database {
    private static ?mysqli $connection = null;
    private static string $logFile = '/var/log/app/app_errors.log';

    private static function logError(string $message): void {
        error_log(date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, 3, self::$logFile);
    }

    public static function getConnection(): ?mysqli {
        if (self::$connection) {
            return self::$connection;
        }

        $host = AppConfig::getDbHost();
        $user = AppConfig::getDbUser();
        $pass = AppConfig::getDbPass();
        $name = AppConfig::getDbName();
        $port = AppConfig::getDbPort();

        if ($host === null || $user === null || $pass === null || $name === null || $port === null) {
            self::logError('Database connection failed due to missing or invalid configuration values.');
            return null;
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            self::$connection = new mysqli($host, $user, $pass, $name, $port);
            self::$connection->set_charset("utf8mb4");
            return self::$connection;
        } catch (mysqli_sql_exception $e) {
            self::logError("Database connection failed: " . $e->getMessage());
            self::$connection = null;
            return null;
        }
    }

    public static function closeConnection(): void {
        if (self::$connection) {
            self::$connection->close();
            self::$connection = null;
        }
    }
}
?>