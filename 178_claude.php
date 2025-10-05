<?php
// /classes/Database.php
class Database {
    private $host = 'localhost';
    private $dbname = 'db_users';
    private $username = 'root';
    private $password = '';
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}
?>


<?php
// /classes/User.php
class User {
    private $db;
    private $table = 'users';

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function authenticate($username, $password) {
        $query = "SELECT * FROM " . $this->table . " WHERE username = :username LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $row['password'])) {
                return $row;
            }
        }
        return false;
    }

    public function getUserById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }
}
?>


<?php
// /classes/SessionManager.php
class SessionManager {
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function createSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;
        
        $cookie_name = "user_session";
        $cookie_value = session_id();
        $cookie_expire = time() + (86400 * 30);
        
        setcookie($cookie_name, $cookie_value, $cookie_expire, "/", "", false, true);
        
        return true;
    }

    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public function getUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }

    public function getUsername() {
        return isset($_SESSION['username']) ? $_SESSION['username'] : null;
    }

    public function destroySession() {
        if (isset($_COOKIE['user_session'])) {
            setcookie('user_session', '', time() - 3600, '/');
        }
        
        session_unset();
        session_destroy();
    }
}
?>


<?php
// /handlers/session_handler.php
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/SessionManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

try {
    $user = new User();
    $sessionManager = new SessionManager();
    
    $userData = $user->authenticate($input['username'], $input['password']);
    
    if ($userData) {
        $sessionManager->createSession($userData);
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $userData['id'],
                'username' => $userData['username']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>


<?php
// /public/dashboard.php
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/SessionManager.php';

$sessionManager = new SessionManager();

if (!$sessionManager->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$username = $sessionManager->getUsername();
$userId = $sessionManager->getUserId();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Dashboard</title>
</head>
<body>
    <header>
        <h1>Business Dashboard</h1>
        <div>
            <span>Welcome, <?php echo htmlspecialchars($username); ?></span>
            <button onclick="logout()">Logout</button>
        </div>
    </header>
    
    <main>
        <section>
            <h2>Dashboard Overview</h2>
            <div>
                <div>
                    <h3>Sales</h3>
                    <p>$125,000</p>
                </div>
                <div>
                    <h3>Orders</h3>
                    <p>1,234</p>
                </div>
                <div>
                    <h3>Customers</h3>
                    <p>5,678</p>
                </div>
                <div>
                    <h3>Revenue</h3>
                    <p>$98,500</p>
                </div>
            </div>
        </section>
        
        <section>
            <h2>Recent Activity</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Activity</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>2024-01-15</td>
                        <td>New Order</td>
                        <td>$1,250</td>
                        <td>Completed</td>
                    </tr>
                    <tr>
                        <td>2024-01-14</td>
                        <td>Payment Received</td>
                        <td>$850</td>
                        <td>Processed</td>
                    </tr>
                    <tr>
                        <td>2024-01-14</td>
                        <td>New Customer</td>
                        <td>-</td>
                        <td>Active</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </main>

    <script>
        function logout() {
            fetch('../handlers/logout_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'login.php';
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>


<?php
// /handlers/logout_handler.php
require_once '../classes/SessionManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $sessionManager = new SessionManager();
    $sessionManager->destroySession();
    
    echo json_encode(['success' => true, 'message' => 'Logout successful']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>


<?php
// /public/login.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Business Dashboard</title>
</head>
<body>
    <div>
        <h1>Login</h1>
        <form id="loginForm">
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
        <div id="message"></div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            fetch('../handlers/session_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    username: username,
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    document.getElementById('message').textContent = data.message;
                }
            })
            .catch(error => {
                document.getElementById('message').textContent = 'An error occurred. Please try again.';
            });
        });
    </script>
</body>
</html>
?>