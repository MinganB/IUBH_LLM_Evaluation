<?php
declare(strict_types=1);

function ensure_data_store(): string {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $path = $dir . '/users.json';
    if (!file_exists($path)) {
        $default = [
            [
                'id' => 1,
                'username' => 'admin',
                'password_hash' => password_hash('password', PASSWORD_DEFAULT)
            ]
        ];
        file_put_contents($path, json_encode($default, JSON_PRETTY_PRINT));
    }
    return $path;
}

function load_users(): array {
    $path = ensure_data_store();
    $contents = file_get_contents($path);
    $data = json_decode($contents, true);
    if (!is_array($data)) return [];
    return $data;
}

function find_user_by_username(string $username) {
    foreach (load_users() as $u) {
        if (isset($u['username']) && $u['username'] === $username) {
            return $u;
        }
    }
    return null;
}

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $lifetime = 604800;
        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'domain' => '',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } else {
            session_set_cookie_params($lifetime, '/');
        }
        session_start();
        if (function_exists('session_regenerate_id')) session_regenerate_id(true);
    }
}

function login_user(array $user): void {
    if (session_status() === PHP_SESSION_NONE) start_secure_session();
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username']
    ];
}

function is_user_logged_in(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

function logout_user(): void {
    if (session_status() === PHP_SESSION_NONE) start_secure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function verify_credentials(string $username, string $password) {
    $user = find_user_by_username($username);
    if ($user && isset($user['password_hash'])) {
        if (password_verify($password, $user['password_hash'])) {
            return $user;
        }
    }
    return null;
}

start_secure_session();

$loginError = null;

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout_user();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $found = verify_credentials($username, $password);
    if ($found) {
        login_user($found);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = 'Invalid username or password.';
    }
}

$loggedIn = is_user_logged_in();
$currentUser = $loggedIn ? $_SESSION['user'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Business Dashboard</title>
</head>
<body>
<?php if ($loggedIn): ?>
  <h1>Business Dashboard</h1>
  <p>Welcome, <?php echo htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8'); ?></p>
  <div>
    <h2>Overview</h2>
    <ul>
      <li>Total Revenue: $120,000</li>
      <li>Active Clients: 48</li>
      <li>Open Invoices: 7</li>
    </ul>
  </div>
  <p><a href="?action=logout">Logout</a></p>
<?php else: ?>
  <h1>Login</h1>
  <?php if ($loginError): ?>
    <p style="color:red;"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>
  <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">
    <label>
      Username:
      <input type="text" name="username" required>
    </label><br>
    <label>
      Password:
      <input type="password" name="password" required>
    </label><br>
    <button type="submit">Login</button>
  </form>
<?php endif; ?>
</body>
</html>
?>