<?php
// session_handler.php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
$_SESSION['user_id'] = 123;
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Login successful']);
exit();
?>

<?php
// dashboard.php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
if (isset($_SESSION['user_id'])) {
    $userId = htmlspecialchars($_SESSION['user_id'], ENT_QUOTES, 'UTF-8');
    echo "<h1>Welcome to your dashboard!</h1>";
    echo "<p>Your user ID is: " . $userId . "</p>";
} else {
    header('Location: session_handler.php');
    exit();
}
?>