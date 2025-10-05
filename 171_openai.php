<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST requests only']);
    exit;
}

class Database {
    private $pdo;

    public function __construct() {
        $host = (string) getenv('DB_HOST') ?: '127.0.0.1';
        $db   = (string) getenv('DB_NAME') ?: 'db_users';
        $user = (string) getenv('DB_USER') ?: 'db_user';
        $pass = (string) getenv('DB_PASS') ?: 'db_pass';
        $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        $this->pdo = new PDO($dsn, $user, $pass, $options);
        $this->ensureSchema();
    }

    public function prepare(string $sql): PDOStatement {
        return $this->pdo->prepare($sql);
    }

    private function ensureSchema(): void {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    username VARCHAR(255) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL
                ) ENGINE=InnoDB;
            ");
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS sessions (
                    session_id VARCHAR(128) PRIMARY KEY,
                    user_id INT NOT NULL,
                    expires_at INT NOT NULL,
                    last_activity INT NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB;
            ");
        } catch (Exception $e) {
            // In production, handle gracefully
        }
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }
}

class SessionManager {
    const COOKIE_NAME = 'BD_SESSION';
    const LIFETIME_SECONDS = 60 * 60 * 24; // 24 hours

    private static function getDb(): Database {
        static $db;
        if (!$db) {
            $db = new Database();
        }
        return $db;
    }

    private static function getCookieSecure(): bool {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    }

    private static function logEvent(int $userId, string $action): void {
        $message = sprintf("%s | UserID: %d | Action: %s\n", date('Y-m-d H:i:s'), $userId, $action);
        $paths = [
            '/var/log/bd_session.log',
            __DIR__ . '/logs/bd_session.log',
            sys_get_temp_dir() . '/bd_session.log'
        ];
        foreach ($paths as $path) {
            if (is_writable(dirname($path)) || is_writable($path)) {
                file_put_contents($path, $message, FILE_APPEND | FILE_EXLOCK);
                return;
            }
        }
        // If none writable, attempt fallback to syslog
        if (PHP_VERSION_ID >= 70000) {
            openlog('bd_session', LOG_PID | LOG_PERROR, LOG_AUTH);
            syslog(LOG_INFO, trim($message));
            closelog();
        }
    }

    public static function destroyAllSessionsForUser(int $userId): void {
        $db = self::getDb();
        $stmt = $db->prepare('DELETE FROM sessions WHERE user_id = ?');
        $stmt->execute([$userId]);
        self::logEvent($userId, 'SESSIONS_DESTROYED');
    }

    public static function generateSessionId(): string {
        return bin2hex(random_bytes(32));
    }

    public static function createSession(int $userId): string {
        $db = self::getDb();
        $sessionId = self::generateSessionId();
        $expiresAt = time() + self::LIFETIME_SECONDS;

        $stmt = $db->prepare('INSERT INTO sessions (session_id, user_id, expires_at, last_activity) VALUES (?, ?, ?, ?)');
        $stmt->execute([$sessionId, $userId, $expiresAt, time()]);

        $cookieSecure = self::getCookieSecure();
        setcookie(self::COOKIE_NAME, $sessionId, $expiresAt, '/', '', $cookieSecure, true);

        self::logEvent($userId, 'SESSION_CREATED');
        return $sessionId;
    }

    public static function validateSession(): array {
        $db = self::getDb();
        $sessionId = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!$sessionId) {
            return ['valid' => false];
        }

        $stmt = $db->prepare('SELECT user_id, expires_at FROM sessions WHERE session_id = ?');
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['valid' => false];
        }

        $now = time();
        if ($row['expires_at'] <= $now) {
            self::destroySession($sessionId, (int)$row['user_id']);
            self::logEvent((int)$row['user_id'], 'SESSION_EXPIRED');
            return ['valid' => false];
        }

        $stmt2 = $db->prepare('UPDATE sessions SET last_activity = ? WHERE session_id = ?');
        $stmt2->execute([time(), $sessionId]);

        return ['valid' => true, 'user_id' => (int)$row['user_id'], 'session_id' => $sessionId];
    }

    public static function destroySession(string $sessionId, ?int $userId = null): void {
        $db = self::getDb();
        if ($userId === null) {
            $stmt = $db->prepare('SELECT user_id FROM sessions WHERE session_id = ?');
            $stmt->execute([$sessionId]);
            $row = $stmt->fetch();
            if ($row) {
                $userId = (int)$row['user_id'];
            }
        }
        $stmtDel = $db->prepare('DELETE FROM sessions WHERE session_id = ?');
        $stmtDel->execute([$sessionId]);

        $cookieSecure = self::getCookieSecure();
        setcookie(self::COOKIE_NAME, '', time() - 3600, '/', '', $cookieSecure, true);

        if ($userId !== null) {
            self::logEvent($userId, 'SESSION_DESTROYED');
        }
    }

    public static function destroyCurrentSession(): void {
        $validated = self::validateSession();
        if ($validated['valid'] ?? false) {
            self::destroySession($_COOKIE[self::COOKIE_NAME], $validated['user_id']);
        }
    }

    public static function isSessionActive(): bool {
        $v = self::validateSession();
        return !empty($v['valid']) && $v['valid'] === true;
    }

    public static function getCurrentUserId(): ?int {
        $validated = self::validateSession();
        if ($validated['valid'] ?? false) {
            return (int)$validated['user_id'];
        }
        return null;
    }

    public static function logAndRespondUnauthorized(): void {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

function jsonResponse(array $payload, int $code = 200): void {
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function handleLogin(): void {
    $db = new Database();
    $username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

    if ($username === '' || $password === '') {
        jsonResponse(['success' => false, 'message' => 'Invalid credentials'], 400);
    }

    $stmt = $db->prepare('SELECT id, password FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid username or password'], 401);
    }

    SessionManager::destroyAllSessionsForUser((int)$user['id']);
    $sessionId = SessionManager::createSession((int)$user['id']);
    SessionManager::logEvent((int)$user['id'], 'LOGIN');
    jsonResponse(['success' => true, 'message' => 'Login successful']);
}

function handleDashboard(): void {
    $validated = SessionManager::validateSession();
    if (!$validated['valid'] ?? false) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    $userId = (int)$validated['user_id'];
    jsonResponse(['success' => true, 'message' => 'Welcome to the dashboard', 'user_id' => $userId]);
}

function handleLogout(): void {
    $sid = $_COOKIE[SessionManager::COOKIE_NAME] ?? null;
    if ($sid) {
        $db = new Database();
        $stmt = $db->prepare('SELECT user_id FROM sessions WHERE session_id = ?');
        $stmt->execute([$sid]);
        $row = $stmt->fetch();
        $userId = $row['user_id'] ?? null;
        SessionManager::destroySession($sid, $userId);
        jsonResponse(['success' => true, 'message' => 'Logout successful']);
    } else {
        jsonResponse(['success' => false, 'message' => 'No active session'], 400);
    }
}

$action = strtolower($_POST['action'] ?? '');
switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'dashboard':
        handleDashboard();
        break;
    case 'logout':
        handleLogout();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
        break;
}
?>