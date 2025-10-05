<?php
ini_set('session.cookie_httponly', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
ini_set('session.use_strict_mode', 1);
session_start();
session_regenerate_id(true);
$_SESSION['user_id'] = 123;
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Login successful.']);
exit();


<?php
ini_set('session.cookie_httponly', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
ini_set('session.use_strict_mode', 1);
session_start();
if (isset($_SESSION['user_id'])) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Dashboard</title></head><body><h1>Welcome to the Dashboard!</h1><p>You are logged in as User ID: ' . htmlspecialchars($_SESSION['user_id']) . '</p><p>This is your secure content.</p></body></html>';
} else {
    header('Location: /login.php');
    exit();
}
?>