<?php
session_start();

function getPdo(): PDO {
  $host = $_ENV['DB_HOST'] ?? 'localhost';
  $db = $_ENV['DB_NAME'] ?? 'db_users';
  $user = $_ENV['DB_USER'] ?? 'root';
  $pass = $_ENV['DB_PASS'] ?? '';
  $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
  ];
  return new PDO($dsn, $user, $pass, $options);
}

class User {
  private PDO $pdo;
  public function __construct(PDO $pdo) { $this->pdo = $pdo; }
  public function findByUsername(string $username): ?array {
    $stmt = $this->pdo->prepare('SELECT id, username, password, last_login_at, is_active FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ?: null;
  }
  public function updateLastLogin(int $id): void {
    $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $stmt->execute([$id]);
  }
  public function setActive(int $id, bool $active): void {
    $stmt = $this->pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
    $stmt->execute[$active ? 1 : 0, $id];
  }
}

class AuthHandler {
  private PDO $pdo;
  public function __construct(PDO $pdo) { $this->pdo = $pdo; }
  private function getUserByUsername(string $username): ?array {
    return (new User($this->pdo))->findByUsername($username);
  }
  public function login(string $username, string $password): array {
    $user = $this->getUserByUsername($username);
    if (!$user) {
      return ['success'=>false, 'error'=>'Invalid credentials'];
    }
    if (empty($user['is_active']) || $user['is_active'] == 0) {
      return ['success'=>false, 'error'=>'Account is inactive'];
    }
    if (!password_verify($password, $user['password'])) {
      return ['success'=>false, 'error'=>'Invalid credentials'];
    }
    $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $stmt->execute([$user['id']]);
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    return ['success'=>true, 'redirect'=>'/dashboard.php'];
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  $token = $_SESSION['csrf_token'];
  header('Content-Type: text/html; charset=utf-8');
  echo '<!DOCTYPE html><html><head><meta charset="UTF-8"/><meta name="csrf-token" content="'.htmlspecialchars($token, ENT_QUOTES, 'UTF-8').'"/><title>Login</title></head><body>';
  echo '<h2>Login</h2>';
  echo '<div id="error" style="color:red;"></div>';
  echo '<form id="loginForm" autocomplete="on">';
  echo '<label>Username: <input type="text" id="username" name="username" required/></label><br />';
  echo '<label>Password: <input type="password" id="password" name="password" required/></label><br />';
  echo '<button type="submit">Login</button>';
  echo '</form>';
  echo '<script>';
  echo 'const form = document.getElementById("loginForm");';
  echo 'const errorBox = document.getElementById("error");';
  echo 'form.addEventListener("submit", async (e) => {';
  echo '  e.preventDefault();';
  echo '  errorBox.textContent = "";';
  echo '  const username = document.getElementById("username").value;';
  echo '  const password = document.getElementById("password").value;';
  echo '  const token = document.querySelector("meta[name=\\"csrf-token\\"]").getAttribute("content");';
  echo '  const resp = await fetch("", {';
  echo '    method: "POST",';
  echo '    headers: {"Content-Type": "application/json"},';
  echo '    body: JSON.stringify({username, password, csrf_token: token})';
  echo '  });';
  echo '  const data = await resp.json();';
  echo '  if (data.success) {';
  echo '    window.location.href = data.redirect || "/dashboard.php";';
  echo '  } else {';
  echo '    errorBox.textContent = data.error || "Login failed";';
  echo '  }';
  echo '});';
  echo '</script>';
  echo '</body></html>';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input = null;
  $raw = file_get_contents("php://input");
  if ($raw) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
      $input = $decoded;
    }
  }
  if (!$input) {
    $input = $_POST;
  }
  $username = isset($input['username']) ? trim($input['username']) : '';
  $password = isset($input['password']) ? $input['password'] : '';
  $csrf = isset($input['csrf_token']) ? $input['csrf_token'] : null;
  if (empty($username) || empty($password) || empty($csrf)) {
    echo json_encode(['success'=>false, 'error'=>'Missing credentials']);
    exit;
  }
  if (empty($_SESSION['csrf_token']) || $csrf !== $_SESSION['csrf_token']) {
    echo json_encode(['success'=>false, 'error'=>'Invalid CSRF token']);
    exit;
  }

  $pdo = getPdo();
  $handler = new AuthHandler($pdo);
  $result = $handler->login($username, $password);
  echo json_encode($result);
  exit;
}
?>