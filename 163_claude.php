<?php
class SessionManager {
    private $sessionName = 'USER_SESSION';
    private $cookieName = 'user_session_token';
    private $sessionDuration = 3600;
    
    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function createSession($userId, $username, $email = null) {
        $sessionToken = $this->generateToken();
        
        $_SESSION[$this->sessionName] = [
            'user_id' => $userId,
            'username' => $username,
            'email' => $email,
            'token' => $sessionToken,
            'created_at' => time(),
            'last_activity' => time()
        ];
        
        $this->setCookie($sessionToken);
        return $sessionToken;
    }
    
    public function validateSession() {
        if (!isset($_SESSION[$this->sessionName])) {
            return false;
        }
        
        $session = $_SESSION[$this->sessionName];
        
        if (!isset($_COOKIE[$this->cookieName])) {
            return false;
        }
        
        if ($session['token'] !== $_COOKIE[$this->cookieName]) {
            $this->destroySession();
            return false;
        }
        
        if (time() - $session['last_activity'] > $this->sessionDuration) {
            $this->destroySession();
            return false;
        }
        
        $_SESSION[$this->sessionName]['last_activity'] = time();
        return true;
    }
    
    public function getSessionData() {
        if ($this->validateSession()) {
            return $_SESSION[$this->sessionName];
        }
        return null;
    }
    
    public function destroySession() {
        unset($_SESSION[$this->sessionName]);
        setcookie($this->cookieName, '', time() - 3600, '/');
    }
    
    private function generateToken() {
        return bin2hex(random_bytes(32));
    }
    
    private function setCookie($token) {
        setcookie(
            $this->cookieName,
            $token,
            time() + $this->sessionDuration,
            '/',
            '',
            isset($_SERVER['HTTPS']),
            true
        );
    }
}
?>


<?php
require_once 'SessionManager.php';

$sessionManager = new SessionManager();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (authenticateUser($username, $password)) {
        $userId = getUserId($username);
        $email = getUserEmail($username);
        
        $sessionToken = $sessionManager->createSession($userId, $username, $email);
        
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}

function authenticateUser($username, $password) {
    $validUsers = [
        'admin' => password_hash('admin123', PASSWORD_DEFAULT),
        'user1' => password_hash('password1', PASSWORD_DEFAULT),
        'testuser' => password_hash('test123', PASSWORD_DEFAULT)
    ];
    
    if (isset($validUsers[$username])) {
        return password_verify($password, $validUsers[$username]);
    }
    
    return false;
}

function getUserId($username) {
    $userIds = [
        'admin' => 1,
        'user1' => 2,
        'testuser' => 3
    ];
    
    return $userIds[$username] ?? null;
}

function getUserEmail($username) {
    $emails = [
        'admin' => 'admin@example.com',
        'user1' => 'user1@example.com',
        'testuser' => 'test@example.com'
    ];
    
    return $emails[$username] ?? null;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <h2>Login</h2>
    
    <?php if (isset($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    
    <form method="POST">
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
    
    <p>Test accounts: admin/admin123, user1/password1, testuser/test123</p>
</body>
</html>


<?php
require_once 'SessionManager.php';

$sessionManager = new SessionManager();

if (!$sessionManager->validateSession()) {
    header('Location: login.php');
    exit;
}

$sessionData = $sessionManager->getSessionData();

if (isset($_GET['logout'])) {
    $sessionManager->destroySession();
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome to Dashboard</h1>
    
    <div>
        <h3>Session Information:</h3>
        <p><strong>User ID:</strong> <?php echo htmlspecialchars($sessionData['user_id']); ?></p>
        <p><strong>Username:</strong> <?php echo htmlspecialchars($sessionData['username']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($sessionData['email']); ?></p>
        <p><strong>Session Created:</strong> <?php echo date('Y-m-d H:i:s', $sessionData['created_at']); ?></p>
        <p><strong>Last Activity:</strong> <?php echo date('Y-m-d H:i:s', $sessionData['last_activity']); ?></p>
    </div>
    
    <div>
        <h3>Protected Content</h3>
        <p>This content is only visible to authenticated users.</p>
        <p>Your session is active and valid.</p>
    </div>
    
    <div>
        <a href="?logout=1">Logout</a>
    </div>
</body>
</html>


<?php
require_once 'SessionManager.php';

$sessionManager = new SessionManager();

if (!$sessionManager->validateSession()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$sessionData = $sessionManager->getSessionData();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Protected Page</title>
</head>
<body>
    <h1>Protected Content</h1>
    
    <p>Hello, <?php echo htmlspecialchars($sessionData['username']); ?>!</p>
    
    <div>
        <h3>This is a protected page</h3>
        <p>You can only see this content because you have a valid session.</p>
        <p>Session expires in: <?php echo (3600 - (time() - $sessionData['last_activity'])); ?> seconds</p>
    </div>
    
    <nav>
        <a href="dashboard.php">Back to Dashboard</a>
        <a href="login.php">Login Page</a>
    </nav>
</body>
</html>
?>