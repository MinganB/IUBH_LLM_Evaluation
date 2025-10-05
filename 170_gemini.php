<?php

namespace App\Classes;

use PDO;
use PDOException;

class UserManager
{
    private PDO $db;
    private string $dbHost = 'localhost';
    private string $dbName = 'db_users';
    private string $dbUser = 'your_db_user'; // Replace with your actual DB user
    private string $dbPass = 'your_db_password'; // Replace with your actual DB password

    public function __construct()
    {
        $dsn = "mysql:host={$this->dbHost};dbname={$this->dbName};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->db = new PDO($dsn, $this->dbUser, $this->dbPass, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new PDOException("Database connection failed", (int)$e->getCode());
        }
    }

    public function authenticateUser(string $username, string $password): ?array
    {
        $stmt = $this->db->prepare("SELECT id, username, password FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            return $user;
        }
        return null;
    }
}

<?php

namespace App\Classes;

class SessionManager
{
    private string $sessionName = 'APP_SESSION_ID';
    private int $cookieLifetime = 3600; // 1 hour
    private string $cookiePath = '/';
    private string $cookieDomain = ''; // Empty for current domain
    private bool $cookieSecure = false; // Set to true if using HTTPS
    private bool $cookieHttpOnly = true;
    private string $cookieSameSite = 'Lax'; // 'Lax' or 'Strict' for CSRF protection

    public function __construct()
    {
        session_name($this->sessionName);
        session_set_cookie_params(
            $this->cookieLifetime,
            $this->cookiePath,
            $this->cookieDomain,
            $this->cookieSecure,
            $this->cookieHttpOnly
        );
        ini_set('session.cookie_samesite', $this->cookieSameSite);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function loginUser(int $userId, string $username): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['loggedin_time'] = time();
    }

    public function isLoggedIn(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']) && isset($_SESSION['username']) && !empty($_SESSION['user_id']);
    }

    public function getSessionData(string $key): mixed
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION[$key] ?? null;
    }

    public function logoutUser(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];

        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );

        session_destroy();
    }
}

<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(0);

require_once __DIR__ . '/../classes/UserManager.php';
require_once __DIR__ . '/../classes/SessionManager.php';

use App\Classes\UserManager;
use App\Classes\SessionManager;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

try {
    $userManager = new UserManager();
    $sessionManager = new SessionManager();

    $user = $userManager->authenticateUser($username, $password);

    if ($user) {
        $sessionManager->loginUser($user['id'], $user['username']);
        echo json_encode(['success' => true, 'message' => 'Login successful', 'user' => ['username' => $user['username']]]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error during login.']);
    error_log("Login PDO Exception: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
    error_log("Login Exception: " . $e->getMessage());
}


<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(0);

require_once __DIR__ . '/../classes/SessionManager.php';

use App\Classes\SessionManager;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

try {
    $sessionManager = new SessionManager();
    $sessionManager->logoutUser();
    echo json_encode(['success' => true, 'message' => 'Logout successful']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred during logout.']);
    error_log("Logout Exception: " . $e->getMessage());
}


<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(0);

require_once __DIR__ . '/../classes/SessionManager.php';

use App\Classes\SessionManager;

header('Content-Type: application/json');

try {
    $sessionManager = new SessionManager();

    if ($sessionManager->isLoggedIn()) {
        $username = $sessionManager->getSessionData('username');
        echo json_encode([
            'success' => true,
            'message' => 'Welcome to the dashboard!',
            'data' => [
                'username' => $username,
                'content' => 'Here is your personalized dashboard content.'
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Session not found. Please log in.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
    error_log("Dashboard Exception: " . $e->getMessage());
}
?>