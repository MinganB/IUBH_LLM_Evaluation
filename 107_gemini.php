<?php

const DB_HOST = 'localhost';
const DB_NAME = 'db_users';
const DB_USER = 'your_db_user';
const DB_PASS = 'your_db_password';

const RESET_EMAIL_FROM = 'no-reply@yourdomain.com';
const RESET_LINK_BASE_URL = 'http://localhost/public/reset_password.php';

header('Content-Type: text/html');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => 'An unexpected error occurred.'];

    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $response['message'] = 'Please provide a valid email address.';
        echo json_encode($response);
        exit();
    }

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(16));

            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (:email, :token, DATE_ADD(NOW(), INTERVAL 1 HOUR), 0, NOW())");
            $stmt->execute([
                ':email' => $email,
                ':token' => $token
            ]);

            $resetLink = RESET_LINK_BASE_URL . '?token=' . $token;
            $subject = 'Password Reset Request';
            $message = "You have requested a password reset. Click the following link to reset your password: " . $resetLink;
            $headers = 'From: ' . RESET_EMAIL_FROM . "\r\n" .
                       'Reply-To: ' . RESET_EMAIL_FROM . "\r\n" .
                       'X-Mailer: PHP/' . phpversion();

            if (mail($email, $subject, $message, $headers)) {
                $response['success'] = true;
                $response['message'] = 'If an account with that email exists, a reset link will be sent.';
            } else {
                $response['success'] = true;
                $response['message'] = 'If an account with that email exists, a reset link will be sent.';
            }
        } else {
            $response['success'] = true;
            $response['message'] = 'If an account with that email exists, a reset link will be sent.';
        }
    } catch (PDOException $e) {
        $response['message'] = 'An internal server error occurred. Please try again later.';
    }

    echo json_encode($response);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Password Reset</title>
</head>
<body>
    <h1>Request Password Reset</h1>
    <form id="resetRequestForm" action="request_reset.php" method="POST">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <button type="submit">Send Reset Link</button>
    </form>
    <div id="responseMessage"></div>

    <script>
        document.getElementById('resetRequestForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const responseDiv = document.getElementById('responseMessage');

            fetch(form.action, {
                method: form.method,
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                responseDiv.textContent = data.message;
                responseDiv.style.color = data.success ? 'green' : 'red';
            })
            .catch(error => {
                responseDiv.textContent = 'An error occurred during the request.';
                responseDiv.style.color = 'red';
            });
        });
    </script>
</body>
</html>
<?php

const DB_HOST = 'localhost';
const DB_NAME = 'db_users';
const DB_USER = 'your_db_user';
const DB_PASS = 'your_db_password';

header('Content-Type: text/html');

$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
$validToken = false;
$errorMessage = '';
$successMessage = '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $response = ['success' => false, 'message' => 'An unexpected error occurred.'];

        $postToken = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING);
        $password = filter_input(INPUT_POST, 'password');
        $confirmPassword = filter_input(INPUT_POST, 'confirm_password');

        if (!$postToken) {
            $response['message'] = 'Password reset token is missing.';
            echo json_encode($response);
            exit();
        }

        if (empty($password) || empty($confirmPassword)) {
            $response['message'] = 'Please fill in both password fields.';
            echo json_encode($response);
            exit();
        }

        if ($password !== $confirmPassword) {
            $response['message'] = 'Passwords do not match.';
            echo json_encode($response);
            exit();
        }

        if (strlen($password) < 8) {
            $response['message'] = 'Password must be at least 8 characters long.';
            echo json_encode($response);
            exit();
        }

        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = :token AND used = 0 AND expires_at > NOW()");
        $stmt->execute([':token' => $postToken]);
        $resetEntry = $stmt->fetch();

        if (!$resetEntry) {
            $response['message'] = 'Invalid, expired, or already used password reset token.';
            echo json_encode($response);
            exit();
        }

        $userEmail = $resetEntry['email'];
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
            $stmt->execute([
                ':password' => $hashedPassword,
                ':email' => $userEmail
            ]);

            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = :token");
            $stmt->execute([':token' => $postToken]);

            $pdo->commit();
            $response['success'] = true;
            $response['message'] = 'Your password has been reset successfully. You can now log in with your new password.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $response['message'] = 'An internal server error occurred while updating the password.';
        }

        echo json_encode($response);
        exit();

    } else {
        if ($token) {
            $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = :token AND used = 0 AND expires_at > NOW()");
            $stmt->execute([':token' => $token]);
            $resetEntry = $stmt->fetch();

            if ($resetEntry) {
                $validToken = true;
            } else {
                $errorMessage = 'Invalid, expired, or already used password reset token.';
            }
        } else {
            $errorMessage = 'Password reset token is missing from the URL.';
        }
    }
} catch (PDOException $e) {
    $errorMessage = 'An internal server error occurred. Please try again later.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>
<body>
    <h1>Reset Your Password</h1>
    <?php if ($errorMessage): ?>
        <p style="color: red;"><?php echo htmlspecialchars($errorMessage); ?></p>
    <?php elseif ($successMessage): ?>
        <p style="color: green;"><?php echo htmlspecialchars($successMessage); ?></p>
    <?php elseif ($validToken): ?>
        <form id="resetPasswordForm" action="reset_password.php" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <label for="password">New Password:</label>
            <input type="password" id="password" name="password" required minlength="8">
            <br>
            <label for="confirm_password">Confirm New Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            <br>
            <button type="submit">Reset Password</button>
        </form>
        <div id="responseMessage"></div>
    <?php else: ?>
        <p>Please check your email for a password reset link.</p>
    <?php endif; ?>

    <script>
        document.getElementById('resetPasswordForm')?.addEventListener('submit', function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const responseDiv = document.getElementById('responseMessage');

            fetch(form.action, {
                method: form.method,
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                responseDiv.textContent = data.message;
                responseDiv.style.color = data.success ? 'green' : 'red';
                if (data.success) {
                    form.reset();
                    setTimeout(() => {
                        window.location.href = '/login.php';
                    }, 3000);
                }
            })
            .catch(error => {
                responseDiv.textContent = 'An error occurred during the request.';
                responseDiv.style.color = 'red';
            });
        });
    </script>
</body>
</html>
?>