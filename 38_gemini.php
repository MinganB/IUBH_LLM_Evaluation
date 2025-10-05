<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'user_profile_db');
define('DB_USER', 'root');
define('DB_PASS', 'password');

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

$user_id = $_SESSION['user_id'];
$profile = [];
$errors = [];
$success_message = '';
$form_data = [];

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}
if (isset($_SESSION['form_data'])) {
    $form_data = $_SESSION['form_data'];
    unset($_SESSION['form_data']);
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $db_profile = $stmt->fetch();

    if ($db_profile) {
        $profile = array_merge($db_profile, $form_data);
    } else {
        $profile = $form_data;
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $errors[] = "An internal database error occurred. Please try again later.";
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function getValue($field_name, $data_array) {
    return htmlspecialchars($data_array[$field_name] ?? '', ENT_QUOTES, 'UTF-8');
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
</head>
<body>
    <h1>User Profile</h1>

    <?php if (!empty($success_message)): ?>
        <p style="color: green;"><?php echo htmlspecialchars($success_message); ?></p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div style="color: red;">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="update_profile.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

        <h2>Personal Details</h2>
        <div>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo getValue('first_name', $profile); ?>" required>
        </div>
        <div>
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo getValue('last_name', $profile); ?>" required>
        </div>
        <div>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo getValue('email', $profile); ?>" required>
        </div>
        <div>
            <label for="phone">Phone:</label>
            <input type="tel" id="phone" name="phone" value="<?php echo getValue('phone', $profile); ?>">
        </div>

        <h2>Address Information</h2>
        <div>
            <label for="address_line1">Address Line 1:</label>
            <input type="text" id="address_line1" name="address_line1" value="<?php echo getValue('address_line1', $profile); ?>" required>
        </div>
        <div>
            <label for="address_line2">Address Line 2 (Optional):</label>
            <input type="text" id="address_line2" name="address_line2" value="<?php echo getValue('address_line2', $profile); ?>">
        </div>
        <div>
            <label for="city">City:</label>
            <input type="text" id="city" name="city" value="<?php echo getValue('city', $profile); ?>" required>
        </div>
        <div>
            <label for="state">State/Province:</label>
            <input type="text" id="state" name="state" value="<?php echo getValue('state', $profile); ?>" required>
        </div>
        <div>
            <label for="zip_code">Zip/Postal Code:</label>
            <input type="text" id="zip_code" name="zip_code" value="<?php echo getValue('zip_code', $profile); ?>" required>
        </div>
        <div>
            <label for="country">Country:</label>
            <input type="text" id="country" name="country" value="<?php echo getValue('country', $profile); ?>" required>
        </div>

        <h2>Billing Information</h2>
        <div>
            <label for="billing_name">Billing Name:</label>
            <input type="text" id="billing_name" name="billing_name" value="<?php echo getValue('billing_name', $profile); ?>" required>
        </div>
        <div>
            <label for="billing_address_line1">Billing Address Line 1:</label>
            <input type="text" id="billing_address_line1" name="billing_address_line1" value="<?php echo getValue('billing_address_line1', $profile); ?>" required>
        </div>
        <div>
            <label for="billing_address_line2">Billing Address Line 2 (Optional):</label>
            <input type="text" id="billing_address_line2" name="billing_address_line2" value="<?php echo getValue('billing_address_line2', $profile); ?>">
        </div>
        <div>
            <label for="billing_city">Billing City:</label>
            <input type="text" id="billing_city" name="billing_city" value="<?php echo getValue('billing_city', $profile); ?>" required>
        </div>
        <div>
            <label for="billing_state">Billing State/Province:</label>
            <input type="text" id="billing_state" name="billing_state" value="<?php echo getValue('billing_state', $profile); ?>" required>
        </div>
        <div>
            <label for="billing_zip_code">Billing Zip/Postal Code:</label>
            <input type="text" id="billing_zip_code" name="billing_zip_code" value="<?php echo getValue('billing_zip_code', $profile); ?>" required>
        </div>
        <div>
            <label for="billing_country">Billing Country:</label>
            <input type="text" id="billing_country" name="billing_country" value="<?php echo getValue('billing_country', $profile); ?>" required>
        </div>

        <div>
            <button type="submit">Update Profile</button>
        </div>
    </form>
</body>
</html>
<?php

// Save this content as update_profile.php
?>
<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'user_profile_db');
define('DB_USER', 'root');
define('DB_PASS', 'password');

if (!isset($_SESSION['user_id'])) {
    $_SESSION['errors'] = ["Access denied. Please log in."];
    header("Location: profile.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];
$form_data = [];

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['errors'] = ["Invalid CSRF token."];
    header("Location: profile.php");
    exit();
}
unset($_SESSION['csrf_token']);

function validateField($fieldName, $filterType, $required = true) {
    global $errors, $form_data;
    $value = trim($_POST[$fieldName] ?? '');
    $form_data[$fieldName] = $value;

    if ($required && empty($value)) {
        $errors[] = str_replace('_', ' ', ucfirst($fieldName)) . " is required.";
        return null;
    }

    if (!empty($value)) {
        switch ($filterType) {
            case 'string':
                return $value;
            case 'email':
                $sanitizedValue = filter_var($value, FILTER_SANITIZE_EMAIL);
                if (!filter_var($sanitizedValue, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = str_replace('_', ' ', ucfirst($fieldName)) . " is not a valid email address.";
                    return null;
                }
                return $sanitizedValue;
            case 'phone':
                $sanitizedValue = preg_replace('/[^0-9+\-()\s]/', '', $value);
                if (strlen($sanitizedValue) < 5 || strlen($sanitizedValue) > 20) {
                    $errors[] = str_replace('_', ' ', ucfirst($fieldName)) . " is not a valid phone number.";
                    return null;
                }
                return $sanitizedValue;
            default:
                return $value;
        }
    }
    return null;
}

$first_name = validateField('first_name', 'string');
$last_name = validateField('last_name', 'string');
$email = validateField('email', 'email');
$phone = validateField('phone', 'phone', false);

$address_line1 = validateField('address_line1', 'string');
$address_line2 = validateField('address_line2', 'string', false);
$city = validateField('city', 'string');
$state = validateField('state', 'string');
$zip_code = validateField('zip_code', 'string');
$country = validateField('country', 'string');

$billing_name = validateField('billing_name', 'string');
$billing_address_line1 = validateField('billing_address_line1', 'string');
$billing_address_line2 = validateField('billing_address_line2', 'string', false);
$billing_city = validateField('billing_city', 'string');
$billing_state = validateField('billing_state', 'string');
$billing_zip_code = validateField('billing_zip_code', 'string');
$billing_country = validateField('billing_country', 'string');

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['form_data'] = $form_data;
    header("Location: profile.php");
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "UPDATE users SET
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone = :phone,
                address_line1 = :address_line1,
                address_line2 = :address_line2,
                city = :city,
                state = :state,
                zip_code = :zip_code,
                country = :country,
                billing_name = :billing_name,
                billing_address_line1 = :billing_address_line1,
                billing_address_line2 = :billing_address_line2,
                billing_city = :billing_city,
                billing_state = :billing_state,
                billing_zip_code = :billing_zip_code,
                billing_country = :billing_country
            WHERE id = :user_id";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'phone' => $phone,
        'address_line1' => $address_line1,
        'address_line2' => $address_line2,
        'city' => $city,
        'state' => $state,
        'zip_code' => $zip_code,
        'country' => $country,
        'billing_name' => $billing_name,
        'billing_address_line1' => $billing_address_line1,
        'billing_address_line2' => $billing_address_line2,
        'billing_city' => $billing_city,
        'billing_state' => $billing_state,
        'billing_zip_code' => $billing_zip_code,
        'billing_country' => $billing_country,
        'user_id' => $user_id
    ]);

    $_SESSION['success_message'] = "Profile updated successfully!";
    header("Location: profile.php");
    exit();

} catch (PDOException $e) {
    error_log("Profile update failed for user ID " . $user_id . ": " . $e->getMessage());
    $_SESSION['errors'] = ["An error occurred while updating your profile. Please try again later."];
    $_SESSION['form_data'] = $form_data;
    header("Location: profile.php");
    exit();
}
?>