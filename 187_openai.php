<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}
session_start();
$_SESSION['user_id'] = 123;
setcookie(session_name(), session_id(), time() + 3600, '/');
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Login successful']);
exit;
?>

<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    echo 'Welcome, user ID: ' . htmlspecialchars($userId, ENT_QUOTES, 'UTF-8');
    exit;
} else {
    header('Location: /public/login.php');
    exit;
}
?>