<?php
declare(strict_types=1);
$https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'samesite' => 'Lax',
    'secure' => $https,
    'httponly' => true
]);
session_start();

try {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'app';
    $dbuser = getenv('DB_USER') ?: 'root';
    $dbpass = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    $pdo = new PDO($dsn, $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            failed_attempts INT NOT NULL DEFAULT 0,
            lockout_until DATETIME NULL,
            last_login DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database connection error';
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
if (empty($csrfToken)) {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrfToken) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $emailInput = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $email = filter_var($emailInput, FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $errors[] = 'Please enter a valid email.';
        }
        if ($password === '') {
            $errors[] = 'Please enter your password.';
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare('SELECT id, email, password_hash, is_active, failed_attempts, lockout_until FROM users WHERE email = ?');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user || !$user['is_active']) {
                    $errors[] = 'Invalid email or password.';
                } else {
                    $now = new DateTime();
                    if (!empty($user['lockout_until'])) {
                        $lockoutUntil = new DateTime($user['lockout_until']);
                        if ($lockoutUntil > $now) {
                            $errors[] = 'Too many failed attempts. Please try again later.';
                        } else {
                            $upd = $pdo->prepare('UPDATE users SET lockout_until = NULL, failed_attempts = 0 WHERE id = ?');
                            $upd->execute([$user['id']]);
                        }
                    }

                    if (empty($errors)) {
                        if (password_verify($password, $user['password_hash'])) {
                            $upd2 = $pdo->prepare('UPDATE users SET failed_attempts = 0, lockout_until = NULL, last_login = NOW() WHERE id = ?');
                            $upd2->execute([$user['id']]);
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = (int)$user['id'];
                            $_SESSION['user_email'] = $user['email'];
                            $_SESSION['login_time'] = time();

                            $redirectTarget = '/';
                            $dashboardPath = $_SERVER['DOCUMENT_ROOT'] . '/dashboard.php';
                            if (file_exists($dashboardPath)) {
                                $redirectTarget = '/dashboard.php';
                            }
                            header('Location: ' . $redirectTarget);
                            exit;
                        } else {
                            $newFailed = (int)$user['failed_attempts'] + 1;
                            $lockoutUntil = null;
                            if ($newFailed >= 5) {
                                $lockoutUntil = (new DateTime())->add(new DateInterval('PT15M'))->format('Y-m-d H:i:s');
                            }
                            $update3 = $pdo->prepare('UPDATE users SET failed_attempts = ?, lockout_until = ? WHERE id = ?');
                            $update3->execute([$newFailed, $lockoutUntil, $user['id']]);
                            $errors[] = 'Invalid email or password.';
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log($e->getMessage());
                http_response_code(500);
                $errors[] = 'An internal error occurred. Please try again later.';
            }
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <h2>Sign In</h2>
    <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $err): ?>
            <p><?php echo htmlspecialchars($err); ?></p>
        <?php endforeach; ?>
    <?php endif; ?>
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <label>
            Email:
            <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
        </label>
        <br>
        <label>
            Password:
            <input type="password" name="password" required>
        </label>
        <br>
        <button type="submit">Login</button>
    </form>
</body>
</html>
?>