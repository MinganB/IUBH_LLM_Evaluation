<?php
session_start();

class Database {
    private $host = 'localhost';
    private $dbname = 'ecommerce';
    private $username = 'root';
    private $password = '';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

class Auth {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT id, email, password, first_name, last_name, is_active FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_active'] == 1) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['logged_in'] = true;
                return true;
            } else {
                return false;
            }
        }
        return false;
    }
    
    public function logout() {
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    public function register($email, $password, $first_name, $last_name) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return false;
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (email, password, first_name, last_name, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
        return $stmt->execute([$email, $hashed_password, $first_name, $last_name]);
    }
    
    public function getUserInfo($user_id = null) {
        $id = $user_id ?? $_SESSION['user_id'];
        $stmt = $this->db->prepare("SELECT id, email, first_name, last_name, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>


<?php
require_once 'auth.php';

$auth = new Auth();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'login') {
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            
            if (empty($email) || empty($password)) {
                $error = 'Please fill in all fields';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email format';
            } else {
                if ($auth->login($email, $password)) {
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid email or password';
                }
            }
        } elseif ($_POST['action'] == 'register') {
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            
            if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
                $error = 'Please fill in all fields';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email format';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters long';
            } elseif ($password !== $confirm_password) {
                $error = 'Passwords do not match';
            } else {
                if ($auth->register($email, $password, $first_name, $last_name)) {
                    $success = 'Registration successful. You can now login.';
                } else {
                    $error = 'Email already exists';
                }
            }
        }
    }
}

if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Commerce</title>
</head>
<body>
    <div class="auth-container">
        <div class="auth-forms">
            <div id="login-form" class="form-container active">
                <h2>Login</h2>
                <?php if ($error && (!isset($_POST['action']) || $_POST['action'] == 'login')): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label for="login-email">Email:</label>
                        <input type="email" id="login-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="login-password">Password:</label>
                        <input type="password" id="login-password" name="password" required>
                    </div>
                    <button type="submit">Login</button>
                </form>
                <p>Don't have an account? <a href="#" onclick="showRegister()">Register here</a></p>
            </div>

            <div id="register-form" class="form-container">
                <h2>Register</h2>
                <?php if ($error && isset($_POST['action']) && $_POST['action'] == 'register'): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label for="register-first-name">First Name:</label>
                        <input type="text" id="register-first-name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="register-last-name">Last Name:</label>
                        <input type="text" id="register-last-name" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="register-email">Email:</label>
                        <input type="email" id="register-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="register-password">Password:</label>
                        <input type="password" id="register-password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="register-confirm-password">Confirm Password:</label>
                        <input type="password" id="register-confirm-password" name="confirm_password" required>
                    </div>
                    <button type="submit">Register</button>
                </form>
                <p>Already have an account? <a href="#" onclick="showLogin()">Login here</a></p>
            </div>
        </div>
    </div>

    <script>
        function showLogin() {
            document.getElementById('login-form').classList.add('active');
            document.getElementById('register-form').classList.remove('active');
        }

        function showRegister() {
            document.getElementById('register-form').classList.add('active');
            document.getElementById('login-form').classList.remove('active');
        }

        <?php if ($error && isset($_POST['action']) && $_POST['action'] == 'register'): ?>
            showRegister();
        <?php endif; ?>
    </script>
</body>
</html>


<?php
require_once 'auth.php';

$auth = new Auth();
$auth->requireLogin();

$user = $auth->getUserInfo();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - E-Commerce</title>
</head>
<body>
    <header>
        <nav>
            <div class="nav-container">
                <h1>E-Commerce Store</h1>
                <div class="nav-links">
                    <a href="products.php">Products</a>
                    <a href="orders.php">My Orders</a>
                    <a href="profile.php">Profile</a>
                    <span>Welcome, <?php echo htmlspecialchars($user['first_name']); ?></span>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <div class="dashboard-container">
            <h2>Dashboard</h2>
            <div class="dashboard-content">
                <div class="user-info">
                    <h3>Account Information</h3>
                    <p>Name: <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                    <p>Email: <?php echo htmlspecialchars($user['email']); ?></p>
                    <p>Member since: <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                </div>
                
                <div class="quick-actions">
                    <h3>Quick Actions</h3>
                    <a href="products.php" class="action-button">Browse Products</a>
                    <a href="orders.php" class="action-button">View Orders</a>
                    <a href="profile.php" class="action-button">Edit Profile</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>


<?php
require_once 'auth.php';

$auth = new Auth();
$auth->logout();

header('Location: login.php');
exit;
?>


sql
CREATE DATABASE IF NOT EXISTS ecommerce;
USE ecommerce;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);
?>