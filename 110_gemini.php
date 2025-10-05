<?php

return [
    'db_host' => 'localhost',
    'db_user' => 'your_db_username',
    'db_pass' => 'your_db_password',
    'db_name' => 'your_db_database',
    'db_port' => 3306,
    'db_charset' => 'utf8mb4'
];

<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function getDbConnection() {
    $config = require __DIR__ . '/config.php';

    try {
        $mysqli = new mysqli(
            $config['db_host'],
            $config['db_user'],
            $config['db_pass'],
            $config['db_name'],
            $config['db_port']
        );

        $mysqli->set_charset($config['db_charset']);

        return $mysqli;
    } catch (mysqli_sql_exception $e) {
        error_log("Failed to connect to MySQL: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        exit('A database connection error occurred. Please try again later.');
    }
}
?>