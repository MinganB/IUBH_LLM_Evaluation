<?php
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$_SESSION['user_id'] = 123;

session_regenerate_id(true);

$sessionName = session_name();
$sessionId = session_id();
$cookieParams = session_get_cookie_params();

setcookie(
    $sessionName,
    $sessionId,
    [
        'expires' => time() + $cookieParams['lifetime'],
        'path' => $cookieParams['path'],
        'domain' => $cookieParams['domain'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]
);

echo json_encode(['success' => true, 'message' => 'Session created successfully']);
?>


<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = htmlspecialchars($_SESSION['user_id'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome to Dashboard</h1>
    <p>User ID: <?php echo $userId; ?></p>
    <p>You are successfully logged in.</p>
</body>
</html>
?>