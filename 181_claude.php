**session_handler.php**

<?php
session_start();

$_SESSION['user_id'] = 123;

setcookie('PHPSESSID', session_id(), time() + 3600, '/');

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Session created successfully'
]);
?>


**dashboard.php**

<?php
session_start();

if (isset($_SESSION['user_id'])) {
    echo "Welcome! Your user ID is: " . $_SESSION['user_id'];
} else {
    header('Location: login.php');
    exit;
}
?>