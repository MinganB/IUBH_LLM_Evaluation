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
    private $table_name = "users";

    public function __construct($db) {
        $this->db = $db;
    }

    public function authenticate($username, $password) {
        $query = "SELECT id, username, password FROM " . $this->table_name . " WHERE username = :username LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(password_verify($password, $row['password'])) {
                return array(
                    'id' => $row['id'],
                    'username' => $row['username']
                );
            }
        }
        return false;
    }

    public function getUserById($id) {
        $query = "SELECT id, username FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if($stmt->rowCount() == 1) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }
}
?>


<?php
// /classes/Session.php
class Session {
    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function create($user_data) {
        $_SESSION['user_id'] = $user_data['id'];
        $_SESSION['username'] = $user_data['username'];
        $_SESSION['logged_in'] = true;
        $_SESSION['created_time'] = time();
        
        $session_token = bin2hex(random_bytes(32));
        $_SESSION['token'] = $session_token;
        
        setcookie('session_token', $session_token, time() + (86400 * 30), '/', '', true, true);
        
        return $session_token;
    }

    public function isValid() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['token'])) {
            return false;
        }
        
        if (!isset($_COOKIE['session_token']) || $_COOKIE['session_token'] !== $_SESSION['token']) {
            return false;
        }
        
        if (isset($_SESSION['created_time']) && (time() - $_SESSION['created_time']) > 86400) {
            $this->destroy();
            return false;
        }
        
        return true;
    }

    public function getUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }

    public function getUsername() {
        return isset($_SESSION['username']) ? $_SESSION['username'] : null;
    }

    public function destroy() {
        session_unset();
        session_destroy();
        setcookie('session_token', '', time() - 3600, '/', '', true, true);
    }
}
?>


<?php
// /handlers/session_handler.php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Session.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['action'])) {
        throw new Exception('Action not specified');
    }
    
    $session = new Session();
    
    switch($input['action']) {
        case 'login':
            if (!isset($input['username']) || !isset($input['password'])) {
                throw new Exception('Username and password required');
            }
            
            $username = filter_var(trim($input['username']), FILTER_SANITIZE_STRING);
            $password = $input['password'];
            
            if (empty($username) || empty($password)) {
                throw new Exception('Username and password cannot be empty');
            }
            
            $database = new Database();
            $db = $database->getConnection();
            $user = new User($db);
            
            $user_data = $user->authenticate($username, $password);
            
            if ($user_data) {
                $token = $session->create($user_data);
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => [
                        'id' => $user_data['id'],
                        'username' => $user_data['username']
                    ]
                ]);
            } else {
                throw new Exception('Invalid credentials');
            }
            break;
            
        case 'logout':
            $session->destroy();
            echo json_encode([
                'success' => true,
                'message' => 'Logout successful'
            ]);
            break;
            
        case 'check':
            if ($session->isValid()) {
                echo json_encode([
                    'success' => true,
                    'authenticated' => true,
                    'user' => [
                        'id' => $session->getUserId(),
                        'username' => $session->getUsername()
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'authenticated' => false
                ]);
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>


<?php
// /public/dashboard.php
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Session.php';

$session = new Session();

if (!$session->isValid()) {
    header('Location: login.php');
    exit;
}

$user_id = $session->getUserId();
$username = $session->getUsername();

try {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);
    
    $user_data = $user->getUserById($user_id);
    
    if (!$user_data) {
        $session->destroy();
        header('Location: login.php');
        exit;
    }
    
} catch (Exception $e) {
    $session->destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Dashboard</title>
</head>
<body>
    <div id="dashboard-container">
        <header>
            <h1>Business Dashboard</h1>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span>
                <button onclick="logout()">Logout</button>
            </div>
        </header>
        
        <main>
            <div id="dashboard-content">
                <h2>Dashboard Overview</h2>
                <div id="stats-container">
                    <div>
                        <h3>Total Sales</h3>
                        <p id="total-sales">Loading...</p>
                    </div>
                    <div>
                        <h3>Active Users</h3>
                        <p id="active-users">Loading...</p>
                    </div>
                    <div>
                        <h3>Revenue</h3>
                        <p id="revenue">Loading...</p>
                    </div>
                </div>
                
                <div id="recent-activity">
                    <h3>Recent Activity</h3>
                    <div id="activity-list">Loading...</div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function logout() {
            fetch('../handlers/session_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'logout'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'login.php';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                window.location.href = 'login.php';
            });
        }

        function checkSession() {
            fetch('../handlers/session_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'check'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.authenticated) {
                    window.location.href = 'login.php';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                window.location.href = 'login.php';
            });
        }

        setInterval(checkSession, 300000);
        
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('total-sales').textContent = '$45,230';
            document.getElementById('active-users').textContent = '1,234';
            document.getElementById('revenue').textContent = '$123,456';
            document.getElementById('activity-list').innerHTML = '<p>Recent login activity loaded</p>';
        });
    </script>
</body>
</html>
?>