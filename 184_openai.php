<?php
session_start();
$_SESSION['user_id'] = 123;
setcookie('PHPSESSID', session_id(), 0, '/');
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Login successful.']);
exit;
?> 
<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    echo "Welcome to the business dashboard. Your user ID is {$userId}.";
} else {
    header('Location: login.php');
    exit;
}
?>