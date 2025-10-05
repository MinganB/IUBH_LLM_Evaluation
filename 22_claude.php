<?php
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <div>
        <h2>Login</h2>
        <?php
        session_start();
        if (isset($_SESSION['error'])) {
            echo '<div style="color: red;">' . $_SESSION['error'] . '</div>';
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
            <div>
                <button type="submit">Login</button>
            </div>
        </form>
    </div>
</body>
</html>


<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['error'] = 'Please enter both username and password.';
    header('Location: index.php');
    exit();
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=db_users;charset=utf8mb4', 'username', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;
        header('Location: dashboard.php');
        exit();
    } else {
        $_SESSION['error'] = 'Invalid username or password.';
        header('Location: index.php');
        exit();
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database connection failed. Please try again.';
    header('Location: index.php');
    exit();
}
?>


<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <div>
        <h1>Welcome to Dashboard</h1>
        <p>Hello, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
        <nav>
            <ul>
                <li><a href="products.php">View Products</a></li>
                <li><a href="orders.php">Manage Orders</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
        <div>
            <h2>Dashboard Content</h2>
            <p>This is your e-commerce dashboard where you can manage your account and orders.</p>
        </div>
    </div>
</body>
</html>


<?php
session_start();
session_destroy();
header('Location: index.php');
exit();
?>


sql
CREATE DATABASE IF NOT EXISTS db_users;

USE db_users;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com'),
('user1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user1@example.com');
?>