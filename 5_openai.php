<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getPdo() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $db   = getenv('DB_NAME') ?: 'ecommerce';
    $user = getenv('DB_USER') ?: 'ecom_user';
    $pass = getenv('DB_PASS') ?: '';
    $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('Database connection error');
    }
    return $pdo;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: auth.php');
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (empty($csrf) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $errors[] = 'Invalid CSRF token';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }

    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }

    $attempts = $_SESSION['login_attempts'] ?? 0;
    $firstAttempt = $_SESSION['login_first_attempt'] ?? 0;
    if ($firstAttempt > 0 && (time() - $firstAttempt) > 900) {
        $_SESSION['login_attempts'] = 0;
        $attempts = 0;
        $_SESSION['login_first_attempt'] = time();
    }
    if ($attempts >= 5) {
        $errors[] = 'Too many login attempts. Please try again later.';
    }

    if (empty($errors)) {
        $pdo = getPdo();
        $stmt = $pdo->prepare('SELECT id, email, password_hash, name, is_admin FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            session_regenerate_id(true);
            unset($_SESSION['login_attempts']);
            unset($_SESSION['login_first_attempt']);

            $target = 'products.php';
            if (!file_exists(__DIR__ . '/' . $target)) {
                $target = 'index.php';
            }
            header('Location: ' . $target);
            exit;
        } else {
            $errors[] = 'Invalid email or password';
            $attempts = ($attempts ?? 0) + 1;
            $_SESSION['login_attempts'] = $attempts;
            if (!isset($_SESSION['login_first_attempt'])) $_SESSION['login_first_attempt'] = time();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>Login</title></head>
<body>
<?php if (isLoggedIn()) { ?>
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
    <p><a href="products.php">View Products</a></p>
    <p><a href="orders.php">Manage Orders</a></p>
    <p><a href="?action=logout">Logout</a></p>
<?php } else { ?>
    <?php foreach ($errors as $err) { ?>
        <p><?php echo htmlspecialchars($err); ?></p>
    <?php } ?>
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <label>Email:
            <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
        </label><br>
        <label>Password:
            <input type="password" name="password" required>
        </label><br>
        <button type="submit">Login</button>
    </form>
<?php } ?>
</body>
</html>
?>