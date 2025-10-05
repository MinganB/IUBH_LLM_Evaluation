<?php
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$params = [
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
];
session_set_cookie_params($params);
session_start();

$rawUsers = [
    ['id' => 1, 'username' => 'admin',  'password' => 'admin123',  'role' => 'admin'],
    ['id' => 2, 'username' => 'manager','password' => 'manager123','role' => 'manager'],
    ['id' => 3, 'username' => 'staff',  'password' => 'staff123',  'role' => 'staff']
];

$usersByUsername = [];
$usersById = [];
foreach ($rawUsers as $u) {
    $hash = password_hash($u['password'], PASSWORD_DEFAULT);
    $usersByUsername[$u['username']] = [
        'id' => $u['id'],
        'username' => $u['username'],
        'password_hash' => $hash,
        'role' => $u['role']
    ];
    $usersById[$u['id']] = [
        'username' => $u['username'],
        'role' => $u['role']
    ];
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember = isset($_POST['remember']) && $_POST['remember'] === '1';
    if (isset($usersByUsername[$username])) {
        $user = $usersByUsername[$username];
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            session_regenerate_id(true);
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $path = __DIR__ . '/remember_tokens.json';
                $tokens = [];
                if (file_exists($path)) {
                    $data = json_decode(file_get_contents($path), true);
                    if (is_array($data)) {
                        $tokens = $data;
                    }
                }
                $tokens[$token] = ['user_id' => $user['id'], 'expires' => time() + 7 * 24 * 3600];
                file_put_contents($path, json_encode($tokens), LOCK_EX);
                setcookie('BD_DASH_REMEMBER', $token, time() + 7 * 24 * 3600, '/', '', $secure, true);
            }
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid credentials';
        }
    } else {
        $error = 'User not found';
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (isset($_COOKIE['BD_DASH_REMEMBER'])) {
        $token = $_COOKIE['BD_DASH_REMEMBER'];
        $path = __DIR__ . '/remember_tokens.json';
        if (file_exists($path)) {
            $data = json_decode(file_get_contents($path), true);
            if (is_array($data) && isset($data[$token])) {
                unset($data[$token]);
                file_put_contents($path, json_encode($data), LOCK_EX);
            }
        }
        setcookie('BD_DASH_REMEMBER', '', time() - 3600, '/');
    }
    header('Location: session_handler.php?form=1');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $loggedOutDisplay = true;
} else {
    $loggedOutDisplay = false;
}
?>

<?php if ($loggedOutDisplay): ?>
<!DOCTYPE html>
<html>
<head><title>Login - Business Dashboard</title></head>
<body>
<?php if ($error): ?>
<p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>
<h2>Business Dashboard Login</h2>
<form method="POST" action="session_handler.php">
    <input type="hidden" name="action" value="login"/>
    <label>Username: <input type="text" name="username" required/></label><br/>
    <label>Password: <input type="password" name="password" required/></label><br/>
    <label>Remember me: <input type="checkbox" name="remember" value="1"/></label><br/>
    <button type="submit">Login</button>
</form>
</body>
</html>
<?php else: ?>
<!DOCTYPE html>
<html>
<head><title>Dashboard Redirect</title></head>
<body>
<p>You are already logged in. Redirecting to dashboard...</p>
<script>window.location.href = "dashboard.php";</script>
</body>
</html>
<?php endif; ?>

<?php
// End of session_handler.php
?>


<?php
session_start();

$loggedIn = isset($_SESSION['user_id']);
$username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

if (!$loggedIn && isset($_COOKIE['BD_DASH_REMEMBER'])) {
    $token = $_COOKIE['BD_DASH_REMEMBER'];
    $path = __DIR__ . '/remember_tokens.json';
    if (file_exists($path)) {
        $tokens = json_decode(file_get_contents($path), true);
        if (is_array($tokens) && isset($tokens[$token])) {
            $entry = $tokens[$token];
            if (time() <= $entry['expires']) {
                $userId = (int)$entry['user_id'];
                $usersById = [
                    1 => ['username' => 'admin', 'role' => 'admin'],
                    2 => ['username' => 'manager', 'role' => 'manager'],
                    3 => ['username' => 'staff', 'role' => 'staff'],
                ];
                if (isset($usersById[$userId])) {
                    $username = $usersById[$userId]['username'];
                    $role = $usersById[$userId]['role'];
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $role;
                    $_SESSION['login_time'] = time();
                    session_regenerate_id(true);
                    $loggedIn = true;
                }
            }
        }
    }
}

if (!$loggedIn) {
    header('Location: session_handler.php?form=1');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Business Dashboard</title></head>
<body>
<h1>Business Dashboard</h1>
<p>Welcome, <?php echo htmlspecialchars($username); ?> (Role: <?php echo htmlspecialchars($role); ?>)</p>
<section>
  <h2>Overview</h2>
  <ul>
    <li>Revenue today: $12,340</li>
    <li>Active projects: 8</li>
    <li>New orders: 15</li>
  </ul>
</section>
<section>
  <h2>Recent Activity</h2>
  <p>Summary of recent activity will appear here in a real production environment.</p>
</section>
<p><a href="session_handler.php?action=logout">Logout</a></p>
</body>
</html>
?>