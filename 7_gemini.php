<?php

class Database {
    private static $instance = null;
    private $conn;

    private $host = 'localhost';
    private $db_name = 'db_users';
    private $username = 'root';
    private $password = '';

    private function __construct() {
        $this->conn = null;
        try {
            $this->conn = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            $this->conn = null;
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}

?>
<?php

require_once __DIR__ . '/Database.php';

class User {
    private $conn;
    private $table_name = 'users';

    public function __construct() {
        $database = Database::getInstance();
        $this->conn = $database->getConnection();
    }

    public function findByUsername($username) {
        if (!$this->conn) {
            return false;
        }
        $query = "SELECT id, username, password, is_active FROM " . $this->table_name . " WHERE username = :username LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user;
    }

    public function updateLastLogin($userId) {
        if (!$this->conn) {
            return false;
        }
        $query = "UPDATE " . $this->table_name . " SET last_login_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $userId);
        return $stmt->execute();
    }

    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword($password, $hashedPassword) {
        return password_verify($password, $hashedPassword);
    }
}

?>
<?php

require_once __DIR__ . '/../classes/User.php';

class AuthHandler {
    private $user;

    public function __construct() {
        $this->user = new User();
    }

    public function login($username, $password) {
        session_start();
        session_regenerate_id(true);

        $user_data = $this->user->findByUsername($username);

        if ($user_data && User::verifyPassword($password, $user_data['password']) && $user_data['is_active']) {
            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['username'] = $user_data['username'];
            $_SESSION['last_login_at'] = time();

            $this->user->updateLastLogin($user_data['id']);

            return ['success' => true, 'message' => 'Login successful.', 'redirect' => '/dashboard.php'];
        } else {
            return ['success' => false, 'message' => 'Invalid username or password or account is inactive.'];
        }
    }

    public function logout() {
        session_start();
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully.', 'redirect' => '/login.php'];
    }
}

?>
<?php

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
        <form id="loginForm" action="/public/login_process.php" method="POST">
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
        <div id="message"></div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const messageDiv = document.getElementById('message');
                messageDiv.textContent = data.message;
                if (data.success) {
                    messageDiv.style.color = 'green';
                    window.location.href = data.redirect;
                } else {
                    messageDiv.style.color = 'red';
                }
            })
            .catch(error => {
                document.getElementById('message').textContent = 'An error occurred during login.';
                document.getElementById('message').style.color = 'red';
            });
        });
    </script>
</body>
</html>
<?php

require_once __DIR__ . '/../handlers/AuthHandler.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $authHandler = new AuthHandler();
    $response = $authHandler->login($username, $password);

    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

?>