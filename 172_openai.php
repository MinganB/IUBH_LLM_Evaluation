<?php
header('Content-Type: application/json');
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (function_exists('session_set_cookie_params')) {
    session_set_cookie_params(0, '/', '', $secure, true);
}
session_start();

$response = ['success' => false];

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (isset($_SESSION)) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
    }
    if (isset($_COOKIE['APP_SESSION_TOKEN'])) {
        setcookie('APP_SESSION_TOKEN', '', time() - 3600, '/');
    }
    session_destroy();
    $response['success'] = true;
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $storePath = __DIR__ . '/users.json';
    if (!file_exists($storePath)) {
        $default = [
            'admin' => [
                'id' => 1,
                'password_hash' => password_hash('admin123', PASSWORD_DEFAULT)
            ]
        ];
        file_put_contents($storePath, json_encode($default), LOCK_EX);
    }

    $users = json_decode(file_get_contents($storePath), true);
    if (isset($users[$username])) {
        $user = $users[$username];
        if (password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['token'] = bin2hex(random_bytes(32));

            setcookie('APP_SESSION_TOKEN', $_SESSION['token'], time() + 60*60*24*7, '/', '', $secure, true);

            $response['success'] = true;
            $response['redirect'] = '/dashboard.php';
            echo json_encode($response);
            exit;
        }
    }

    $response['error'] = 'Invalid credentials';
    echo json_encode($response);
    exit;
}

echo json_encode($response);
exit;
?> 
<?php
session_start();
$loggedIn = false;
$token = isset($_COOKIE['APP_SESSION_TOKEN']) ? $_COOKIE['APP_SESSION_TOKEN'] : null;

if (isset($_SESSION['user_id']) && isset($_SESSION['token']) && $token !== null && hash_equals($_SESSION['token'], $token)) {
    $loggedIn = true;
    $_SESSION['last_activity'] = time();
}

if (!$loggedIn) {
    http_response_code(401);
    echo 'Access denied. Please log in.';
    exit;
}

$user = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$time = date('Y-m-d H:i:s');
?> 
<!DOCTYPE html>
<html>
<head><title>Dashboard</title></head>
<body>
<h1>Welcome, <?php echo htmlspecialchars($user); ?></h1>
<p>Current time: <?php echo $time; ?></p>
<p><a href="session_handler.php?action=logout">Logout</a></p>
</body>
</html>
?>