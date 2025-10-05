<?php
session_start();
function isAjax() {
  if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    return true;
  }
  if (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false) {
    return true;
  }
  return false;
}
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'db_users';
$dbUser = getenv('DB_USER') ?: 'db_user';
$dbPass = getenv('DB_PASS') ?: '';
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';
  $errorCode = '';
  if (empty($username) || empty($password)) {
    $errorCode = 'empty_fields';
    if (isAjax()) {
      echo json_encode(['success' => false, 'error' => $errorCode]);
      exit;
    } else {
      header("Location: /public/login.php?error=$errorCode");
      exit;
    }
  }
  try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $stmt = $pdo->prepare('SELECT id, username, password, last_login_at, is_active FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();
    if (!$user || !$user['is_active']) {
      $errorCode = 'invalid_credentials';
      if (isAjax()) {
        echo json_encode(['success' => false, 'error' => $errorCode]);
        exit;
      } else {
        header("Location: /public/login.php?error=$errorCode");
        exit;
      }
    }
    if (password_verify($password, $user['password'])) {
      $upd = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
      $upd->execute([':id' => $user['id']]);
      session_regenerate_id(true);
      $_SESSION['user_id'] = (int)$user['id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['logged_in'] = true;
      if (isAjax()) {
        echo json_encode(['success' => true, 'redirect' => '/dashboard.php']);
        exit;
      } else {
        header('Location: /dashboard.php');
        exit;
      }
    } else {
      $errorCode = 'invalid_credentials';
      if (isAjax()) {
        echo json_encode(['success' => false, 'error' => $errorCode]);
        exit;
      } else {
        header("Location: /public/login.php?error=$errorCode");
        exit;
      }
    }
  } catch (PDOException $e) {
    if (isAjax()) {
      echo json_encode(['success' => false, 'error' => 'server_error']);
      exit;
    } else {
      header('Location: /public/login.php?error=server_error');
      exit;
    }
  }
} else {
  $error = $_GET['error'] ?? '';
  $message = '';
  switch ($error) {
    case 'empty_fields': $message = 'Please enter username and password.'; break;
    case 'invalid_credentials': $message = 'Invalid username or password.'; break;
    case 'server_error': $message = 'A server error occurred. Please try again later.'; break;
    default: $message = ''; break;
  }
  ?>
  <!doctype html>
  <html>
  <head>
    <meta charset="utf-8">
    <title>Login</title>
  </head>
  <body>
    <?php if ($message): ?>
    <p style="color:red;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <form method="POST" action="/public/login.php">
      <label>Username
        <input type="text" name="username" autocomplete="username" required>
      </label><br>
      <label>Password
        <input type="password" name="password" autocomplete="current-password" required>
      </label><br>
      <button type="submit">Login</button>
    </form>
  </body>
  </html>
  <?php
}
?>