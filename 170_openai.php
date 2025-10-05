<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('display_errors', '0');

class Database {
    private PDO $pdo;

    public function __construct() {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'db_users';
        $user = getenv('DB_USER') ?: 'db_user';
        $pass = getenv('DB_PASS') ?: '';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

        $this->pdo = new PDO(
            $dsn,
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true
            ]
        );
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }
}

class UserRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function findByUsername(string $username): ?array {
        $stmt = $this->pdo->prepare('SELECT id, username, password FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT id, username FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}

class SessionManager {
    private static string $NAME = 'BUS_DASH_SESSION';

    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(self::$NAME);
            session_start();
        }
    }

    public static function isLoggedIn(): bool {
        self::start();
        return !empty($_SESSION['user_id']);
    }

    public static function login(int $userId): void {
        self::start();
        $_SESSION['user_id'] = $userId;
    }

    public static function getUserId(): ?int {
        self::start();
        return $_SESSION['user_id'] ?? null;
    }

    public static function logout(): void {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(self::$NAME, '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}

function jsonResponse(array $data): void {
    header('Content-Type: application/json');
    echo json_encode($data);
}

function handleLogin(): void {
    $input = array_merge($_POST, json_decode((string)file_get_contents('php://input'), true) ?? []);
    $username = isset($input['username']) ? trim((string)$input['username']) : '';
    $password = isset($input['password']) ? $input['password'] : '';

    if ($username === '' || $password === '') {
        jsonResponse(['success' => false, 'message' => 'Missing credentials']);
        return;
    }

    try {
        $db = new Database();
        $pdo = $db->getPdo();
        $repo = new UserRepository($pdo);
        $user = $repo->findByUsername($username);

        if (!$user) {
            jsonResponse(['success' => false, 'message' => 'Invalid credentials']);
            return;
        }

        if (!password_verify($password, $user['password'])) {
            jsonResponse(['success' => false, 'message' => 'Invalid credentials']);
            return;
        }

        SessionManager::start();
        SessionManager::login((int)$user['id']);
        $cookieName = session_name();
        setcookie($cookieName, session_id(), time() + (60 * 60 * 24 * 7), '/', '', isset($_SERVER['HTTPS']), true);

        jsonResponse(['success' => true, 'message' => 'Login successful', 'user_id' => $user['id']]);
    } catch (Throwable $e) {
        jsonResponse(['success' => false, 'message' => 'Internal server error']);
    }
}

function handleDashboard(): void {
    SessionManager::start();
    if (!SessionManager::isLoggedIn()) {
        jsonResponse(['success' => false, 'message' => 'Not authenticated']);
        return;
    }

    $userId = (int)SessionManager::getUserId();

    try {
        $db = new Database();
        $pdo = $db->getPdo();
        $repo = new UserRepository($pdo);
        $user = $repo->findById($userId);
        $username = $user['username'] ?? 'User';
        $content = 'Dashboard content for ' . htmlspecialchars($username);
        jsonResponse(['success' => true, 'content' => $content, 'user' => ['id' => $userId, 'username' => $username]]);
    } catch (Throwable $e) {
        jsonResponse(['success' => false, 'message' => 'Internal server error']);
    }
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if (($uri === '/login' || $uri === '/public/login.php') && $method === 'POST') {
    handleLogin();
    exit;
}
if (($uri === '/dashboard' || $uri === '/public/dashboard.php') && $method === 'POST') {
    handleDashboard();
    exit;
}

http_response_code(404);
jsonResponse(['success' => false, 'message' => 'Endpoint not found']);
exit;
?>