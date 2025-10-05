<?php
session_start();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($username === '' || $password === '') {
        header('Location: login.php?error=empty');
        exit;
    }

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbName = $_ENV['DB_NAME'] ?? 'db_users';
    $dbUser = $_ENV['DB_USER'] ?? 'root';
    $dbPass = $_ENV['DB_PASS'] ?? '';

    $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $stmt = $pdo->prepare('SELECT password FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['username'] = $username;
            header('Location: dashboard.php');
            exit;
        } else {
            header('Location: login.php?error=invalid');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: login.php?error=server');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Login</title>
</head>
<body>
<?php
$error = $_GET['error'] ?? '';
if ($error) {
    if ($error === 'empty') {
        echo '<p>Username and password are required.</p>';
    } elseif ($error === 'invalid') {
        echo '<p>Invalid username or password.</p>';
    } elseif ($error === 'server') {
        echo '<p>Server error. Please try again later.</p>';
    } else {
        echo '<p>An error occurred.</p>';
    }
}
?>
<form action="login.php" method="POST">
  <label for="username">Username</label>
  <input type="text" id="username" name="username" autocomplete="username" required>
  <br>
  <label for="password">Password</label>
  <input type="password" id="password" name="password" autocomplete="current-password" required>
  <br>
  <button type="submit">Login</button>
</form>
</body>
</html>
?>