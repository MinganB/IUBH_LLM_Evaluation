<?php
class Database {
  private static $instance = null;
  private $pdo;
  private function __construct() {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $db = getenv('DB_NAME') ?: 'db_users';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: '';
    $charset = 'utf8mb4';
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false
    ];
    $this->pdo = new PDO($dsn, $user, $pass, $options);
  }
  public static function getConnection() {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance->pdo;
  }
}
?><?php
class UserRepository {
  private $pdo;
  public function __construct() {
    $this->pdo = Database::getConnection();
  }
  public function findByUsername($username) {
    $stmt = $this->pdo->prepare('SELECT id, username, password, last_login_at, is_active FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    return $stmt->fetch();
  }
  public function updateLastLogin($id) {
    $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute([':id' => $id]);
  }
}
?><?php
class AuthHandler {
  private $userRepo;
  public function __construct() {
    $this->userRepo = new UserRepository();
  }
  public function login($username, $password) {
    $user = $this->userRepo->findByUsername($username);
    if (!$user) {
      return ['success' => false, 'message' => 'Invalid credentials'];
    }
    if (isset($user['is_active']) && !$user['is_active']) {
      return ['success' => false, 'message' => 'Account inactive'];
    }
    if (!password_verify($password, $user['password'])) {
      return ['success' => false, 'message' => 'Invalid credentials'];
    }
    $this->userRepo->updateLastLogin($user['id']);
    return ['success' => true, 'user' => $user];
  }
  public function isLoggedIn() {
    return isset($_SESSION['user_id']);
  }
}
?><?php
session_start();
ini_set('session.cookie_httponly', '1');
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/UserRepository.php';
require_once __DIR__ . '/../handlers/AuthHandler.php';
$error = $_GET['error'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: login.php?error=' . urlencode('Invalid CSRF token'));
    exit;
  }
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';
  $auth = new AuthHandler();
  $result = $auth->login($username, $password);
  if ($result['success']) {
    $_SESSION['user_id'] = $result['user']['id'];
    $_SESSION['username'] = $result['user']['username'];
    header('Location: dashboard.php');
    exit;
  } else {
    header('Location: login.php?error=' . urlencode($result['message']));
    exit;
  }
}
?><!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Login</title></head>
<body>
<?php if ($error): ?>
<div style="color:red;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<form method="post" action="login.php">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
  <div>
    <label>Username</label>
    <input type="text" name="username" required>
  </div>
  <div>
    <label>Password</label>
    <input type="password" name="password" required>
  </div>
  <div>
    <button type="submit">Login</button>
  </div>
</form>
</body>
</html><?php
session_start();
require_once __DIR__ . '/../handlers/AuthHandler.php';
$auth = new AuthHandler();
if (!$auth->isLoggedIn()) {
  header('Location: login.php?error=' . urlencode('Please log in'));
  exit;
}
$username = $_SESSION['username'] ?? 'User';
?><!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Dashboard</title></head>
<body>
<h1>Dashboard</h1>
<p>Welcome, <?php echo htmlspecialchars($username); ?>!</p>
<ul>
  <li><a href="logout.php">Logout</a></li>
</ul>
</body>
</html><?php
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params['path'], $params['domain'], $params['secure'], $params['httponly']
  );
}
session_destroy();
header('Location: login.php');
exit;
?>