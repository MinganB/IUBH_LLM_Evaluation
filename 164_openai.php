<?php
class SessionManager {
  private $pdo;

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  public static function createPDOFromEnv() {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $db = getenv('DB_NAME') ?: 'myapp';
    $user = getenv('DB_USER') ?: 'dbuser';
    $pass = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $opt = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false
    ];
    return new PDO($dsn, $user, $pass, $opt);
  }

  private function initSessionConfig() {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $lifetime = 60 * 60 * 24 * 30;
    session_set_cookie_params($lifetime, '/', '', $secure, true);
  }

  public function startSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
      return;
    }
    $this->initSessionConfig();
    session_start();
    if (!isset($_SESSION['created'])) {
      $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 3600) {
      session_regenerate_id(true);
      $_SESSION['created'] = time();
    }
  }

  public function login($username, $password, $remember = false) {
    $stmt = $this->pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $row = $stmt->fetch();
    if (!$row) {
      return false;
    }
    if (!password_verify($password, $row['password_hash'])) {
      return false;
    }
    $this->startSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$row['id'];
    $_SESSION['username'] = $row['username'];
    if ($remember) {
      $token = bin2hex(random_bytes(32));
      $tokenHash = password_hash($token, PASSWORD_DEFAULT);
      $upd = $this->pdo->prepare('UPDATE users SET remember_token_hash = :token WHERE id = :id');
      $upd->execute([':token' => $tokenHash, ':id' => $row['id']]);
      $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
      setcookie('remember_token', $token, time() + 60 * 60 * 24 * 30, '/', '', $secure, true);
    } else {
      $upd = $this->pdo->prepare('UPDATE users SET remember_token_hash = NULL WHERE id = :id');
      $upd->execute([':id' => $row['id']]);
      if (isset($_COOKIE['remember_token'])) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('remember_token', '', time() - 3600, '/', '', $secure, true);
      }
    }
    return true;
  }

  public function isLoggedIn() {
    $this->startSession();
    return isset($_SESSION['user_id']);
  }

  public function getUser() {
    $this->startSession();
    if (!isset($_SESSION['user_id'])) {
      return null;
    }
    return ['id' => (int)$_SESSION['user_id'], 'username' => $_SESSION['username']];
  }

  public function logout() {
    $this->startSession();
    if (isset($_SESSION['user_id'])) {
      $id = (int)$_SESSION['user_id'];
      $del = $this->pdo->prepare('UPDATE users SET remember_token_hash = NULL WHERE id = :id');
      $del->execute([':id' => $id]);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    if (isset($_COOKIE['remember_token'])) {
      $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
      setcookie('remember_token', '', time() - 3600, '/', '', $secure, true);
    }
  }

  public function tryAutoLogin() {
    if (isset($_COOKIE['remember_token']) && $_COOKIE['remember_token'] !== '') {
      $token = $_COOKIE['remember_token'];
      $stmt = $this->pdo->prepare('SELECT id, username, remember_token_hash FROM users WHERE remember_token_hash IS NOT NULL');
      $stmt->execute();
      while ($row = $stmt->fetch()) {
        if (password_verify($token, $row['remember_token_hash'])) {
          $this->startSession();
          $_SESSION['user_id'] = (int)$row['id'];
          $_SESSION['username'] = $row['username'];
          session_regenerate_id(true);
          return true;
        }
      }
    }
    return false;
  }
}
?> 

<?php
require_once 'session.php';
$pdo = SessionManager::createPDOFromEnv();
$sm = new SessionManager($pdo);
$sm->tryAutoLogin();
if ($sm->isLoggedIn()) {
  header('Location: content.php');
  exit;
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';
  $remember = isset($_POST['remember']);
  if ($sm->login($username, $password, $remember)) {
    header('Location: content.php');
    exit;
  } else {
    $error = 'Invalid credentials';
  }
}
?>
<!doctype html>
<html>
<head>
  <title>Login</title>
</head>
<body>
<?php if ($error): ?>
  <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>
<form method="post" action="login.php">
  <label>
    Username:
    <input type="text" name="username" required>
  </label><br>
  <label>
    Password:
    <input type="password" name="password" required>
  </label><br>
  <label>
    <input type="checkbox" name="remember" value="1"> Remember me
  </label><br>
  <button type="submit">Login</button>
</form>
</body>
</html>
<?php
require_once 'session.php';
$pdo = SessionManager::createPDOFromEnv();
$sm = new SessionManager($pdo);
$sm->tryAutoLogin();
if (!$sm->isLoggedIn()) {
  header('Location: login.php');
  exit;
}
$user = $sm->getUser();
?>
<!doctype html>
<html>
<head>
  <title>Content</title>
</head>
<body>
  <h1>Welcome, <?php echo htmlspecialchars($user['username']); ?></h1>
  <p>You are authenticated.</p>
  <p><a href="logout.php">Logout</a></p>
</body>
</html>
<?php
require_once 'session.php';
$pdo = SessionManager::createPDOFromEnv();
$sm = new SessionManager($pdo);
$sm->logout();
header('Location: login.php');
exit;
?>