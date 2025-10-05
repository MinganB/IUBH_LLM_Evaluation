<?php
declare(strict_types=1);
header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (strtoupper($method) !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST method required.']);
    exit;
}
if (session_status() === PHP_SESSION_NONE) {
    if (!session_start()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Session start failed.']);
        exit;
    }
} else {
    session_start();
}
$_SESSION['user_id'] = 123;
if (function_exists('session_regenerate_id')) {
    session_regenerate_id(true);
}
$secure = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
setcookie('PHPSESSID', session_id(), 0, '/', '', $secure, true);
echo json_encode(['success' => true, 'message' => 'Login successful.']);
exit;
?>

<?php
declare(strict_types=1);
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    if (!session_start()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Session start failed.']);
        exit;
    }
} else {
    session_start();
}
if (isset($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    echo json_encode(['success' => true, 'message' => 'Welcome, user ' . $userId]);
    exit;
} else {
    header('Location: /public/login.php');
    exit;
}
?>