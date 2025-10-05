<?php
class DB {
    private static $instance = null;
    private $pdo;
    private function __construct() {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $dbname = getenv('DB_NAME') ?: 'db_users';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
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
?> 
<?php
class User {
    public static function findByUsername(PDO $pdo, string $username) {
        $stmt = $pdo->prepare('SELECT id, username, password, last_login_at, is_active FROM db_users.users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        return $stmt->fetch();
    }
    public static function updateLastLogin(PDO $pdo, int $id) {
        $stmt = $pdo->prepare('UPDATE db_users.users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
    public static function isActive(array $user): bool {
        return isset($user['is_active']) && (bool)$user['is_active'];
    }
}
?> 
<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../classes/DB.php';
require_once __DIR__ . '/../../classes/User.php';
$input = [];
$raw = trim(file_get_contents('php://input'));
if ($raw) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}
$username = '';
$password = '';
if (!empty($input)) {
    $username = isset($input['username']) ? trim((string)$input['username']) : '';
    $password = $input['password'] ?? '';
} else {
    if (isset($_POST['username'])) {
        $username = trim((string)$_POST['username']);
        $password = $_POST['password'] ?? '';
    }
}
if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit;
}
try {
    $pdo = DB::getConnection();
    $user = User::findByUsername($pdo, $username);
    if (!$user || !User::isActive($user)) {
        echo json_encode(['success' => false, 'message' => 'Invalid username or inactive account.']);
        exit;
    }
    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
        exit;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['logged_in'] = true;
    User::updateLastLogin($pdo, (int)$user['id']);
    echo json_encode(['success' => true, 'message' => 'Login successful.', 'redirect' => '/public/dashboard.php']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}
?> 
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Login</title>
</head>
<body>
<div id="loginContainer">
<form id="loginForm" autocomplete="on">
<label for="username">Username</label>
<input type="text" id="username" name="username" required>
<label for="password">Password</label>
<input type="password" id="password" name="password" required>
<button type="submit">Login</button>
</form>
<div id="message" aria-live="polite"></div>
</div>
<script>
document.getElementById('loginForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const username = document.getElementById('username').value;
  const password = document.getElementById('password').value;
  try {
    const res = await fetch('/handlers/auth/LoginHandler.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({username, password})
    });
    const data = await res.json();
    if (data && data.success) {
      window.location.href = data.redirect || '/public/dashboard.php';
    } else {
      document.getElementById('message').textContent = data?.message ?? 'Login failed';
    }
  } catch {
    document.getElementById('message').textContent = 'Network error';
  }
});
</script>
</body>
</html>
?>