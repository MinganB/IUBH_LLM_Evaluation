<?php
session_start();

$host = '127.0.0.1';
$db   = 'myapp';
$user = 'dbuser';
$pass = 'dbpass';
$charset = 'utf8mb4';
$pdo = null;
try {
  $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  http_response_code(500);
  echo 'Internal Server Error';
  exit;
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$input = ['email' => ''];

function is_logged_in() {
  return isset($_SESSION['user_id']);
}

if (is_logged_in()) {
  header('Location: dashboard.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(400);
    $errors[] = 'Invalid CSRF token.';
  }

  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  $input['email'] = $email;

  if ($email === '') {
    $errors[] = 'Email is required.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format.';
  }

  if ($password === '') {
    $errors[] = 'Password is required.';
  }

  if (empty($errors)) {
    try {
      $stmt = $pdo->prepare('SELECT id, name, email, password_hash, is_active FROM users WHERE email = :email LIMIT 1');
      $stmt->execute(['email' => $email]);
      $user = $stmt->fetch();

      if (!$user || !$user['is_active']) {
        $errors[] = 'Invalid credentials.';
      } else {
        if (password_verify($password, $user['password_hash'])) {
          session_regenerate_id(true);
          $_SESSION['user_id'] = (int)$user['id'];
          $_SESSION['user_name'] = $user['name'];
          $_SESSION['user_email'] = $user['email'];
          $_SESSION['login_time'] = time();

          header('Location: dashboard.php');
          exit;
        } else {
          $errors[] = 'Invalid credentials.';
        }
      }
    } catch (PDOException $e) {
      http_response_code(500);
      echo 'Internal Server Error';
      exit;
    }
  }

  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
} else {
  $input['email'] = '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
</head>
<body>
  <h1>Login</h1>

  <?php if (!empty($errors)): ?>
    <ul>
      <?php foreach ($errors as $err): ?>
        <li><?= htmlspecialchars($err) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <form method="post" action="login.php" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <label>
      Email:
      <input type="email" name="email" value="<?= htmlspecialchars($input['email']) ?>" required>
    </label>
    <br>
    <label>
      Password:
      <input type="password" name="password" required>
    </label>
    <br>
    <button type="submit">Login</button>
  </form>
</body>
</html>
?>