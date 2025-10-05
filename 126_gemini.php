<?php
// config.php

<?php

// Define a secure log file path.
// Adjust this path for your production environment to be outside the web root.
define('APP_LOG_FILE', __DIR__ . '/../logs/app_errors.log');

// Function to log errors securely
if (!function_exists('log_error')) {
    function log_error(string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] $message" . PHP_EOL;
        // Ensure the log directory exists and is writable by the web server.
        $log_dir = dirname(APP_LOG_FILE);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        file_put_contents(APP_LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
    }
}

// Suppress display of errors in production and ensure errors are logged.
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
// Redirect PHP's native error logging to our custom log file.
ini_set('error_log', APP_LOG_FILE);

// Retrieve database credentials from environment variables.
// These variables must be set in your server configuration (e.g., Apache, Nginx, Docker, or .env file).
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_password = getenv('DB_PASSWORD');
$db_name = getenv('DB_NAME');

// Define constants for database credentials.
// If an environment variable is not set, log an error and define an empty string
// to prevent fatal errors and allow db_connect.php to handle the missing config gracefully.
if ($db_host === false || $db_host === '') {
    log_error('Configuration Error: DB_HOST environment variable not set or is empty.');
    define('DB_HOST', '');
} else {
    define('DB_HOST', $db_host);
}

if ($db_user === false || $db_user === '') {
    log_error('Configuration Error: DB_USER environment variable not set or is empty.');
    define('DB_USER', '');
} else {
    define('DB_USER', $db_user);
}

if ($db_password === false || $db_password === '') {
    // It's possible for a password to be empty, but we still log if the env var is missing.
    log_error('Configuration Error: DB_PASSWORD environment variable not set or is empty.');
    define('DB_PASSWORD', '');
} else {
    define('DB_PASSWORD', $db_password);
}

if ($db_name === false || $db_name === '') {
    log_error('Configuration Error: DB_NAME environment variable not set or is empty.');
    define('DB_NAME', '');
} else {
    define('DB_NAME', $db_name);
}

// Clear sensitive variables from memory after defining constants.
unset($db_host, $db_user, $db_password, $db_name);

?>

<?php
// db_connect.php

<?php

// Include the configuration file that defines database constants.
require_once __DIR__ . '/config.php';

// Configure MySQLi to throw exceptions on errors for robust error handling.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Initialize a global variable for the database connection.
// This allows other scripts to access the connection object (e.g., $GLOBALS['db_connection']).
$GLOBALS['db_connection'] = null;

try {
    // Check if any critical database constants are empty, indicating a configuration issue.
    if (empty(DB_HOST) || empty(DB_USER) || empty(DB_NAME)) {
        throw new Exception('Database connection failed: One or more essential configuration values (HOST, USER, or DB_NAME) are missing or empty. Please check application logs for details.');
    }

    // Attempt to establish a new database connection using the defined constants.
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    // Set the character set for the connection to utf8mb4 for full Unicode support.
    $conn->set_charset("utf8mb4");

    // Store the successful connection object in the global variable.
    $GLOBALS['db_connection'] = $conn;

} catch (mysqli_sql_exception $e) {
    // Catch specific MySQLi exceptions (e.g., connection refused, bad credentials).
    // Log the error but do not expose sensitive details to the user.
    log_error("Database Connection Error (MySQLi): " . $e->getMessage() . " Code: " . $e->getCode());
    // The $GLOBALS['db_connection'] will remain null, indicating a failed connection.

} catch (Exception $e) {
    // Catch general exceptions (e.g., configuration issues detected before mysqli call).
    log_error("Database Connection Setup Error: " . $e->getMessage());
    // The $GLOBALS['db_connection'] will remain null, indicating a failed connection.
}

?>