<?php
session_start();

$host = getenv('DB_HOST') ?: '127.0.0.1';
$dbname = getenv('DB_NAME') ?: 'authdb';
$dbUser = getenv('DB_USER') ?: 'dbuser';
$dbPass = getenv('DB_PASS') ?: 'dbpass';
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

$loggedIn = !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($username === '' || $password === '') {
        header('Location: login.php?error=empty');
        exit;
    }

    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1');
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['logged_in'] = true;
            header('Location: dashboard.php');
            exit;
        } else {
            header('Location: login.php?error=invalid');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: login.php?error=unavailable');
        exit;
    }
}

if ($loggedIn) {
    header('Location: dashboard.php');
    exit;
}

$errorMessage = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid':
            $errorMessage = 'Invalid username or password.';
            break;
        case 'empty':
            $errorMessage = 'Please enter username and password.';
            break;
        case 'unavailable':
            $errorMessage = 'Authentication service is unavailable. Please try again later.';
            break;
        default:
            $errorMessage = 'An error occurred. Please try again.';
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Login</title>
</head>
<body>
<?php if ($errorMessage): ?>
<div><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>
<form action="login.php" method="post" autocomplete="on">
  <div>
    <label for="username">Username</label>
    <input type="text" id="username" name="username" required autofocus />
  </div>
  <div>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required />
  </div>
  <div>
    <button type="submit">Login</button>
  </div>
</form>
</body>
</html>
?>