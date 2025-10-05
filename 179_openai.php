<?php
class Database {
    private static $pdo = null;
    private function __construct() {}
    public static function getConnection() {
        if (self::$pdo === null) {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $db   = getenv('DB_NAME') ?: 'db_users';
            $user = getenv('DB_USER') ?: 'db_user';
            $pass = getenv('DB_PASSWORD') ?: '';
            $charset = 'utf8mb4';
            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            self::$pdo = new PDO($dsn, $user, $pass, $options);
        }
        return self::$pdo;
    }
}
?>

<?php
class SessionManager {
    private $pdo;
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    public function login($username, $password) {
        $stmt = $this->pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user) {
            return ['status' => 'error', 'message' => 'Invalid credentials'];
        }
        if (!password_verify($password, $user['password'])) {
            return ['status' => 'error', 'message' => 'Invalid credentials'];
        }
        session_start();
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 86400);
        $stmt2 = $this->pdo->prepare("INSERT INTO user_sessions (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt2->execute([$user['id'], $token, $expires]);
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('dashboard_token', $token, time() + 86400, '/', '', $secure, true);
        return ['status' => 'success', 'message' => 'Login successful', 'user_id' => (int)$user['id']];
    }
    public function validateCurrentSession() {
        session_start();
        if (isset($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
            if (isset($_COOKIE['dashboard_token'])) {
                $token = $_COOKIE['dashboard_token'];
                $stmt = $this->pdo->prepare("SELECT 1 FROM user_sessions WHERE user_id = ? AND token = ? AND expires_at > NOW()");
                $stmt->execute([$userId, $token]);
                if ($stmt->fetch()) {
                    return ['logged_in' => true, 'user_id' => $userId];
                }
            }
        }
        if (isset($_COOKIE['dashboard_token'])) {
            $token = $_COOKIE['dashboard_token'];
            $stmt = $this->pdo->prepare("SELECT user_id FROM user_sessions WHERE token = ? AND expires_at > NOW()");
            $stmt->execute([$token]);
            $row = $stmt->fetch();
            if ($row) {
                session_start();
                $_SESSION['user_id'] = (int)$row['user_id'];
                $unameStmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
                $unameStmt->execute([ (int)$row['user_id'] ]);
                $u = $unameStmt->fetch();
                if ($u) $_SESSION['username'] = $u['username'];
                return ['logged_in' => true, 'user_id' => (int)$row['user_id']];
            }
        }
        return ['logged_in' => false];
    }
    public function logout() {
        session_start();
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            $stmt->execute([$userId]);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        if (isset($_COOKIE['dashboard_token'])) {
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            setcookie('dashboard_token', '', time() - 3600, '/', '', $secure, true);
        }
        return ['status' => 'success', 'message' => 'Logged out'];
    }
}
?>

<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SessionManager.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Invalid request method']);
    exit;
}
$username = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;
if (!$username || !$password) {
    echo json_encode(['status'=>'error','message'=>'Username and password required']);
    exit;
}
$pdo = Database::getConnection();
$sm = new SessionManager($pdo);
$result = $sm->login($username, $password);
echo json_encode($result);
exit;
?>

<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SessionManager.php';
$pdo = Database::getConnection();
$sm = new SessionManager($pdo);
$check = $sm->validateCurrentSession();
if (!$check['logged_in']) {
    header('Content-Type: application/json');
    echo json_encode(['status'=>'error','message'=>'No active session']);
    exit;
}
$userId = (int)$check['user_id'];
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
$username = $user['username'] ?? 'User';
?>
<!DOCTYPE html>
<html>
<head><title>Dashboard</title></head>
<body>
<h1>Dashboard</h1>
<p>Welcome, <?php echo htmlspecialchars($username); ?></p>
<p>Your user ID: <?php echo $userId; ?></p>
<p>Access to your business metrics is secure.</p>
</body>
</html>
?>