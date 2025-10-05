<?php
session_start();

class Database {
    private string $host;
    private string $db;
    private string $user;
    private string $pass;
    private ?\PDO $pdo = null;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: '127.0.0.1';
        $this->db   = getenv('DB_NAME') ?: 'db_users';
        $this->user = getenv('DB_USER') ?: 'root';
        $this->pass = getenv('DB_PASSWORD') ?: '';
        $dsn = "mysql:host={$this->host};dbname={$this->db};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES     => false
        ];

        $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
    }

    public function getConnection(): \PDO {
        return $this->pdo;
    }
}

class User {
    private int $id;
    private string $username;

    public function __construct(int $id, string $username) {
        $this->id = $id;
        $this->username = $username;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getUsername(): string {
        return $this->username;
    }
}

class AuthHandler {
    private \PDO $db;

    public function __construct(\PDO $pdo) {
        $this->db = $pdo;
    }

    public function authenticate(string $username, string $password): array {
        $sql = "SELECT id, username, password, is_active FROM users WHERE username = :username LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }

        if (!$row['is_active']) {
            return ['success' => false, 'message' => 'Account is inactive.'];
        }

        $hash = $row['password'];
        if (!password_verify($password, $hash)) {
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }

        $update = "UPDATE users SET last_login_at = NOW() WHERE id = :id";
        $upd = $this->db->prepare($update);
        $upd->execute([':id' => (int)$row['id']]);

        $user = new User((int)$row['id'], $row['username']);
        return ['success' => true, 'user' => $user];
    }
}

function render_login_form(?string $error = null): void {
    $csrf = $_SESSION['csrf_token'] ?? '';
    if (!$csrf) {
        $csrf = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $csrf;
    }
    $errHtml = '';
    if (!empty($error)) {
        $errHtml = "<div style=\"color: red; margin-bottom: 10px;\">". htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ."</div>";
    }
    echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>
</head>
<body>
'.$errHtml.'
<form action="" method="post" autocomplete="on" novalidate>
    <input type="hidden" name="csrf_token" value="'. htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') .'">
    <div>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>
    </div>
    <div>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
    </div>
    <div>
        <button type="submit">Login</button>
    </div>
</form>
</body>
</html>';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$errorParam = null;
if (isset($_GET['error'])) {
    $errorParam = $_GET['error'];
}

if ($method === 'POST') {
    $postedCsrf = $_POST['csrf_token'] ?? '';
    if (empty($postedCsrf) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedCsrf)) {
        $errorParam = 'Invalid CSRF token. Please try again.';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $errorParam]);
            exit;
        } else {
            header('Location: login.php?error=' . urlencode($errorParam));
            exit;
        }
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errorParam = 'Please provide both username and password.';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $errorParam]);
            exit;
        } else {
            header('Location: login.php?error=' . urlencode($errorParam));
            exit;
        }
    }

    try {
        $db = new Database();
        $pdo = $db->getConnection();
        $auth = new AuthHandler($pdo);
        $result = $auth->authenticate($username, $password);

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($result['success']) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $result['user']->getId();

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'redirect' => '/dashboard.php']);
                exit;
            } else {
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $message = $result['message'] ?? 'Invalid credentials.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => $message]);
                exit;
            } else {
                header('Location: login.php?error=' . urlencode($message));
                exit;
            }
        }
    } catch (Exception $e) {
        $msg = 'An error occurred during authentication.';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $msg]);
            exit;
        } else {
            header('Location: login.php?error=' . urlencode($msg));
            exit;
        }
    }
} else {
    render_login_form($errorParam);
    exit;
}
?>