<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Profile</title>
</head>
<body>
    <form action="update_profile.php" method="POST">
        <label for="first_name">First Name:</label>
        <input type="text" id="first_name" name="first_name" required maxlength="50">
        
        <label for="last_name">Last Name:</label>
        <input type="text" id="last_name" name="last_name" required maxlength="50">
        
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required maxlength="100">
        
        <label for="phone_number">Phone Number:</label>
        <input type="tel" id="phone_number" name="phone_number" required maxlength="20">
        
        <label for="street_address">Street Address:</label>
        <input type="text" id="street_address" name="street_address" required maxlength="100">
        
        <label for="city">City:</label>
        <input type="text" id="city" name="city" required maxlength="50">
        
        <label for="zip_code">Zip Code:</label>
        <input type="text" id="zip_code" name="zip_code" required maxlength="10">
        
        <label for="credit_card_number">Credit Card Number:</label>
        <input type="text" id="credit_card_number" name="credit_card_number" required maxlength="19">
        
        <label for="credit_card_expiry_date">Credit Card Expiry Date:</label>
        <input type="text" id="credit_card_expiry_date" name="credit_card_expiry_date" required placeholder="MM/YY" maxlength="5">
        
        <button type="submit">Update Profile</button>
    </form>
</body>
</html>


<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile_form.php?error=unauthorized');
    exit;
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 100;
}

function validatePhone($phone) {
    return preg_match('/^\+?[\d\s\-\(\)\.]{10,20}$/', $phone);
}

function validateZipCode($zip) {
    return preg_match('/^[\w\s\-]{3,10}$/', $zip);
}

function validateCreditCard($cc) {
    $cc = preg_replace('/\s+/', '', $cc);
    return preg_match('/^\d{13,19}$/', $cc);
}

function validateExpiryDate($expiry) {
    return preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry);
}

function logAttempt($userId, $success, $message = '') {
    $logFile = '/secure/logs/profile_updates.log';
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAILED';
    $logEntry = "[{$timestamp}] User ID: {$userId} - {$status} - {$message}" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function encryptSensitiveData($data) {
    $key = hash('sha256', $_ENV['ENCRYPTION_KEY'] ?? 'default_key', true);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

try {
    $dsn = 'mysql:host=localhost;dbname=db_ecommerce;charset=utf8mb4';
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    logAttempt($_SESSION['user_id'], false, 'Database connection failed');
    header('Location: profile_form.php?error=system');
    exit;
}

$userId = (int)$_SESSION['user_id'];

$firstName = sanitizeInput($_POST['first_name'] ?? '');
$lastName = sanitizeInput($_POST['last_name'] ?? '');
$email = sanitizeInput($_POST['email'] ?? '');
$phone = sanitizeInput($_POST['phone_number'] ?? '');
$streetAddress = sanitizeInput($_POST['street_address'] ?? '');
$city = sanitizeInput($_POST['city'] ?? '');
$zipCode = sanitizeInput($_POST['zip_code'] ?? '');
$creditCard = sanitizeInput($_POST['credit_card_number'] ?? '');
$expiryDate = sanitizeInput($_POST['credit_card_expiry_date'] ?? '');

if (empty($firstName) || strlen($firstName) > 50) {
    logAttempt($userId, false, 'Invalid first name');
    header('Location: profile_form.php?error=invalid_first_name');
    exit;
}

if (empty($lastName) || strlen($lastName) > 50) {
    logAttempt($userId, false, 'Invalid last name');
    header('Location: profile_form.php?error=invalid_last_name');
    exit;
}

if (!validateEmail($email)) {
    logAttempt($userId, false, 'Invalid email');
    header('Location: profile_form.php?error=invalid_email');
    exit;
}

if (!validatePhone($phone)) {
    logAttempt($userId, false, 'Invalid phone');
    header('Location: profile_form.php?error=invalid_phone');
    exit;
}

if (empty($streetAddress) || strlen($streetAddress) > 100) {
    logAttempt($userId, false, 'Invalid street address');
    header('Location: profile_form.php?error=invalid_address');
    exit;
}

if (empty($city) || strlen($city) > 50) {
    logAttempt($userId, false, 'Invalid city');
    header('Location: profile_form.php?error=invalid_city');
    exit;
}

if (!validateZipCode($zipCode)) {
    logAttempt($userId, false, 'Invalid zip code');
    header('Location: profile_form.php?error=invalid_zip');
    exit;
}

if (!validateCreditCard($creditCard)) {
    logAttempt($userId, false, 'Invalid credit card');
    header('Location: profile_form.php?error=invalid_cc');
    exit;
}

if (!validateExpiryDate($expiryDate)) {
    logAttempt($userId, false, 'Invalid expiry date');
    header('Location: profile_form.php?error=invalid_expiry');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->fetchColumn()) {
        logAttempt($userId, false, 'Email already exists');
        header('Location: profile_form.php?error=email_exists');
        exit;
    }
    
    $encryptedCreditCard = encryptSensitiveData($creditCard);
    
    $sql = "UPDATE users SET 
            first_name = ?, 
            last_name = ?, 
            email = ?, 
            phone_number = ?, 
            street_address = ?, 
            city = ?, 
            zip_code = ?, 
            credit_card_number = ?, 
            credit_card_expiry_date = ?,
            updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $firstName,
        $lastName,
        $email,
        $phone,
        $streetAddress,
        $city,
        $zipCode,
        $encryptedCreditCard,
        $expiryDate,
        $userId
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        logAttempt($userId, true, 'Profile updated successfully');
        header('Location: profile_success.php');
        exit;
    } else {
        logAttempt($userId, false, 'No rows affected');
        header('Location: profile_form.php?error=no_changes');
        exit;
    }
    
} catch (PDOException $e) {
    logAttempt($userId, false, 'Database error during update');
    header('Location: profile_form.php?error=system');
    exit;
}
?>


<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Profile Updated Successfully</title>
</head>
<body>
    <h1>Profile Updated Successfully</h1>
    <p>Your profile has been updated successfully.</p>
    <a href="dashboard.php">Return to Dashboard</a>
</body>
</html>
?>