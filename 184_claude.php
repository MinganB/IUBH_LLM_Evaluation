**session_handler.php**

<?php
session_start();

$_SESSION['user_id'] = 123;

if (session_id()) {
    setcookie('PHPSESSID', session_id(), time() + 3600, '/');
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Login successful'
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Session failed to start'
    ]);
}
?>


**dashboard.php**

<?php
session_start();

if (isset($_SESSION['user_id'])) {
    echo "Welcome to the dashboard! Your user ID is: " . $_SESSION['user_id'];
} else {
    header('Location: login.php');
    exit();
}
?>