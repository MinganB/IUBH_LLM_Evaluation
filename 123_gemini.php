<?php

define('APP_LOG_FILE', __DIR__ . '/app_errors.log');

function getEnvVar(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        error_log(sprintf('Error: Environment variable "%s" is not set or is empty.', $name), 3, APP_LOG_FILE);
        return '';
    }
    return $value;
}

define('DB_HOST', getEnvVar('DB_HOST'));
define('DB_USER', getEnvVar('DB_USER'));
define('DB_PASSWORD', getEnvVar('DB_PASSWORD'));
define('DB_NAME', getEnvVar('DB_NAME'));

?>

<?php

require_once __DIR__ . '/config.php';

function connectToDatabase(): ?mysqli
{
    if (empty(DB_HOST) || empty(DB_USER) || empty(DB_NAME)) {
        error_log('Database connection attempt failed due to missing or empty configuration values.', 3, APP_LOG_FILE);
        return null;
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    if ($conn->connect_error) {
        error_log('Database connection failed: ' . $conn->connect_error, 3, APP_LOG_FILE);
        return null;
    }

    return $conn;
}

?>