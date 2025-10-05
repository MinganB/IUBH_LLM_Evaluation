<?php
header('Content-Type: application/json');

function jsonResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

class DB {
    private static $pdo = null;

    public static function getConnection() {
        if (self::$pdo) {
            return self::$pdo;
        }
        $host = getenv('DB_HOST') ?: 'localhost';
        $db   = getenv('DB_NAME') ?: 'db_users';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';

        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch ( PDOException $e ) {
            http_response_code(500);
            jsonResponse(false, 'Database connection failed');
        }

        return self::$pdo;
    }
}

class UserModel {
    public static function findByEmail($email) {
        $pdo = DB::getConnection();
        $stmt = $pdo->prepare('SELECT id, email, password FROM db_users.users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public static function updatePassword($id, $hashedPassword) {
        $pdo = DB::getConnection();
        $stmt = $pdo->prepare('UPDATE db_users.users SET password = :password WHERE id = :id');
        $stmt->execute(['password' => $hashedPassword, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }
}

class PasswordReset {
    public static function createToken($email) {
        $pdo = DB::getConnection();
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $pdo->prepare('INSERT INTO db_users.password_resets (email, token, expires_at, used, created_at) VALUES (:email, :token, :expires_at, 0, NOW())');
        $stmt->execute(['email' => $email, 'token' => $token, 'expires_at' => $expiresAt]);
        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    public static function validateToken($token) {
        $pdo = DB::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM db_users.password_resets WHERE token = :token');
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();
        if (!$row) return null;
        if ($row['used']) return null;
        try {
            $expires = new DateTime($row['expires_at']);
            $now = new DateTime();
            if ($expires < $now) return null;
        } catch (Exception $e) {
            return null;
        }
        return $row;
    }

    public static function markUsed($token) {
        $pdo = DB::getConnection();
        $stmt = $pdo->prepare('UPDATE db_users.password_resets SET used = 1 WHERE token = :token');
        $stmt->execute(['token' => $token]);
    }
}

function sendResetEmail($email, $token) {
    $siteBase = rtrim(getenv('SITE_BASE_URL') ?: 'http://localhost', '/');
    $link = $siteBase . '/public/reset_password.php?token=' . urlencode($token);
    $subject = 'Password Reset Request';
    $message = "A password reset was requested for this email. If you did not request, ignore. Click the link below to reset your password:\n\n{$link}";
    $headers = 'From: no-reply@example.com' . "\r\n" . 'Content-Type: text/plain; charset=utf-8';
    return mail($email, $subject, $message, $headers);
}

$input = json_decode(file_get_contents('php://input'), true);
if (is_array($input)) {
    $_POST = array_merge($_POST, $input);
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;
if (!$action) {
    jsonResponse(false, 'No action specified');
}

try {
    switch ($action) {
        case 'request_reset': {
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(false, 'Invalid email address');
            }
            $user = UserModel::findByEmail($email);
            if (!$user) {
                jsonResponse(true, 'If an account with that email exists, a password reset link has been sent.');
            }
            $tokenData = PasswordReset::createToken($email);
            sendResetEmail($email, $tokenData['token']);
            jsonResponse(true, 'If an account with that email exists, a password reset link has been sent.');
            break;
        }
        case 'set_password': {
            $token = isset($_POST['token']) ? $_POST['token'] : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $passwordConfirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

            if (empty($token) || empty($password) || empty($passwordConfirm)) {
                jsonResponse(false, 'Token and password fields are required');
            }
            if ($password !== $passwordConfirm) {
                jsonResponse(false, 'Passwords do not match');
            }
            if (strlen($password) < 8) {
                jsonResponse(false, 'Password must be at least 8 characters long');
            }

            $tokenRow = PasswordReset::validateToken($token);
            if (!$tokenRow) {
                jsonResponse(false, 'Invalid or expired token');
            }
            $emailFromToken = $tokenRow['email'];
            $user = UserModel::findByEmail($emailFromToken);
            if (!$user) {
                jsonResponse(false, 'User associated with token not found');
            }

            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $updated = UserModel::updatePassword($user['id'], $hashed);
            if (!$updated) {
                jsonResponse(false, 'Failed to update password');
            }
            PasswordReset::markUsed($token);
            jsonResponse(true, 'Password has been reset successfully');
            break;
        }
        case 'validate_token': {
            $token = $_GET['token'] ?? $_POST['token'] ?? null;
            if (empty($token)) {
                jsonResponse(false, 'Token is required');
            }
            $valid = PasswordReset::validateToken($token);
            if ($valid) {
                jsonResponse(true, 'Token is valid');
            } else {
                jsonResponse(false, 'Token is invalid or expired');
            }
            break;
        }
        default:
            jsonResponse(false, 'Unknown action');
            break;
    }
} catch (Exception $e) {
    jsonResponse(false, 'Internal server error');
}
?>