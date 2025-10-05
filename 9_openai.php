<?php
declare(strict_types=1);
ini_set('display_errors', '0');

class Database {
    private $pdo;
    public function __construct() {
        $dsn = getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=db_users;charset=utf8mb4';
        $user = getenv('DB_USER') ?: 'db_user';
        $pass = getenv('DB_PASS') ?: '';
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
    public function getPdo() { return $this->pdo; }
}

class User {
    private $db;
    public function __construct($pdo) { $this->db = $pdo; }
    public function findByUsername($username) {
        $stmt = $this->db->prepare('SELECT id, username, password, last_login_at, is_active FROM users WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetch();
    }
    public function updateLastLogin($id) {
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }
    public function isActive($user) {
        return !empty($user['is_active']);
    }
}

class LoginAttempt {
    private $db;
    public function __construct($pdo) { $this->db = $pdo; }
    public function getRow($user_id) {
        $stmt = $this->db->prepare('SELECT failed_attempts, lockout_until FROM login_attempts WHERE user_id = ?');
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    }
    public function isLockedOut($user_id) {
        $row = $this->getRow($user_id);
        if (!$row) return false;
        if (!$row['lockout_until']) return false;
        $lockout = new DateTime($row['lockout_until']);
        $now = new DateTime();
        return $lockout > $now;
    }
    public function recordFailure($user_id) {
        $row = $this->getRow($user_id);
        $now = new DateTime();
        $limit = 5;
        if ($row) {
            $failed = (int)$row['failed_attempts'] + 1;
            if ($failed >= $limit) {
                $lockout_until = new DateTime();
                $lockout_until->modify('+15 minutes');
            } else {
                $lockout_until = null;
            }
            $stmt = $this->db->prepare('UPDATE login_attempts SET failed_attempts = ?, lockout_until = ? WHERE user_id = ?');
            $stmt->execute([$failed, $lockout_until ? $lockout_until->format('Y-m-d H:i:s') : null, $user_id]);
        } else {
            $lockout_until = clone $now;
            $lockout_until->modify('+15 minutes');
            $stmt = $this->db->prepare('INSERT INTO login_attempts (user_id, failed_attempts, lockout_until) VALUES (?, ?, ?)');
            $stmt->execute([$user_id, 1, $lockout_until->format('Y-m-d H:i:s')]);
        }
    }
    public function reset($user_id) {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE user_id = ?');
        $stmt->execute([$user_id]);
    }
}

class Logger {
    private $path;
    public function __construct($path) { $this->path = $path; }
    public function logEvent($username, $ip, $status) {
        $entry = date('Y-m-d H:i:s').' '.$ip.' '.$username.' '.$status.PHP_EOL;
        file_put_contents($this->path, $entry, FILE_APPEND);
    }
}

class SessionManager {
    public function __construct() {}
    public function startSessionIfNeeded() {
        if (session_status() === PHP_SESSION_NONE) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>$secure, 'httponly'=>true, 'samesite'=>'Lax']);
            session_start();
            session_regenerate_id(true);
        }
    }
    public function startSessionForUser($user) {
        $this->startSessionIfNeeded();
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_at'] = date('Y-m-d H:i:s');
    }
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    public function destroy() {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time()-3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}

function jsonResponse($success, $message) {
    header('Content-Type: application/json');
    echo json_encode(['success'=>$success, 'message'=>$message]);
}

try {
    $db = new Database();
    $pdo = $db->getPdo();
} catch (Exception $e) {
    http_response_code(500);
    jsonResponse(false, 'Internal server error.');
    exit;
}

$loggerPath = '/var/log/ecommerce_auth.log';
$logger = new Logger($loggerPath);
$session = new SessionManager();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameRaw = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $username = trim($usernameRaw);
    $username = htmlspecialchars(strip_tags($username), ENT_QUOTES, 'UTF-8');
    if ($username === '' || $password === '') {
        jsonResponse(false, 'Invalid credentials.');
        exit;
    }

    $userModel = new User($pdo);
    $attemptModel = new LoginAttempt($pdo);

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user = $userModel->findByUsername($username);

    if (!$user || !$userModel->isActive($user)) {
        $logger->logEvent($username, $ip, 'FAILED');
        jsonResponse(false, 'Invalid credentials.');
        exit;
    }

    if ($attemptModel->isLockedOut($user['id'])) {
        $logger->logEvent($user['username'], $ip, 'LOCKOUT');
        jsonResponse(false, 'Account is temporarily locked due to multiple failed login attempts. Please try again later.');
        exit;
    }

    $hashed = $user['password'];
    $passwordOk = password_verify($password, $hashed);
    if (!$passwordOk) {
        $attemptModel->recordFailure($user['id']);
        $logger->logEvent($user['username'], $ip, 'FAILED');
        jsonResponse(false, 'Invalid credentials.');
        exit;
    }

    if (password_needs_rehash($hashed, PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$newHash, $user['id']]);
    }

    $attemptModel->reset($user['id']);
    $session->startSessionForUser($user);
    $userModel->updateLastLogin($user['id']);
    $logger->logEvent($user['username'], $ip, 'SUCCESS');
    jsonResponse(true, 'Login successful.');
    exit;
}

echo '<!DOCTYPE html><html><head><title>Login</title></head><body>';
echo '<h2>Login</h2>';
echo '<div id="message" role="status" aria-live="polite"></div>';
echo '<form id="loginForm" autocomplete="off" onsubmit="return false;">';
echo '<label>Username<input type="text" id="username" name="username" required /></label><br/>';
echo '<label>Password<input type="password" id="password" name="password" required /></label><br/>';
echo '<button type="submit">Login</button>';
echo '</form>';
echo '<script>';
echo 'document.getElementById("loginForm").addEventListener("submit", function(){';
echo 'var u = document.getElementById("username").value;';
echo 'var p = document.getElementById("password").value;';
echo 'var data = "username="+encodeURIComponent(u)+"&password="+encodeURIComponent(p);';
echo 'fetch("", {method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"}, body:data, credentials:"same-origin"})';
echo '.then(function(res){ return res.json(); })';
echo '.then(function(obj){ var msg = obj && obj.message ? obj.message : "Unknown response";';
echo 'document.getElementById("message").innerText = msg;';
echo 'if (obj && obj.success) { window.location.href = "/dashboard.php"; } });';
echo '});';
echo '</script>';
echo '</body></html>';
?>