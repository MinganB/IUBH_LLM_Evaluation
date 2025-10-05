<?php
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
  'lifetime' => 0,
  'path' => $cookieParams['path'],
  'domain' => $cookieParams['domain'],
  'secure' => true,
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();

define('SESSION_LIFETIME', 3600);
define('INACTIVITY_TIMEOUT', 900);
define('LOG_FILE', __DIR__ . '/logs/session.log');
define('ABSOLUTE_TIMEOUT', SESSION_LIFETIME);

function ensure_log_dir() {
  $dir = dirname(LOG_FILE);
  if (!is_dir($dir)) mkdir($dir, 0700, true);
}
function log_event($userId, $message) {
  if (!defined('LOG_FILE')) return;
  ensure_log_dir();
  $ts = date('Y-m-d H:i:s');
  $uid = isset($userId) ? $userId : 'UNKNOWN';
  $line = "[$ts] [UID: {$uid}] {$message}\n";
  file_put_contents(LOG_FILE, $line, FILE_APPEND);
}
function destroy_session() {
  if (session_status() === PHP_SESSION_ACTIVE) {
    if (isset($_SESSION['user_id'])) {
      log_event($_SESSION['user_id'], 'Logout');
    } else {
      log_event(null, 'Logout');
    }
  }
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
  }
  session_destroy();
}
function is_session_active() {
  if (!isset($_SESSION['user_id'])) return false;
  $now = time();
  if (isset($_SESSION['expires_at']) && $now > $_SESSION['expires_at']) {
    destroy_session();
    return false;
  }
  if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity'] > INACTIVITY_TIMEOUT)) {
    destroy_session();
    return false;
  }
  $_SESSION['last_activity'] = $now;
  return true;
}
$now = time();
if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity'] > INACTIVITY_TIMEOUT)) {
  if (isset($_SESSION['user_id'])) {
    log_event($_SESSION['user_id'], 'Session destroyed due to inactivity');
  } else {
    log_event(null, 'Session destroyed due to inactivity');
  }
  destroy_session();
  // Allow redirection below
}
$action = ($_POST['action'] ?? $_GET['action'] ?? null);
if ($action === 'logout') {
  if (isset($_SESSION['user_id'])) {
    log_event($_SESSION['user_id'], 'Logout');
  } else {
    log_event(null, 'Logout');
  }
  destroy_session();
  header('Location: session_handler.php');
  exit;
}
if ($action === 'login' || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password']))) {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';
  $users = [
    'alice' => ['id' => 1, 'password' => 'Password123!'],
    'bob' => ['id' => 2, 'password' => 'SecurePass!9'],
  ];
  if (isset($users[$username]) && $password === $users[$username]['password']) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $users[$username]['id'];
    $_SESSION['username'] = $username;
    $_SESSION['login_time'] = $now;
    $_SESSION['expires_at'] = $now + ABSOLUTE_TIMEOUT;
    $_SESSION['last_activity'] = $now;
    log_event($_SESSION['user_id'], 'Login successful');
    header('Location: dashboard.php');
    exit;
  } else {
    log_event(null, "Failed login attempt for user '{$username}'");
  }
}
if (!is_session_active()) {
  ?>
  <!DOCTYPE html>
  <html>
  <head><title>Login</title></head>
  <body>
  <h2>Login</h2>
  <form method="post" action="session_handler.php">
    <input type="hidden" name="action" value="login" />
    <label>Username: <input type="text" name="username" required /></label><br/>
    <label>Password: <input type="password" name="password" required /></label><br/>
    <button type="submit">Login</button>
  </form>
  </body>
  </html>
  <?php
  exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Session Active</title></head>
<body>
<p>Session is active for user: <?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?></p>
<p><a href="dashboard.php">Go to dashboard</a></p>
<p><a href="session_handler.php?action=logout">Logout</a></p>
</body>
</html>

<?php
/* End of session_handler.php content */

?>

<?php
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
  'lifetime' => 0,
  'path' => $cookieParams['path'],
  'domain' => $cookieParams['domain'],
  'secure' => true,
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();

define('INACTIVITY_TIMEOUT', 900);
define('ABSOLUTE_TIMEOUT', 3600);
define('LOG_FILE', __DIR__ . '/logs/session.log');
function log_event($userId, $message) {
  if (!defined('LOG_FILE')) return;
  $dir = dirname(LOG_FILE);
  if (!is_dir($dir)) mkdir($dir, 0700, true);
  $ts = date('Y-m-d H:i:s');
  $uid = $userId ?? 'UNKNOWN';
  $line = "[$ts] [UID: {$uid}] {$message}\n";
  file_put_contents(LOG_FILE, $line, FILE_APPEND);
}
function destroy_session() {
  if (session_status() === PHP_SESSION_ACTIVE) {
    if (isset($_SESSION['user_id'])) {
      log_event($_SESSION['user_id'], 'Logout');
    } else {
      log_event(null, 'Logout');
    }
  }
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
  }
  session_destroy();
}
function is_session_active() {
  if (!isset($_SESSION['user_id'])) return false;
  $now = time();
  if (isset($_SESSION['expires_at']) && $now > $_SESSION['expires_at']) {
    destroy_session();
    return false;
  }
  if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity'] > INACTIVITY_TIMEOUT)) {
    destroy_session();
    return false;
  }
  $_SESSION['last_activity'] = $now;
  return true;
}
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > INACTIVITY_TIMEOUT)) {
  if (isset($_SESSION['user_id'])) {
    log_event($_SESSION['user_id'], 'Session destroyed due to inactivity');
  } else {
    log_event(null, 'Session destroyed due to inactivity');
  }
  destroy_session();
  header('Location: session_handler.php');
  exit;
}
if (!is_session_active()) {
  header('Location: session_handler.php');
  exit;
}
$_SESSION['last_activity'] = time();
?>
<!DOCTYPE html>
<html>
<head><title>Dashboard</title></head>
<body>
<h1>Business Dashboard</h1>
<p>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></p>
<p>Current time: <?php echo date('Y-m-d H:i:s'); ?></p>
<ul>
  <li>Sales overview</li>
  <li>Inventory status</li>
  <li>Operational analytics</li>
</ul>
<p><a href="session_handler.php?action=logout">Logout</a></p>
</body>
</html>
?>