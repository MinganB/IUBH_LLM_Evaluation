<?php

session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_db');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    die('An unexpected error occurred. Please try again later.');
}

function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^[\d\s\-\+\(\)]+$/', $phone) && strlen(str_replace([' ', '-', '(', ')', '+'], '', $phone)) >= 7;
}

function logProfileUpdate($userId, $status) {
    $logFile = __DIR__ . '/profile_updates.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] User ID: " . sanitizeInput($userId) . " | Status: " . sanitizeInput($status) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$userData = null;
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone, shipping_address, billing_address FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $userData = $stmt->fetch();

    if (!$userData) {
        session_unset();
        session_destroy();
        header('Location: /login.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Error fetching user data for ID ' . $userId . ': ' . $e->getMessage());
    die('An unexpected error occurred. Please try again later.');
}

$feedbackMessage = '';
$isSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        logProfileUpdate($userId, 'CSRF token mismatch - POST attempt');
        $feedbackMessage = 'Invalid request. Please try again.';
    } else {
        $errors = [];

        $firstName = sanitizeInput($_POST['first_name'] ?? '');
        $lastName = sanitizeInput($_POST['last_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $shippingAddress = sanitizeInput($_POST['shipping_address'] ?? '');
        $billingAddress = sanitizeInput($_POST['billing_address'] ?? '');

        if (empty($firstName)) {
            $errors[] = 'First name is required.';
        } elseif (strlen($firstName) > 50) {
            $errors[] = 'First name is too long.';
        }
        if (empty($lastName)) {
            $errors[] = 'Last name is required.';
        } elseif (strlen($lastName) > 50) {
            $errors[] = 'Last name is too long.';
        }
        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!validateEmail($email)) {
            $errors[] = 'Invalid email format.';
        } elseif (strlen($email) > 100) {
            $errors[] = 'Email is too long.';
        }
        if (!empty($phone) && !validatePhone($phone)) {
            $errors[] = 'Invalid phone number format.';
        } elseif (strlen($phone) > 20) {
            $errors[] = 'Phone number is too long.';
        }
        if (strlen($shippingAddress) > 255) {
            $errors[] = 'Shipping address is too long.';
        }
        if (strlen($billingAddress) > 255) {
            $errors[] = 'Billing address is too long.';
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE users SET 
                    first_name = :first_name, 
                    last_name = :last_name, 
                    email = :email, 
                    phone = :phone, 
                    shipping_address = :shipping_address, 
                    billing_address = :billing_address 
                    WHERE id = :id"
                );

                $stmt->execute([
                    ':first_name' => $firstName,
                    ':last_name' => $lastName,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':shipping_address' => $shippingAddress,
                    ':billing_address' => $billingAddress,
                    ':id' => $userId
                ]);

                if ($stmt->rowCount() > 0) {
                    $feedbackMessage = 'Profile updated successfully.';
                    $isSuccess = true;
                    logProfileUpdate($userId, 'Update successful');
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $csrfToken = $_SESSION['csrf_token'];
                    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone, shipping_address, billing_address FROM users WHERE id = :id");
                    $stmt->execute([':id' => $userId]);
                    $userData = $stmt->fetch();
                } else {
                    $feedbackMessage = 'No changes detected or an error occurred.';
                    logProfileUpdate($userId, 'No changes detected or error (0 rows affected)');
                }
            } catch (PDOException $e) {
                error_log('Database error updating user profile (ID: ' . $userId . '): ' . $e->getMessage());
                $feedbackMessage = 'An unexpected error occurred during the update. Please try again.';
                logProfileUpdate($userId, 'Database error during update');
            }
        } else {
            $feedbackMessage = 'Please correct the following errors:<br>' . implode('<br>', $errors);
            logProfileUpdate($userId, 'Validation errors: ' . implode(', ', $errors));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
</head>
<body>
    <h1>User Profile</h1>

    <?php if ($feedbackMessage): ?>
        <p style="color: <?php echo $isSuccess ? 'green' : 'red'; ?>;"><?php echo $feedbackMessage; ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

        <h2>Personal Information</h2>
        <div>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo sanitizeInput($userData['first_name'] ?? ''); ?>" required maxlength="50">
        </div>
        <div>
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo sanitizeInput($userData['last_name'] ?? ''); ?>" required maxlength="50">
        </div>
        <div>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo sanitizeInput($userData['email'] ?? ''); ?>" required maxlength="100">
        </div>
        <div>
            <label for="phone">Phone:</label>
            <input type="tel" id="phone" name="phone" value="<?php echo sanitizeInput($userData['phone'] ?? ''); ?>" maxlength="20">
        </div>

        <h2>Shipping Address</h2>
        <div>
            <label for="shipping_address">Address:</label>
            <textarea id="shipping_address" name="shipping_address" rows="4" maxlength="255"><?php echo sanitizeInput($userData['shipping_address'] ?? ''); ?></textarea>
        </div>

        <h2>Billing Details</h2>
        <div>
            <label for="billing_address">Billing Address:</label>
            <textarea id="billing_address" name="billing_address" rows="4" maxlength="255"><?php echo sanitizeInput($userData['billing_address'] ?? ''); ?></textarea>
        </div>

        <div>
            <button type="submit">Update Profile</button>
        </div>
    </form>
</body>
</html>
?>