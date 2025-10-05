<?php
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
        <?php
        session_start();
        if (isset($_SESSION['error'])) {
            echo '<div>' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        ?>
        <form method="POST" action="login.php">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>


<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['error'] = 'Username and password are required.';
    header('Location: login.html');
    exit;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=db_users;charset=utf8mb4', 'username', 'password', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;
        
        header('Location: dashboard.php');
        exit;
    } else {
        $_SESSION['error'] = 'Invalid username or password.';
        header('Location: login.html');
        exit;
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database connection failed. Please try again later.';
    header('Location: login.html');
    exit;
}
?>


<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.html');
    exit;
}
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
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <nav>
            <ul>
                <li><a href="products.php">View Products</a></li>
                <li><a href="orders.php">My Orders</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
        <div>
            <h2>Dashboard</h2>
            <p>You have successfully logged in to your account.</p>
        </div>
    </div>
</body>
</html>


<?php
session_start();
session_destroy();
header('Location: login.html');
exit;
?>


sql
CREATE DATABASE IF NOT EXISTS db_users CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE db_users;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
);
?>