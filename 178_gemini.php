<?php
// classes/Database.php
class Database {
    private $host = 'localhost'; // Replace with your DB host
    private $db_name = 'db_users';
    private $username = 'root'; // Replace with your DB user
    private $password = ''; // Replace with your DB password
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            throw new Exception("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}

<?php
// classes/User.php
require_once __DIR__ . '/Database.php';

class User {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function authenticate($username_input, $password_input) {
        $username_input = htmlspecialchars(strip_tags($username_input));

        $query = "SELECT id, username, password FROM " . $this->table_name . " WHERE username = :username LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username_input);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $hashed_password_from_db = $row['password'];

            if (password_verify($password_input, $hashed_password_from_db)) {
                return [
                    'id' => $row['id'],
                    'username' => $row['username']
                ];
            }
        }
        return false;
    }
}

<?php
// handlers/session_handler.php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Only POST requests are accepted.']);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['username']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing username or password.']);
    exit();
}

$username = $data['username'];
$password = $data['password'];

try {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);

    $authenticated_user_data = $user->authenticate($username, $password);

    if ($authenticated_user_data) {
        $_SESSION['user_id'] = $authenticated_user_data['id'];
        $_SESSION['username'] = $authenticated_user_data['username'];
        
        session_regenerate_id(true);

        echo json_encode(['success' => true, 'message' => 'Login successful', 'redirect' => '/public/dashboard.php']);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

<?php
// public/dashboard.php
session_start();

header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'Guest';

    echo json_encode([
        'success' => true,
        'message' => 'Welcome to the business dashboard!',
        'user' => [
            'id' => $userId,
            'username' => $username
        ],
        'dashboard_data' => [
            'total_sales' => '$15,000',
            'new_customers' => 120,
            'pending_tasks' => 5,
            'recent_activity' => [
                'Logged in successfully',
                'Report generated: Q3-2023',
                'New order placed by customer #101'
            ]
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in to view the dashboard.']);
}
?>