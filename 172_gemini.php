<?php // session_handler.php

session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    session_regenerate_id(true);

    $_SESSION['loggedin'] = true;
    $_SESSION['user_id'] = 123;
    $_SESSION['username'] = 'demo_user';

    header('Location: dashboard.php');
    exit();
} else {
    header('Location: dashboard.php');
    exit();
}
?>