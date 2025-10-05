<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'root');
define('DB_PASS', '');

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        header('Location: login.php?error=empty_fields');
        exit();
    }

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: dashboard.php');
            exit();
        } else {
            header('Location: login.php?error=invalid_credentials');
            exit();
        }
    } catch (PDOException $e) {
        header('Location: login.php?error=db_error');
        exit();
    }
} else {
    if (isset($_GET['error'])) {
        switch ($_GET['error']) {
            case 'invalid_credentials':
                $errorMessage = 'Invalid username or password.';
                break;
            case 'empty_fields':
                $errorMessage = 'Please enter both username and password.';
                break;
            case 'db_error':
                $errorMessage = 'A database error occurred. Please try again later.';
                break;
            case 'not_logged_in':
                $errorMessage = 'Please log in to access that page.';
                break;
            default:
                $errorMessage = 'An unknown error occurred.';
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <div>
        <h2>Login</h2>
        <?php if ($errorMessage): ?>
            <p style="color: red;"><?php echo htmlspecialchars($errorMessage); ?></p>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <button type="submit">Login</button>
            </div>
        </form>
    </div>
</body>
</html>

<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php?error=not_logged_in');
    exit();
}

$username = $_SESSION['username'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <div>
        <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
        <p>This is your secure dashboard.</p>
        <p>You can view products and manage your orders here.</p>
        <p><a href="logout.php">Logout</a></p>
    </div>
</body>
</html>

<?php
session_start();

$_SESSION = array();

session_destroy();

header('Location: login.php?logout=success');
exit();
?>