<?php

class DB {
    private static $instance = null;
    private $pdo;

    private const DB_HOST = 'localhost';
    private const DB_NAME = 'db_users';
    private const DB_USER = 'root';
    private const DB_PASS = '';

    private function __construct() {
        $dsn = 'mysql:host=' . self::DB_HOST . ';dbname=' . self::DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->pdo = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            exit('Database connection failed.');
        }
    }

    public static function getInstance(): DB {
        if (self::$instance === null) {
            self::$instance = new DB();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }
}

?>
<?php

require_once __DIR__ . '/DB.php';

class User {
    private $pdo;

    public function __construct() {
        $this->pdo = DB::getInstance()->getConnection();
    }

    public function findByUsername(string $username): ?array {
        $stmt = $this->pdo->prepare('SELECT id, username, password FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $userId): ?array {
        $stmt = $this->pdo->prepare('SELECT id, username FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function verifyPassword(string $inputPassword, string $hashedPassword): bool {
        return password_verify($inputPassword, $hashedPassword);
    }
}

?>
<?php

class Session {
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            $this->configureSession();
            session_start();
        }
    }

    private function configureSession(): void {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.sid_length', 48);
        ini_set('session.sid_bits_per_character', 6);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.gc_maxlifetime', 1440);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    public function regenerateId(): void {
        session_regenerate_id(true);
    }

    public function set(string $key, $value): void {
        $_SESSION[$key] = $value;
    }

    public function get(string $key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    public function remove(string $key): void {
        unset($_SESSION[$key]);
    }

    public function destroy(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax'
            ]);
        }
        session_destroy();
    }

    public function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }
}

?>
<?php

require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit();
}

$userHandler = new User();
$user = $userHandler->findByUsername($username);

if ($user && $userHandler->verifyPassword($password, $user['password'])) {
    $session = new Session();
    $session->regenerateId();
    $session->set('user_id', $user['id']);

    echo json_encode(['success' => true, 'message' => 'Login successful.']);
    exit();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    exit();
}

?>
<?php

require_once __DIR__ . '/../classes/Session.php';
require_once __DIR__ . '/../classes/User.php';

header('Content-Type: application/json');

$session = new Session();

if (!$session->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You are not logged in.']);
    exit();
}

$userId = $session->get('user_id');
$userHandler = new User();
$user = $userHandler->findById($userId);

if (!$user) {
    $session->destroy();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not found, session terminated.']);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => 'Welcome to the dashboard!',
    'user_info' => [
        'id' => $user['id'],
        'username' => $user['username']
    ],
    'dashboard_data' => [
        'metric1' => 1234,
        'metric2' => 5678,
        'recent_activity' => [
            'item A', 'item B', 'item C'
        ]
    ]
]);
exit();

?>