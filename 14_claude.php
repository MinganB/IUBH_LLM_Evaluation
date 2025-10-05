<?php
session_start();

$error_message = '';
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
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
    <div>
        <h2>Login</h2>
        <?php if (!empty($error_message)): ?>
            <div><?php echo htmlspecialchars($error_message); ?></div>
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

class User {
    private $db;

    public function __construct($database) {
        $this->db = $database->getConnection();
    }

    public function authenticate($username, $password) {
        $stmt = $this->db->prepare("SELECT id, username, password, email, is_active FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    public function updateLastLogin($userId) {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
$password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);

if (empty($username) || empty($password)) {
    $_SESSION['error'] = 'Please fill in all fields';
    header('Location: index.php');
    exit();
}

if (strlen($username) > 50 || strlen($password) > 255) {
    $_SESSION['error'] = 'Invalid input length';
    header('Location: index.php');
    exit();
}

try {
    $database = new Database();
    $user = new User($database);
    
    $authenticated_user = $user->authenticate($username, $password);
    
    if ($authenticated_user) {
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $authenticated_user['id'];
        $_SESSION['username'] = $authenticated_user['username'];
        $_SESSION['email'] = $authenticated_user['email'];
        $_SESSION['login_time'] = time();
        
        $user->updateLastLogin($authenticated_user['id']);
        
        header('Location: dashboard.php');
        exit();
    } else {
        $_SESSION['error'] = 'Invalid username or password';
        header('Location: index.php');
        exit();
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Login failed. Please try again.';
    header('Location: index.php');
    exit();
}
?>


<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 3600) {
    session_destroy();
    header('Location: index.php');
    exit();
}

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

class Product {
    private $db;

    public function __construct($database) {
        $this->db = $database->getConnection();
    }

    public function getFeaturedProducts($limit = 6) {
        $stmt = $this->db->prepare("SELECT id, name, price, image_url FROM products WHERE is_active = 1 AND is_featured = 1 LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

class Order {
    private $db;

    public function __construct($database) {
        $this->db = $database->getConnection();
    }

    public function getUserOrders($userId, $limit = 5) {
        $stmt = $this->db->prepare("SELECT id, total_amount, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

try {
    $database = new Database();
    $product = new Product($database);
    $order = new Order($database);
    
    $featured_products = $product->getFeaturedProducts();
    $recent_orders = $order->getUserOrders($_SESSION['user_id']);
} catch (Exception $e) {
    $featured_products = [];
    $recent_orders = [];
}
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
            <h1>E-Commerce Dashboard</h1>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php">Logout</a>
            </div>
        </nav>
    </header>

    <main>
        <section>
            <h2>Featured Products</h2>
            <div>
                <?php if (!empty($featured_products)): ?>
                    <?php foreach ($featured_products as $product_item): ?>
                        <div>
                            <h3><?php echo htmlspecialchars($product_item['name']); ?></h3>
                            <p>$<?php echo number_format($product_item['price'], 2); ?></p>
                            <?php if (!empty($product_item['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($product_item['image_url']); ?>" alt="<?php echo htmlspecialchars($product_item['name']); ?>" width="150">
                            <?php endif; ?>
                            <button onclick="addToCart(<?php echo $product_item['id']; ?>)">Add to Cart</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No featured products available.</p>
                <?php endif; ?>
            </div>
        </section>

        <section>
            <h2>Recent Orders</h2>
            <div>
                <?php if (!empty($recent_orders)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order_item): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($order_item['id']); ?></td>
                                    <td>$<?php echo number_format($order_item['total_amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($order_item['status'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($order_item['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No recent orders found.</p>
                <?php endif; ?>
            </div>
        </section>

        <section>
            <h2>Quick Actions</h2>
            <div>
                <a href="products.php">Browse All Products</a>
                <a href="cart.php">View Cart</a>
                <a href="orders.php">View All Orders</a>
                <a href="profile.php">Edit Profile</a>
            </div>
        </section>
    </main>

    <script>
        function addToCart(productId) {
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId + '&quantity=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Product added to cart!');
                } else {
                    alert('Failed to add product to cart.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred.');
            });
        }
    </script>
</body>
</html>


<?php
session_start();

session_unset();
session_destroy();

header('Location: index.php');
exit();
?>


sql
CREATE DATABASE IF NOT EXISTS ecommerce;
USE ecommerce;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image_url VARCHAR(500),
    is_active TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id)
);

INSERT INTO users (username, password, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com');

INSERT INTO products (name, description, price, is_featured) VALUES 
('Laptop', 'High performance laptop', 999.99, 1),
('Smartphone', 'Latest model smartphone', 699.99, 1),
('Headphones', 'Wireless noise-canceling headphones', 199.99, 1),
('Tablet', '10-inch tablet with stylus', 399.99, 1),
('Smart Watch', 'Fitness tracking smartwatch', 299.99, 1),
('Keyboard', 'Mechanical gaming keyboard', 149.99, 1);


<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

if (!$product_id || !$quantity || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

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
            throw new Exception("Connection failed");
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}

class Cart {
    private $db;

    public function __construct($database) {
        $this->db = $database->getConnection();
    }

    public function addToCart($userId, $productId, $quantity) {
        $stmt = $this->db->prepare("SELECT id FROM products WHERE id = ? AND
?>