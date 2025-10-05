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
        $errorMessage = 'Please enter both username and password.';
    } else {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT id, username, password, is_active FROM users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && $user['is_active'] && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];

                $updateStmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
                $updateStmt->execute(['id' => $user['id']]);

                header('Location: dashboard.php');
                exit();
            } else {
                $errorMessage = 'Invalid username or password, or account is inactive.';
            }

        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $errorMessage = 'An internal error occurred. Please try again later.';
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
    <h2>Login</h2>

    <?php if (!empty($errorMessage)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($errorMessage); ?></p>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <label for="username">Username:</label><br>
        <input type="text" id="username" name="username" required><br><br>

        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required><br><br>

        <input type="submit" value="Login">
    </form>
</body>
</html>

<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$username = htmlspecialchars($_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <h2>Welcome, <?php echo $username; ?>!</h2>
    <p>This is your dashboard. You are logged in.</p>
    <p><a href="logout.php">Logout</a></p>
</body>
</html>

<?php
session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit();
?>