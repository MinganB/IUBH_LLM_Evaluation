<?php
class SessionManager {
    private $pdo;
    private $dbPath;

    public function __construct() {
        $this->dbPath = __DIR__ . '/data/users.db';
        $this->initDatabase();
        $this->startSession();
    }

    private function initDatabase() {
        $dir = __DIR__ . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->pdo = new PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS cnt FROM users");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row['cnt'] == 0) {
            $hash = password_hash('password', PASSWORD_DEFAULT);
            $this->pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)")->execute(['admin', $hash]);
        }
    }

    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            ini_set('session.cookie_secure', $secure ? '1' : '0');
            if (PHP_VERSION_ID >= 70300) {
                ini_set('session.cookie_samesite', 'Lax');
            }
            session_start([
                'cookie_lifetime' => 86400
            ]);
        }
        if (session_id()) {
            if (!isset($_COOKIE['APP_SESSION'])) {
                setcookie('APP_SESSION', session_id(), time() + 86400, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
            }
        }
    }

    public function login($username, $password) {
        $stmt = $this->pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['logged_in_at'] = time();

            if (!isset($_COOKIE['APP_SESSION']) || $_COOKIE['APP_SESSION'] !== session_id()) {
                setcookie('APP_SESSION', session_id(), time() + 86400, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
            }
            return true;
        }
        return false;
    }

    public function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }

    public function requireLogin() {
        if (!$this->isAuthenticated()) {
            $current = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: login.php?redirect=' . urlencode($current));
            exit;
        }
    }

    public function logout() {
        if (session_status() == PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'],
                    $params['secure'], $params['httponly']);
            }
            session_destroy();
        }
        if (isset($_COOKIE['APP_SESSION'])) {
            setcookie('APP_SESSION', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
        }
    }
}
?><?php
?> 

<?php
require_once __DIR__ . '/session_manager.php';
$sm = new SessionManager();
$error = null;
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($sm->login($username, $password)) {
        $dest = $redirect ?: 'protected.php';
        header('Location: ' . $dest);
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}

if ($sm->isAuthenticated()) {
    header('Location: protected.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Login</title></head>
<body>
<?php if ($error): ?>
<p style="color:red;"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></p>
<?php endif; ?>
<form method="post" action="login.php<?php echo $redirect ? '?redirect=' . urlencode($redirect) : ''; ?>">
  <label>Username:<input type="text" name="username" required></label><br>
  <label>Password:<input type="password" name="password" required></label><br>
  <button type="submit">Login</button>
</form>
</body>
</html>
<?php
?> 

<?php
require_once __DIR__ . '/session_manager.php';
$sm = new SessionManager();
$sm->requireLogin();
$username = $_SESSION['username'] ?? 'Guest';
?>
<!DOCTYPE html>
<html>
<head><title>Protected Page</title></head>
<body>
<h1>Protected Content</h1>
<p>Welcome, <?php echo htmlspecialchars($username, ENT_QUOTES); ?>!</p>
<p>This content is only visible to authenticated users.</p>
<p><a href="logout.php">Logout</a></p>
</body>
</html>
<?php
?> 

<?php
require_once __DIR__ . '/session_manager.php';
$sm = new SessionManager();
$sm->logout();
header('Location: login.php');
exit;
?>