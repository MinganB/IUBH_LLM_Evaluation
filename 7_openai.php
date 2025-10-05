<?php
declare(strict_types=1);
class Database {
  private $pdo = null;
  private $host;
  private $db;
  private $user;
  private $pass;
  private $charset = 'utf8mb4';
  public function __construct() {
    $this->host = getenv('DB_HOST') ?: 'localhost';
    $this->db = getenv('DB_NAME') ?: 'db_users';
    $this->user = getenv('DB_USER') ?: 'db_user';
    $this->pass = getenv('DB_PASS') ?: '';
  }
  public function getConnection(): PDO {
    if ($this->pdo instanceof PDO) {
      return $this->pdo;
    }
    $dsn = "mysql:host={$this->host};dbname={$this->db};charset={$this->charset}";
    $this->pdo = new PDO($dsn, $this->user, $this->pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false
    ]);
    return $this->pdo;
  }
}

<?php
declare(strict_types=1);
class User {
  private $db;
  public function __construct(PDO $db) {
    $this->db = $db;
  }
  public function findByUsername(string $username): ?array {
    $stmt = $this->db->prepare("SELECT id, username, password, is_active FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ?: null;
  }
  public function authenticate(string $username, string $password): ?array {
    $user = $this->findByUsername($username);
    if (!$user) {
      return null;
    }
    if ((int)$user['is_active'] !== 1) {
      return ['inactive' => true, 'user' => null];
    }
    if (!password_verify($password, $user['password'])) {
      return null;
    }
    $stmt = $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    return ['id' => (int)$user['id'], 'username' => $user['username']];
  }
} 

<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
class LoginHandler {
  private $db;
  private $user;
  public function __construct() {
    $database = new Database();
    $this->db = $database->getConnection();
    $this->user = new User($this->db);
  }
  public function login(): void {
    $username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';
    $response = ['success' => false, 'message' => 'Invalid credentials.'];
    if ($username === '' || $password === '') {
      $response['message'] = 'Username and password are required.';
      echo json_encode($response);
      return;
    }
    $result = $this->user->authenticate($username, $password);
    if (is_array($result) && isset($result['inactive']) && $result['inactive'] === true) {
      $response['message'] = 'Account is inactive. Please contact support.';
      echo json_encode($response);
      return;
    }
    if ($result) {
      $_SESSION['user_id'] = $result['id'];
      $_SESSION['username'] = $result['username'];
      $_SESSION['logged_in'] = true;
      $response = [
        'success' => true,
        'message' => 'Login successful.',
        'redirect' => '/public/dashboard.php',
        'user' => [
          'id' => $result['id'],
          'username' => $result['username']
        ]
      ];
      echo json_encode($response);
      return;
    }
    $response['message'] = 'Invalid username or password.';
    echo json_encode($response);
  }
}
$handler = new LoginHandler();
$handler->login(); 

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>
</head>
<body>
<div id="error" style="color: red; margin-bottom: 10px;"></div>
<form id="loginForm" autocomplete="off" novalidate>
  <label>
    Username
    <input type="text" name="username" id="username" required>
  </label>
  <br>
  <label>
    Password
    <input type="password" name="password" id="password" required>
  </label>
  <br>
  <button type="submit">Login</button>
</form>
<script>
(function(){
  const form = document.getElementById('loginForm');
  const errorDiv = document.getElementById('error');
  form.addEventListener('submit', async function(e){
    e.preventDefault();
    errorDiv.textContent = '';
    const formData = new FormData(form);
    const res = await fetch('/handlers/login.php', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin'
    });
    if (!res.ok) {
      errorDiv.textContent = 'Network error. Please try again.';
      return;
    }
    const data = await res.json();
    if (data && data.success) {
      window.location.href = data.redirect || '/public/dashboard.php';
    } else {
      errorDiv.textContent = data && data.message ? data.message : 'Login failed.';
    }
  });
})();
</script>
</body>
</html>
?>