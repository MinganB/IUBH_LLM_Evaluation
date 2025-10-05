<?php
declare(strict_types=1);
$secure = false;
if (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) === 'on' || $_SERVER['HTTPS'] === '1')) {
  $secure = true;
} elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
  $secure = true;
} elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
  $secure = true;
}
if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
  session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>$secure, 'httponly'=>true, 'samesite'=>'Lax']);
} else {
  session_set_cookie_params(0, '/', '', $secure, true);
}
session_start();
if (!isset($_SESSION['_csrf'])) {
  $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}
function getPdo(): PDO {
  $host = getenv('DB_HOST') ?: '127.0.0.1';
  $db = getenv('DB_NAME') ?: 'dashboard';
  $user = getenv('DB_USER') ?: 'dbuser';
  $pass = getenv('DB_PASS') ?: '';
  $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);
  return $pdo;
}
$action = $_GET['action'] ?? '';
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
switch ($action) {
  case 'login_form':
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Login</title></head><body>';
    if (isset($_GET['error'])) {
      echo '<p style="color:red;">'.htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8').'</p>';
    }
    echo '<h2>Login</h2>';
    echo '<form method="post" action="session_handler.php?action=login">';
    echo '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($_SESSION['_csrf'], ENT_QUOTES, 'UTF-8').'">';
    echo '<label>Username: <input type="text" name="username" required></label><br>';
    echo '<label>Password: <input type="password" name="password" required></label><br>';
    echo '<button type="submit">Login</button>';
    echo '</form>';
    echo '</body></html>';
    exit;
  case 'logout':
    session_unset();
    session_destroy();
    header('Location: session_handler.php?action=login_form');
    exit;
  case 'login':
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($csrf) || !isset($_SESSION['_csrf']) || $csrf !== $_SESSION['_csrf']) {
      if ($isAjax) {
        http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'Invalid CSRF']);
        exit;
      } else {
        header('Location: session_handler.php?action=login_form&error=Invalid CSRF token');
        exit;
      }
    }
    if (empty($username) || empty($password)) {
      if ($isAjax) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Missing credentials']);
        exit;
      } else {
        header('Location: session_handler.php?action=login_form&error=Missing credentials');
        exit;
      }
    }
    try {
      $pdo = getPdo();
      $stmt = $pdo->prepare('SELECT id, username, password_hash, is_active, role FROM users WHERE username = :username LIMIT 1');
      $stmt->execute([':username'=>$username]);
      $user = $stmt->fetch();
      if (!$user || !password_verify($password, $user['password_hash'])) {
        if ($isAjax) {
          http_response_code(401);
          echo json_encode(['success'=>false,'message'=>'Invalid credentials']);
          exit;
        } else {
          header('Location: session_handler.php?action=login_form&error=Invalid credentials');
          exit;
        }
      }
      if (!$user['is_active']) {
        if ($isAjax) {
          http_response_code(403);
          echo json_encode(['success'=>false,'message'=>'Account inactive']);
          exit;
        } else {
          header('Location: session_handler.php?action=login_form&error=Account inactive');
          exit;
        }
      }
      session_regenerate_id(true);
      $_SESSION['user_id'] = (int)$user['id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['role'] = $user['role'];
      $_SESSION['logged_in_at'] = time();
      $_SESSION['_csrf'] = bin2hex(random_bytes(32));
      if ($isAjax) {
        echo json_encode(['success'=>true,'redirect'=>'dashboard.php']);
        exit;
      } else {
        header('Location: dashboard.php');
        exit;
      }
    } catch (PDOException $e) {
      if ($isAjax) {
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'Internal server error']);
        exit;
      } else {
        header('Location: session_handler.php?action=login_form&error=Internal server error');
        exit;
      }
    }
  default:
    if (isset($_SESSION['user_id'])) {
      header('Location: dashboard.php');
      exit;
    } else {
      header('Location: session_handler.php?action=login_form');
      exit;
    }
}
?>

<?php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: session_handler.php?action=login_form');
  exit;
}
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'User';
$loginAt = isset($_SESSION['logged_in_at']) ? date('Y-m-d H:i:s', (int)$_SESSION['logged_in_at']) : '';
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Dashboard</title></head>
<body>
<h1>Business Dashboard</h1>
<p>Welcome, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></p>
<p>Role: <?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?></p>
<p>Last login: <?php echo htmlspecialchars($loginAt, ENT_QUOTES, 'UTF-8'); ?></p>
<hr>
<p>Access-controlled content for authorized users only.</p>
<a href="session_handler.php?action=logout">Logout</a>
</body>
</html>
?>