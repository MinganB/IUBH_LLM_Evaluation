<?php
class SessionHandler {
    private $sessionName;
    private $cookieName;
    private $cookieLifetime;
    private $cookiePath;
    private $cookieDomain;
    private $cookieSecure;
    private $cookieHttpOnly;
    private $cookieSameSite;
    
    public function __construct() {
        $this->sessionName = 'SECURE_SESSION';
        $this->cookieName = 'user_session';
        $this->cookieLifetime = 3600;
        $this->cookiePath = '/';
        $this->cookieDomain = '';
        $this->cookieSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $this->cookieHttpOnly = true;
        $this->cookieSameSite = 'Strict';
        
        $this->initializeSession();
    }
    
    private function initializeSession() {
        ini_set('session.cookie_lifetime', $this->cookieLifetime);
        ini_set('session.cookie_path', $this->cookiePath);
        ini_set('session.cookie_domain', $this->cookieDomain);
        ini_set('session.cookie_secure', $this->cookieSecure);
        ini_set('session.cookie_httponly', $this->cookieHttpOnly);
        ini_set('session.cookie_samesite', $this->cookieSameSite);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.gc_maxlifetime', $this->cookieLifetime);
        
        session_name($this->sessionName);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function createSession($userId, $username, $email) {
        if (!$this->isValidUserId($userId) || !$this->isValidUsername($username) || !$this->isValidEmail($email)) {
            return false;
        }
        
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $_SESSION['email'] = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $_SESSION['created_at'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $this->getClientIP();
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['is_authenticated'] = true;
        
        $sessionToken = bin2hex(random_bytes(32));
        $_SESSION['session_token'] = hash('sha256', $sessionToken);
        
        setcookie(
            $this->cookieName,
            $sessionToken,
            [
                'expires' => time() + $this->cookieLifetime,
                'path' => $this->cookiePath,
                'domain' => $this->cookieDomain,
                'secure' => $this->cookieSecure,
                'httponly' => $this->cookieHttpOnly,
                'samesite' => $this->cookieSameSite
            ]
        );
        
        return true;
    }
    
    public function validateSession() {
        if (!isset($_SESSION['is_authenticated']) || $_SESSION['is_authenticated'] !== true) {
            return false;
        }
        
        if (!isset($_COOKIE[$this->cookieName]) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        $providedToken = $_COOKIE[$this->cookieName];
        $hashedToken = hash('sha256', $providedToken);
        
        if (!hash_equals($_SESSION['session_token'], $hashedToken)) {
            $this->destroySession();
            return false;
        }
        
        if (time() - $_SESSION['last_activity'] > $this->cookieLifetime) {
            $this->destroySession();
            return false;
        }
        
        if ($_SESSION['ip_address'] !== $this->getClientIP()) {
            $this->destroySession();
            return false;
        }
        
        if (isset($_SERVER['HTTP_USER_AGENT']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            $this->destroySession();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    public function destroySession() {
        $_SESSION = [];
        
        if (isset($_COOKIE[$this->cookieName])) {
            setcookie(
                $this->cookieName,
                '',
                [
                    'expires' => time() - 3600,
                    'path' => $this->cookiePath,
                    'domain' => $this->cookieDomain,
                    'secure' => $this->cookieSecure,
                    'httponly' => $this->cookieHttpOnly,
                    'samesite' => $this->cookieSameSite
                ]
            );
        }
        
        session_destroy();
    }
    
    public function getUserData() {
        if (!$this->validateSession()) {
            return null;
        }
        
        return [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'created_at' => $_SESSION['created_at'],
            'last_activity' => $_SESSION['last_activity']
        ];
    }
    
    public function getCSRFToken() {
        return $_SESSION['csrf_token'] ?? null;
    }
    
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    private function isValidUserId($userId) {
        return is_numeric($userId) && $userId > 0;
    }
    
    private function isValidUsername($username) {
        return is_string($username) && strlen($username) >= 3 && strlen($username) <= 50 && preg_match('/^[a-zA-Z0-9_]+$/', $username);
    }
    
    private function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 254;
    }
    
    public function refreshSession() {
        if ($this->validateSession()) {
            session_regenerate_id(true);
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }
}

$sessionHandler = new SessionHandler();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'login':
            if (isset($_POST['user_id'], $_POST['username'], $_POST['email'])) {
                $userId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                
                if ($sessionHandler->createSession($userId, $username, $email)) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Session created successfully',
                        'csrf_token' => $sessionHandler->getCSRFToken(),
                        'redirect' => 'dashboard.php'
                    ]);
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Invalid user data provided'
                    ]);
                }
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Missing required fields'
                ]);
            }
            break;
            
        case 'logout':
            $sessionHandler->destroySession();
            echo json_encode([
                'status' => 'success',
                'message' => 'Session destroyed successfully',
                'redirect' => 'login.php'
            ]);
            break;
            
        case 'validate':
            if ($sessionHandler->validateSession()) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Session is valid',
                    'user_data' => $sessionHandler->getUserData(),
                    'csrf_token' => $sessionHandler->getCSRFToken()
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Session is invalid or expired'
                ]);
            }
            break;
            
        case 'refresh':
            if ($sessionHandler->refreshSession()) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Session refreshed successfully',
                    'csrf_token' => $sessionHandler->getCSRFToken()
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Unable to refresh session'
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid action'
            ]);
            break;
    }
    exit;
}
?>


<?php
require_once 'session_handler.php';

$sessionHandler = new SessionHandler();

if (!$sessionHandler->validateSession()) {
    header('Location: login.php');
    exit;
}

$userData = $sessionHandler->getUserData();
$csrfToken = $sessionHandler->getCSRFToken();

if (!$userData) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Dashboard - Secure Area</title>
</head>
<body>
    <header>
        <h1>Welcome to Your Dashboard</h1>
        <nav>
            <ul>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="settings.php">Settings</a></li>
                <li><button onclick="logout()">Logout</button></li>
            </ul>
        </nav>
    </header>
    
    <main>
        <section>
            <h2>User Information</h2>
            <div>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($userData['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($userData['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>User ID:</strong> <?php echo htmlspecialchars($userData['user_id'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Session Created:</strong> <?php echo date('Y-m-d H:i:s', $userData['created_at']); ?></p>
                <p><strong>Last Activity:</strong> <?php echo date('Y-m-d H:i:s', $userData['last_activity']); ?></p>
            </div>
        </section>
        
        <section>
            <h2>Dashboard Content</h2>
            <div>
                <p>This is a protected area that requires authentication to access.</p>
                <p>Your session is active and secure.</p>
            </div>
        </section>
        
        <section>
            <h2>Quick Actions</h2>
            <div>
                <button onclick="refreshSession()">Refresh Session</button>
                <button onclick="validateSession()">Check Session Status</button>
            </div>
        </section>
    </main>
    
    <footer>
        <p>&copy; 2024 Secure Application</p>
    </footer>
    
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                fetch('session_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=logout&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.href = data.redirect || 'login.php';
                    } else {
                        alert('Logout failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred during logout');
                });
            }
        }
        
        function refreshSession() {
            fetch('session_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=refresh&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Session refreshed successfully');
                    if (data.csrf_token) {
                        document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.csrf_token);
                    }
                } else {
                    alert('Session refresh failed: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred during session refresh');
            });
        }
        
        function validateSession() {
            fetch('session_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=validate&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Session is valid and active');
                } else {
                    alert('Session validation failed: ' + data.message);
                    window.location.href = 'login.php';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred during session validation');
            });
        }
        
        setInterval(function() {
            fetch('session_handler.php', {
                method: 'POST',
                headers: {
                    'Content
?>