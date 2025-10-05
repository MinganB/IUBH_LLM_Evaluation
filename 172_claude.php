<?php
class SessionHandler {
    private $sessionName;
    private $cookieName;
    private $cookieLifetime;
    private $secureCookie;
    private $httpOnlyCookie;
    
    public function __construct($sessionName = 'USER_SESSION', $cookieName = 'user_auth', $cookieLifetime = 3600, $secureCookie = false, $httpOnlyCookie = true) {
        $this->sessionName = $sessionName;
        $this->cookieName = $cookieName;
        $this->cookieLifetime = $cookieLifetime;
        $this->secureCookie = $secureCookie;
        $this->httpOnlyCookie = $httpOnlyCookie;
        
        if (session_status() == PHP_SESSION_NONE) {
            session_name($this->sessionName);
            session_start();
        }
    }
    
    public function createSession($userId, $userData = []) {
        if (session_status() != PHP_SESSION_ACTIVE) {
            return false;
        }
        
        $sessionToken = $this->generateSessionToken();
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['user_data'] = $userData;
        $_SESSION['is_authenticated'] = true;
        
        $cookieValue = base64_encode(json_encode([
            'user_id' => $userId,
            'session_token' => $sessionToken,
            'timestamp' => time()
        ]));
        
        return setcookie(
            $this->cookieName,
            $cookieValue,
            time() + $this->cookieLifetime,
            '/',
            '',
            $this->secureCookie,
            $this->httpOnlyCookie
        );
    }
    
    public function validateSession() {
        if (session_status() != PHP_SESSION_ACTIVE) {
            return false;
        }
        
        if (!isset($_SESSION['is_authenticated']) || !$_SESSION['is_authenticated']) {
            return false;
        }
        
        if (!isset($_COOKIE[$this->cookieName])) {
            return false;
        }
        
        $cookieData = json_decode(base64_decode($_COOKIE[$this->cookieName]), true);
        
        if (!$cookieData || !isset($cookieData['session_token']) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        if ($cookieData['session_token'] !== $_SESSION['session_token']) {
            return false;
        }
        
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $this->cookieLifetime) {
            $this->destroySession();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public function destroySession() {
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        
        setcookie($this->cookieName, '', time() - 3600, '/');
        
        return true;
    }
    
    public function getUserData() {
        if ($this->validateSession()) {
            return [
                'user_id' => $_SESSION['user_id'],
                'login_time' => $_SESSION['login_time'],
                'last_activity' => $_SESSION['last_activity'],
                'user_data' => isset($_SESSION['user_data']) ? $_SESSION['user_data'] : []
            ];
        }
        return null;
    }
    
    public function refreshSession() {
        if ($this->validateSession()) {
            $cookieValue = base64_encode(json_encode([
                'user_id' => $_SESSION['user_id'],
                'session_token' => $_SESSION['session_token'],
                'timestamp' => time()
            ]));
            
            return setcookie(
                $this->cookieName,
                $cookieValue,
                time() + $this->cookieLifetime,
                '/',
                '',
                $this->secureCookie,
                $this->httpOnlyCookie
            );
        }
        return false;
    }
    
    private function generateSessionToken() {
        return bin2hex(random_bytes(32));
    }
}

$sessionHandler = new SessionHandler();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'login':
            if (isset($_POST['user_id'])) {
                $userData = isset($_POST['user_data']) ? $_POST['user_data'] : [];
                $success = $sessionHandler->createSession($_POST['user_id'], $userData);
                echo json_encode(['success' => $success]);
            } else {
                echo json_encode(['success' => false, 'error' => 'User ID required']);
            }
            break;
            
        case 'validate':
            $valid = $sessionHandler->validateSession();
            echo json_encode(['valid' => $valid]);
            break;
            
        case 'logout':
            $success = $sessionHandler->destroySession();
            echo json_encode(['success' => $success]);
            break;
            
        case 'refresh':
            $success = $sessionHandler->refreshSession();
            echo json_encode(['success' => $success]);
            break;
            
        case 'get_user_data':
            $userData = $sessionHandler->getUserData();
            echo json_encode(['data' => $userData]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Welcome to Dashboard</h1>
            <div class="user-info">
                <p>User ID: <?php echo htmlspecialchars($userData['user_id']); ?></p>
                <p>Login Time: <?php echo date('Y-m-d H:i:s', $userData['login_time']); ?></p>
                <p>Last Activity: <?php echo date('Y-m-d H:i:s', $userData['last_activity']); ?></p>
            </div>
            <form method="post" action="session_handler.php">
                <input type="hidden" name="action" value="logout">
                <button type="submit">Logout</button>
            </form>
        </header>
        
        <main>
            <div class="dashboard-content">
                <h2>Dashboard Content</h2>
                <p>This is protected content that requires authentication.</p>
                
                <?php if (!empty($userData['user_data'])): ?>
                <div class="user-data">
                    <h3>User Data:</h3>
                    <pre><?php echo htmlspecialchars(json_encode($userData['user_data'], JSON_PRETTY_PRINT)); ?></pre>
                </div>
                <?php endif; ?>
                
                <div class="session-actions">
                    <button onclick="refreshSession()">Refresh Session</button>
                    <button onclick="validateSession()">Check Session Status</button>
                </div>
                
                <div id="session-status"></div>
            </div>
        </main>
    </div>

    <script>
        function refreshSession() {
            fetch('session_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=refresh'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('session-status').innerHTML = 
                    '<p>Session refresh: ' + (data.success ? 'Success' : 'Failed') + '</p>';
            });
        }
        
        function validateSession() {
            fetch('session_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=validate'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('session-status').innerHTML = 
                    '<p>Session status: ' + (data.valid ? 'Valid' : 'Invalid') + '</p>';
                if (!data.valid) {
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                }
            });
        }
        
        setInterval(validateSession, 60000);
    </script>
</body>
</html>
?>