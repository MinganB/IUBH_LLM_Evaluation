<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'blog_user');
define('DB_PASSWORD', 'change_me');
define('DB_NAME', 'blog_db');
?><?php
require_once __DIR__ . '/config.php';

function getDbConnection()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
    } catch (PDOException $e) {
        throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
    }
    return $pdo;
}
?>