<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/log.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

$user_id = $_SESSION['user_id'];
$user_data = [];
$message = '';
$message_type = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user_data = $stmt->fetch();

    if (!$user_data) {
        log_activity("Error: User ID {$user_id} not found when fetching profile data.");
        $message = "Your profile could not be loaded. Please try again later.";
        $message_type = 'error';
    }
} catch (PDOException $e) {
    log_activity("Database error fetching user profile for user_id {$user_id}: " . $e->getMessage());
    $message = "A database error occurred. Please try again later.";
    $message_type = 'error';
}

if (isset($_SESSION['profile_message'])) {
    $message = $_SESSION['profile_message'];
    $message_type = $_SESSION['profile_message_type'];
    unset($_SESSION['profile_message']);
    unset($_SESSION['profile_message_type']);
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
    <h1>Manage Your Profile</h1>

    <?php if ($message): ?>
        <p style="color: <?php echo $message_type === 'success' ? 'green' : 'red'; ?>;">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <form action="update_profile.php" method="POST">
        <h2>Personal Information</h2>
        <label for="first_name">First Name:</label><br>
        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" required><br><br>

        <label for="last_name">Last Name:</label><br>
        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" required><br><br>

        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required><br><br>

        <label for="phone_number">Phone Number:</label><br>
        <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>"><br><br>

        <h2>Address Information</h2>
        <label for="address_line1">Address Line 1:</label><br>
        <input type="text" id="address_line1" name="address_line1" value="<?php echo htmlspecialchars($user_data['address_line1'] ?? ''); ?>"><br><br>

        <label for="address_line2">Address Line 2 (Optional):</label><br>
        <input type="text" id="address_line2" name="address_line2" value="<?php echo htmlspecialchars($user_data['address_line2'] ?? ''); ?>"><br><br>

        <label for="city">City:</label><br>
        <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user_data['city'] ?? ''); ?>"><br><br>

        <label for="state">State/Province:</label><br>
        <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($user_data['state'] ?? ''); ?>"><br><br>

        <label for="zip_code">Zip/Postal Code:</label><br>
        <input type="text" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($user_data['zip_code'] ?? ''); ?>"><br><br>

        <label for="country">Country:</label><br>
        <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($user_data['country'] ?? ''); ?>"><br><br>

        <h2>Billing Information</h2>
        <label for="billing_address_line1">Billing Address Line 1:</label><br>
        <input type="text" id="billing_address_line1" name="billing_address_line1" value="<?php echo htmlspecialchars($user_data['billing_address_line1'] ?? ''); ?>"><br><br>

        <label for="billing_address_line2">Billing Address Line 2 (Optional):</label><br>
        <input type="text" id="billing_address_line2" name="billing_address_line2" value="<?php echo htmlspecialchars($user_data['billing_address_line2'] ?? ''); ?>"><br><br>

        <label for="billing_city">Billing City:</label><br>
        <input type="text" id="billing_city" name="billing_city" value="<?php echo htmlspecialchars($user_data['billing_city'] ?? ''); ?>"><br><br>

        <label for="billing_state">Billing State/Province:</label><br>
        <input type="text" id="billing_state" name="billing_state" value="<?php echo htmlspecialchars($user_data['billing_state'] ?? ''); ?>"><br><br>

        <label for="billing_zip_code">Billing Zip/Postal Code:</label><br>
        <input type="text" id="billing_zip_code" name="billing_zip_code" value="<?php echo htmlspecialchars($user_data['billing_zip_code'] ?? ''); ?>"><br><br>

        <label for="billing_country">Billing Country:</label><br>
        <input type="text" id="billing_country" name="billing_country" value="<?php echo htmlspecialchars($user_data['billing_country'] ?? ''); ?>"><br><br>

        <button type="submit">Update Profile</button>
    </form>
</body>
</html>
<?php

namespace CodeGen;

use PDO;
use PDOException;

// config.php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_db');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');

// Log file path
define('LOG_FILE', __DIR__ . '/profile_updates.log');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage(), 0);
    die("A system error has occurred. Please try again later.");
}

// log.php
function log_activity($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = sprintf("[%s] %s\n", $timestamp, $message);
    file_put_contents(LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
}

// update_profile.php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/log.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    function sanitize_string($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    function validate_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }

    function validate_phone($phone) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        return (strlen($phone) >= 7 && strlen($phone) <= 20) ? $phone : false;
    }

    function validate_zip_code($zip) {
        $zip = trim($zip);
        return preg_match('/^[a-zA-Z0-9\s-]{3,10}$/', $zip) ? $zip : false;
    }

    $required_fields = ['first_name', 'last_name', 'email'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }

    $first_name = sanitize_string($_POST['first_name'] ?? '');
    $last_name = sanitize_string($_POST['last_name'] ?? '');
    $email = sanitize_string($_POST['email'] ?? '');
    $phone_number = sanitize_string($_POST['phone_number'] ?? '');
    $address_line1 = sanitize_string($_POST['address_line1'] ?? '');
    $address_line2 = sanitize_string($_POST['address_line2'] ?? '');
    $city = sanitize_string($_POST['city'] ?? '');
    $state = sanitize_string($_POST['state'] ?? '');
    $zip_code = sanitize_string($_POST['zip_code'] ?? '');
    $country = sanitize_string($_POST['country'] ?? '');
    $billing_address_line1 = sanitize_string($_POST['billing_address_line1'] ?? '');
    $billing_address_line2 = sanitize_string($_POST['billing_address_line2'] ?? '');
    $billing_city = sanitize_string($_POST['billing_city'] ?? '');
    $billing_state = sanitize_string($_POST['billing_state'] ?? '');
    $billing_zip_code = sanitize_string($_POST['billing_zip_code'] ?? '');
    $billing_country = sanitize_string($_POST['billing_country'] ?? '');

    if ($email && !validate_email($email)) {
        $errors[] = 'Invalid email format.';
    }
    if ($phone_number && !validate_phone($phone_number)) {
        $errors[] = 'Invalid phone number format.';
    }
    if ($zip_code && !validate_zip_code($zip_code)) {
        $errors[] = 'Invalid zip/postal code format.';
    }
    if ($billing_zip_code && !validate_zip_code($billing_zip_code)) {
        $errors[] = 'Invalid billing zip/postal code format.';
    }

    if (empty($errors) && $email) {
        try {
            $stmt_check_email = $pdo->prepare("SELECT user_id FROM users WHERE email = :email AND user_id != :user_id");
            $stmt_check_email->bindParam(':email', $email);
            $stmt_check_email->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_check_email->execute();
            if ($stmt_check_email->fetch()) {
                $errors[] = 'This email is already in use by another account.';
            }
        } catch (PDOException $e) {
            log_activity("Database error checking email existence for user_id {$user_id}: " . $e->getMessage());
            $errors[] = 'A system error occurred during email validation.';
        }
    }

    if (!empty($errors)) {
        $_SESSION['profile_message'] = implode('<br>', $errors);
        $_SESSION['profile_message_type'] = 'error';
        header('Location: profile.php');
        exit();
    }

    try {
        log_activity("Attempting profile update for user ID: {$user_id}");

        $stmt = $pdo->prepare("
            UPDATE users SET
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone_number = :phone_number,
                address_line1 = :address_line1,
                address_line2 = :address_line2,
                city = :city,
                state = :state,
                zip_code = :zip_code,
                country = :country,
                billing_address_line1 = :billing_address_line1,
                billing_address_line2 = :billing_address_line2,
                billing_city = :billing_city,
                billing_state = :billing_state,
                billing_zip_code = :billing_zip_code,
                billing_country = :billing_country
            WHERE user_id = :user_id
        ");

        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':address_line1', $address_line1);
        $stmt->bindParam(':address_line2', $address_line2);
        $stmt->bindParam(':city', $city);
        $stmt->bindParam(':state', $state);
        $stmt->bindParam(':zip_code', $zip_code);
        $stmt->bindParam(':country', $country);
        $stmt->bindParam(':billing_address_line1', $billing_address_line1);
        $stmt->bindParam(':billing_address_line2', $billing_address_line2);
        $stmt->bindParam(':billing_city', $billing_city);
        $stmt->bindParam(':billing_state', $billing_state);
        $stmt->bindParam(':billing_zip_code', $billing_zip_code);
        $stmt->bindParam(':billing_country', $billing_country);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            log_activity("Profile updated successfully for user ID: {$user_id}");
            $_SESSION['profile_message'] = 'Your profile has been updated successfully!';
            $_SESSION['profile_message_type'] = 'success';
        } else {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = :user_id AND first_name = :first_name AND last_name = :last_name AND email = :email AND phone_number <=> :phone_number AND address_line1 <=> :address_line1 AND address_line2 <=> :address_line2 AND city <=> :city AND state <=> :state AND zip_code <=> :zip_code AND country <=> :country AND billing_address_line1 <=> :billing_address_line1 AND billing_address_line2 <=> :billing_address_line2 AND billing_city <=> :billing_city AND billing_state <=> :billing_state AND billing_zip_code <=> :billing_zip_code AND billing_country <=> :billing_country");
            $stmt_check->bindParam(':first_name', $first_name);
            $stmt_check->bindParam(':last_name', $last_name);
            $stmt_check->bindParam(':email', $email);
            $stmt_check->bindParam(':phone_number', $phone_number);
            $stmt_check->bindParam(':address_line1', $address_line1);
            $stmt_check->bindParam(':address_line2', $address_line2);
            $stmt_check->bindParam(':city', $city);
            $stmt_check->bindParam(':state', $state);
            $stmt_check->bindParam(':zip_code', $zip_code);
            $stmt_check->bindParam(':country', $country);
            $stmt_check->bindParam(':billing_address_line1', $billing_address_line1);
            $stmt_check->bindParam(':billing_address_line2', $billing_address_line2);
            $stmt_check->bindParam(':billing_city', $billing_city);
            $stmt_check->bindParam(':billing_state', $billing_state);
            $stmt_check->bindParam(':billing_zip_code', $billing_zip_code);
            $stmt_check->bindParam(':billing_country', $billing_country);
            $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_check->execute();

            if ($stmt_check->fetchColumn() > 0) {
                $_SESSION['profile_message'] = 'Your profile data is already up to date.';
                $_SESSION['profile_message_type'] = 'success';
                log_activity("Profile update attempted for user ID: {$user_id} but no changes were detected.");
            } else {
                log_activity("Profile update failed for user ID: {$user_id}. No rows affected and data was not identical.");
                $_SESSION['profile_message'] = 'An unexpected error occurred. Your profile could not be updated.';
                $_SESSION['profile_message_type'] = 'error';
            }
        }

    } catch (PDOException $e) {
        log_activity("Database error updating profile for user ID: {$user_id}. Error: " . $e->getMessage());
        $_SESSION['profile_message'] = 'A system error occurred. Please try again later.';
        $_SESSION['profile_message_type'] = 'error';
    }

    header('Location: profile.php');
    exit();

} else {
    header('Location: profile.php');
    exit();
}
?>