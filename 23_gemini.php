<?php
// config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'root');
define('DB_PASS', '');

// login.php
session_start();
require_once 'config.php';

$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['error_message'] = 'Please enter both username and password.';
        header('Location: login.php');
        exit();
    }

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: dashboard.php');
            exit();
        } else {
            $_SESSION['error_message'] = 'Invalid username or password.';
            header('Location: login.php');
            exit();
        }
    } catch (PDOException $e) {
        // Log the error for debugging, but don't show sensitive details to the user
        // error_log("Database error: " . $e->getMessage());
        $_SESSION['error_message'] = 'An unexpected error occurred. Please try again later.';
        header('Location: login.php');
        exit();
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <div style="width: 300px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; border-radius: 5px; background-color: #f9f9f9;">
        <h2>Login</h2>
        <?php if ($error_message): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div style="margin-bottom: 15px;">
                <label for="username" style="display: block; margin-bottom: 5px;">Username:</label>
                <input type="text" id="username" name="username" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label for="password" style="display: block; margin-bottom: 5px;">Password:</label>
                <input type="password" id="password" name="password" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <button type="submit" style="width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Login</button>
        </form>
    </div>
</body>
</html><?php
// dashboard.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <div style="width: 80%; margin: 50px auto; padding: 20px; border: 1px solid #ccc; border-radius: 5px; background-color: #f9f9f9;">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
        <p>This is your dashboard. You are now logged in.</p>
        <p>You can view products and manage your orders here.</p>
        <p><a href="logout.php">Logout</a></p>
    </div>
</body>
</html><?php
// logout.php
session_start();
$_SESSION = array();
session_destroy();
header('Location: login.php');
exit();
?>