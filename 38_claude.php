<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php');
    exit;
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
    <form action="update_profile.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
        
        <fieldset>
            <legend>Personal Information</legend>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required maxlength="50">
            
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required maxlength="50">
            
            <label for="middle_name">Middle Name:</label>
            <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>" maxlength="50">
        </fieldset>
        
        <fieldset>
            <legend>Contact Details</legend>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required maxlength="255">
            
            <label for="phone">Phone:</label>
            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" maxlength="20">
            
            <label for="mobile">Mobile:</label>
            <input type="tel" id="mobile" name="mobile" value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>" maxlength="20">
        </fieldset>
        
        <fieldset>
            <legend>Address</legend>
            <label for="street_address">Street Address:</label>
            <input type="text" id="street_address" name="street_address" value="<?php echo htmlspecialchars($user['street_address'] ?? ''); ?>" maxlength="255">
            
            <label for="city">City:</label>
            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" maxlength="100">
            
            <label for="state">State/Province:</label>
            <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>" maxlength="100">
            
            <label for="postal_code">Postal Code:</label>
            <input type="text" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>" maxlength="20">
            
            <label for="country">Country:</label>
            <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>" maxlength="100">
        </fieldset>
        
        <fieldset>
            <legend>Billing Information</legend>
            <label for="billing_street_address">Billing Street Address:</label>
            <input type="text" id="billing_street_address" name="billing_street_address" value="<?php echo htmlspecialchars($user['billing_street_address'] ?? ''); ?>" maxlength="255">
            
            <label for="billing_city">Billing City:</label>
            <input type="text" id="billing_city" name="billing_city" value="<?php echo htmlspecialchars($user['billing_city'] ?? ''); ?>" maxlength="100">
            
            <label for="billing_state">Billing State/Province:</label>
            <input type="text" id="billing_state" name="billing_state" value="<?php echo htmlspecialchars($user['billing_state'] ?? ''); ?>" maxlength="100">
            
            <label for="billing_postal_code">Billing Postal Code:</label>
            <input type="text" id="billing_postal_code" name="billing_postal_code" value="<?php echo htmlspecialchars($user['billing_postal_code'] ?? ''); ?>" maxlength="20">
            
            <label for="billing_country">Billing Country:</label>
            <input type="text" id="billing_country" name="billing_country" value="<?php echo htmlspecialchars($user['billing_country'] ?? ''); ?>" maxlength="100">
        </fieldset>
        
        <button type="submit">Update Profile</button>
    </form>
</body>
</html>


<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die('CSRF token validation failed');
}

$user_id = $_SESSION['user_id'];
$errors = [];

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$street_address = trim($_POST['street_address'] ?? '');
$city = trim($_POST['city'] ?? '');
$state = trim($_POST['state'] ?? '');
$postal_code = trim($_POST['postal_code'] ?? '');
$country = trim($_POST['country'] ?? '');
$billing_street_address = trim($_POST['billing_street_address'] ?? '');
$billing_city = trim($_POST['billing_city'] ?? '');
$billing_state = trim($_POST['billing_state'] ?? '');
$billing_postal_code = trim($_POST['billing_postal_code'] ?? '');
$billing_country = trim($_POST['billing_country'] ?? '');

if (empty($first_name) || strlen($first_name) > 50) {
    $errors[] = 'First name is required and must be 50 characters or less';
}

if (empty($last_name) || strlen($last_name) > 50) {
    $errors[] = 'Last name is required and must be 50 characters or less';
}

if (strlen($middle_name) > 50) {
    $errors[] = 'Middle name must be 50 characters or less';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    $errors[] = 'Valid email is required and must be 255 characters or less';
}

if (!empty($phone) && strlen($phone) > 20) {
    $errors[] = 'Phone number must be 20 characters or less';
}

if (!empty($mobile) && strlen($mobile) > 20) {
    $errors[] = 'Mobile number must be 20 characters or less';
}

if (!empty($street_address) && strlen($street_address) > 255) {
    $errors[] = 'Street address must be 255 characters or less';
}

if (!empty($city) && strlen($city) > 100) {
    $errors[] = 'City must be 100 characters or less';
}

if (!empty($state) && strlen($state) > 100) {
    $errors[] = 'State must be 100 characters or less';
}

if (!empty($postal_code) && strlen($postal_code) > 20) {
    $errors[] = 'Postal code must be 20 characters or less';
}

if (!empty($country) && strlen($country) > 100) {
    $errors[] = 'Country must be 100 characters or less';
}

if (!empty($billing_street_address) && strlen($billing_street_address) > 255) {
    $errors[] = 'Billing street address must be 255 characters or less';
}

if (!empty($billing_city) && strlen($billing_city) > 100) {
    $errors[] = 'Billing city must be 100 characters or less';
}

if (!empty($billing_state) && strlen($billing_state) > 100) {
    $errors[] = 'Billing state must be 100 characters or less';
}

if (!empty($billing_postal_code) && strlen($billing_postal_code) > 20) {
    $errors[] = 'Billing postal code must be 20 characters or less';
}

if (!empty($billing_country) && strlen($billing_country) > 100) {
    $errors[] = 'Billing country must be 100 characters or less';
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->execute([$email, $user_id]);
if ($stmt->fetch()) {
    $errors[] = 'Email address is already in use by another account';
}

if (!empty($errors)) {
    $_SESSION['profile_errors'] = $errors;
    $_SESSION['profile_data'] = $_POST;
    header('Location: profile.php');
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE users SET 
        first_name = ?, last_name = ?, middle_name = ?, email = ?, phone = ?, mobile = ?,
        street_address = ?, city = ?, state = ?, postal_code = ?, country = ?,
        billing_street_address = ?, billing_city = ?, billing_state = ?, billing_postal_code = ?, billing_country = ?,
        updated_at = NOW()
        WHERE id = ?");
    
    $stmt->execute([
        $first_name, $last_name, $middle_name, $email, $phone, $mobile,
        $street_address, $city, $state, $postal_code, $country,
        $billing_street_address, $billing_city, $billing_state, $billing_postal_code, $billing_country,
        $user_id
    ]);
    
    $_SESSION['profile_success'] = 'Profile updated successfully';
    header('Location: profile.php');
    exit;
    
} catch (PDOException $e) {
    error_log('Profile update error: ' . $e->getMessage());
    $_SESSION['profile_errors'] = ['An error occurred while updating your profile. Please try again.'];
    header('Location: profile.php');
    exit;
}
?>


<?php
$host = 'localhost';
$dbname = 'your_database';
$username = 'your_username';
$password = 'your_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>


sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    mobile VARCHAR(20),
    street_address VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    billing_street_address VARCHAR(255),
    billing_city VARCHAR(100),
    billing_state VARCHAR(100),
    billing_postal_code VARCHAR(20),
    billing_country VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
?>