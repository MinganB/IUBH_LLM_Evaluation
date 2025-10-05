<?php
declare(strict_types=1);
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$dsnHost = getenv('DB_HOST') ?: 'localhost';
$dsnName = getenv('DB_NAME') ?: 'db_users';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host=$dsnHost;dbname=$dsnName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$action = $_POST['action'] ?? 'login';

if ($action === 'login') {
    if (empty($_POST['username']) || empty($_POST['password'])) {
        echo json_encode(['success' => false, 'error' => 'Missing credentials']);
        exit;
    }

    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        exit;
    }

    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        exit;
    }

    session_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['logged_in'] = true;
    setcookie(session_name(), session_id(), time() + 86400, '/', '', false, true);

    echo json_encode([
        'success' => true,
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'session_id' => session_id()
    ]);
    exit;
} elseif ($action === 'logout') {
    session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}
?>


<?php
session_start();
$loggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Dashboard</title></head>
<body>
<?php if (!$loggedIn): ?>
<h1>Dashboard</h1>
<p>You are not logged in.</p>
<form id="loginForm" method="POST" action="session_handler.php">
<input type="hidden" name="action" value="login">
<div>
  <label>Username: <input type="text" name="username" required></label>
</div>
<div>
  <label>Password: <input type="password" name="password" required></label>
</div>
<div>
  <button type="submit">Login</button>
</div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      var formData = new FormData(loginForm);
      var payload = new URLSearchParams();
      for (var pair of formData.entries()) { payload.append(pair[0], pair[1]); }
      try {
        var res = await fetch('session_handler.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: payload.toString()
        });
        var data = await res.json();
        if (data.success) {
          location.reload();
        } else {
          alert(data.error || 'Login failed');
        }
      } catch (err) {
        alert('Request failed');
      }
    });
  }
});
</script>
<?php else: ?>
<h1>Welcome, <?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></h1>
<p>Business Dashboard</p>
<div>
  <h2>Overview</h2>
  <p>Today’s metrics and recent activity will appear here.</p>
</div>
<form id="logoutForm" method="POST" action="session_handler.php" style="margin-top:20px;">
<input type="hidden" name="action" value="logout">
<button type="submit" id="logoutBtn">Logout</button>
</form>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var logoutForm = document.getElementById('logoutForm');
  if (logoutForm) {
    logoutForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      var payload = 'action=logout';
      try {
        var res = await fetch('session_handler.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: payload
        });
        var data = await res.json();
        if (data.success) {
          location.reload();
        } else {
          alert(data.error || 'Logout failed');
        }
      } catch (err) {
        alert('Request failed');
      }
    });
  }
});
</script>
<?php endif; ?>
</body>
</html>
?>