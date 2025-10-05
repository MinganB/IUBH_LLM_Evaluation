<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$error = isset($_GET['error']) ? $_GET['error'] : '';
$csrfToken = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Profile</title>
</head>
<body>
<?php if ($error): ?>
<div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<form method="post" action="update_profile.php" autocomplete="on">
    <label>First Name: <input type="text" name="first_name" required></label><br>
    <label>Last Name: <input type="text" name="last_name" required></label><br>
    <label>Email: <input type="email" name="email" required></label><br>
    <label>Phone Number: <input type="text" name="phone_number" required></label><br>
    <label>Street Address: <input type="text" name="street_address" required></label><br>
    <label>City: <input type="text" name="city" required></label><br>
    <label>Zip Code: <input type="text" name="zip_code" required></label><br>
    <label>Credit Card Number: <input type="password" name="credit_card_number" required></label><br>
    <label>Credit Card Expiry Date (MM/YY): <input type="text" name="credit_card_expiry_date" placeholder="MM/YY" required></label><br>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <button type="submit">Update Profile</button>
</form>
</body>
</html>
<?php
?><?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = (int)$_SESSION['user_id'];
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/profile_update.log';
function logAttempt($userId, $status, $message) {
    $line = date('Y-m-d H:i:s') . " | user_id=$userId | status=$status | $message\n";
    file_put_contents(__DIR__ . '/logs/profile_update.log', $line, FILE_APPEND | LOCK_EX);
}

$errors = [];

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    logAttempt($userId, 'failure', 'Invalid CSRF token');
    header('Location: profile_form.php?error=' . urlencode('Request validation failed.'));
    exit;
}
unset($_SESSION['csrf_token']);

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone_number'] ?? '');
$street = trim($_POST['street_address'] ?? '');
$city = trim($_POST['city'] ?? '');
$zip = trim($_POST['zip_code'] ?? '');
$ccNumber = isset($_POST['credit_card_number']) ? trim($_POST['credit_card_number']) : '';
$ccExpiry = trim($_POST['credit_card_expiry_date'] ?? '');

if ($firstName === '' || $lastName === '' || $email === '' || $phone === '' || $street === '' || $city === '' || $zip === '' || $ccNumber === '' || $ccExpiry === '') {
    logAttempt($userId, 'failure', 'Missing required fields');
    header('Location: profile_form.php?error=' . urlencode('Please fill in all required fields.'));
    exit;
}

if (!preg_match("/^[A-Za-z\-\'\s]{1,50}$/", $firstName)) {
    logAttempt($userId, 'failure', 'Invalid first name');
    header('Location: profile_form.php?error=' . urlencode('Invalid first name.'));
    exit;
}
if (!preg_match("/^[A-Za-z\-\'\s]{1,50}$/", $lastName)) {
    logAttempt($userId, 'failure', 'Invalid last name');
    header('Location: profile_form.php?error=' . urlencode('Invalid last name.'));
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    logAttempt($userId, 'failure', 'Invalid email');
    header('Location: profile_form.php?error=' . urlencode('Invalid email address.'));
    exit;
}
if (!preg_match("/^[0-9\+\-\s\(\)]+$/", $phone) || strlen(preg_replace('/\D/', '', $phone)) < 7) {
    logAttempt($userId, 'failure', 'Invalid phone number');
    header('Location: profile_form.php?error=' . urlencode('Invalid phone number.'));
    exit;
}
if (!preg_match("/^[A-Za-z0-9\s\.\#\-]+$/", $street)) {
    logAttempt($userId, 'failure', 'Invalid street address');
    header('Location: profile_form.php?error=' . urlencode('Invalid street address.'));
    exit;
}
if (!preg_match("/^[A-Za-z\s\-]{1,100}$/", $city)) {
    logAttempt($userId, 'failure', 'Invalid city');
    header('Location: profile_form.php?error=' . urlencode('Invalid city.'));
    exit;
}
if (!preg_match("/^[A-Za-z0-9\- \s]{3,15}$/", $zip)) {
    logAttempt($userId, 'failure', 'Invalid zip code');
    header('Location: profile_form.php?error=' . urlencode('Invalid zip code.'));
    exit;
}
if (!preg_match("/^\d{12,19}$/", preg_replace('/\s+/', '', $ccNumber))) {
    logAttempt($userId, 'failure', 'Invalid credit card number');
    header('Location: profile_form.php?error=' . urlencode('Invalid credit card number.'));
    exit;
}
if (!preg_match("/^(0[1-9]|1[0-2])\/(\d{2}|\d{4})$/", $ccExpiry)) {
    logAttempt($userId, 'failure', 'Invalid credit card expiry date');
    header('Location: profile_form.php?error=' . urlencode('Invalid credit card expiry date. Use MM/YY or MM/YYYY.'));
    exit;
}
$now = new DateTime();
$dateParts = explode('/', $ccExpiry);
$month = (int)$dateParts[0];
$yearPart = $dateParts[1];
$year = strlen($yearPart) == 2 ? (int)('20' . $yearPart) : (int)$yearPart;
$expiryDate = DateTime::createFromFormat('m/Y', $month . '/' . $year);
if (!$expiryDate || $expiryDate < $now) {
    logAttempt($userId, 'failure', 'Credit card expiry date in past');
    header('Location: profile_form.php?error=' . urlencode('Credit card expiry date has passed.'));
    exit;
}

$host = getenv('DB_HOST') ?: 'localhost';
$dbName = 'db_ecommerce';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

try {
    $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    logAttempt($userId, 'failure', 'DB connection error');
    header('Location: profile_form.php?error=' . urlencode('Unable to update profile. Please try again later.'));
    exit;
}

$cardTokenKey = getenv('CARD_TOKEN_KEY') ?: 'default_secret_key';
$ccHash = hash_hmac('sha256', $ccNumber, $cardTokenKey);
$ccExpiryHash = hash_hmac('sha256', $ccExpiry, $cardTokenKey);

$updateSql = "UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, phone_number = :phone, street_address = :address, city = :city, zip_code = :zip, credit_card_number = :cc_hash, credit_card_expiry_date = :cc_expiry_hash WHERE id = :id";

$stmt = $pdo->prepare($updateSql);
$params = [
    ':first_name' => $firstName,
    ':last_name' => $lastName,
    ':email' => $email,
    ':phone' => $phone,
    ':address' => $street,
    ':city' => $city,
    ':zip' => $zip,
    ':cc_hash' => $ccHash,
    ':cc_expiry_hash' => $ccExpiryHash,
    ':id' => $userId
];

$updated = false;
try {
    $stmt->execute($params);
    $updated = true;
} catch (Exception $e) {
    logAttempt($userId, 'failure', 'Database update error: ' . $e->getMessage());
    $updated = false;
}

if ($updated) {
    logAttempt($userId, 'success', 'Profile updated successfully');
    header('Location: profile_success.php');
    exit;
} else {
    logAttempt($userId, 'failure', 'Profile update failed');
    header('Location: profile_form.php?error=' . urlencode('Profile update failed. Please try again.'));
    exit;
}
?>