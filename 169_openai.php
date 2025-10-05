<?php
class Database {
    private static $instance = null;
    private $pdo;
    private function __construct() {
        $host = getenv('DB_HOST') ?: 'localhost';
        $db = getenv('DB_NAME') ?: 'db_users';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $charset = 'utf8mb4';
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public function getConnection() {
        return $this->pdo;
    }
}
?> 
<?php
class User {
    private $db;
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    public function getByUsername($username) {
        $stmt = $this->db->prepare('SELECT id, username, password FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        return $stmt->fetch();
    }
}
?> 
<?php
class AuthHandler {
    public function login($username, $password) {
        if (empty($username) || empty($password)) {
            return ['status'=>'error','message'=>'Username and password are required'];
        }
        $userObj = new User();
        $user = $userObj->getByUsername($username);
        if (!$user) {
            return ['status'=>'error','message'=>'Invalid credentials'];
        }
        if (!password_verify($password, $user['password'])) {
            return ['status'=>'error','message'=>'Invalid credentials'];
        }
        session_start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        setcookie('SESSIONID', session_id(), time() + 7*24*60*60, '/', '', false, true);
        return ['status'=>'success','message'=>'Login successful','user_id'=>$user['id']];
    }
}
?> 
<?php
class SessionHandler {
    public function validate() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            return ['status'=>'success','message'=>'Authenticated','user_id'=>$_SESSION['user_id']];
        } else {
            return ['status'=>'error','message'=>'Not authenticated'];
        }
    }
}
?> 
<?php
header('Content-Type: application/json');
spl_autoload_register(function($class) {
    $base = dirname(__DIR__) . '/';
    $paths = [
        $base . 'classes/' . $class . '.php',
        $base . 'handlers/' . $class . '.php'
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});
$username = isset($_POST['username']) ? $_POST['username'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$handlerClass = class_exists('AuthHandler') ? 'AuthHandler' : '';
$handler = new $handlerClass();
$response = $handler->login($username, $password);
echo json_encode($response);
?> 
<?php
header('Content-Type: application/json');
spl_autoload_register(function($class) {
    $base = dirname(__DIR__) . '/';
    $paths = [
        $base . 'classes/' . $class . '.php',
        $base . 'handlers/' . $class . '.php'
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});
$session = new SessionHandler();
$auth = $session->validate();
if ($auth['status'] === 'success') {
    echo json_encode(['status'=>'success','message'=>'Dashboard content','user_id'=>$auth['user_id']]);
} else {
    http_response_code(401);
    echo json_encode($auth);
}
?>