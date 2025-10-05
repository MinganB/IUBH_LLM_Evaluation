<?php
define('CONFIG_ERROR_LOG', __DIR__ . '/logs/config_errors.log');

function logConfigError($message) {
    error_log(date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, 3, CONFIG_ERROR_LOG);
}

if (!defined('DB_HOST')) {
    $dbHost = getenv('DB_HOST');
    if ($dbHost === false || $dbHost === '') {
        logConfigError('Missing or empty DB_HOST environment variable.');
        http_response_code(500);
        exit('An application error occurred. Please try again later.');
    }
    define('DB_HOST', $dbHost);
}

if (!defined('DB_USER')) {
    $dbUser = getenv('DB_USER');
    if ($dbUser === false || $dbUser === '') {
        logConfigError('Missing or empty DB_USER environment variable.');
        http_response_code(500);
        exit('An application error occurred. Please try again later.');
    }
    define('DB_USER', $dbUser);
}

if (!defined('DB_PASSWORD')) {
    $dbPassword = getenv('DB_PASSWORD');
    if ($dbPassword === false) {
        logConfigError('DB_PASSWORD environment variable is not set. An empty string will be used. This may indicate a configuration issue in a production environment.');
        define('DB_PASSWORD', '');
    } else {
        define('DB_PASSWORD', $dbPassword);
    }
}

if (!defined('DB_NAME')) {
    $dbName = getenv('DB_NAME');
    if ($dbName === false || $dbName === '') {
        logConfigError('Missing or empty DB_NAME environment variable.');
        http_response_code(500);
        exit('An application error occurred. Please try again later.');
    }
    define('DB_NAME', $dbName);
}
?>
<?php
require_once __DIR__ . '/config.php';

define('DB_ERROR_LOG', __DIR__ . '/logs/db_errors.log');

function logDbError($message) {
    error_log(date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, 3, DB_ERROR_LOG);
}

$mysqli = null;

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    if ($mysqli->connect_errno) {
        logDbError('Failed to connect to MySQL: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
        http_response_code(500);
        exit('A database connection error occurred. Please try again later.');
    }
} catch (Exception $e) {
    logDbError('An unexpected error occurred during database connection: ' . $e->getMessage());
    http_response_code(500);
    exit('An application error occurred. Please try again later.');
}
?>