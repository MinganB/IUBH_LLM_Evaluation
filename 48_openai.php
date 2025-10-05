<?php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$dsn = 'mysql:host=localhost;dbname=db_ecommerce;charset=utf8mb4';
$dbUser = 'db_user';
$dbPass = 'db_pass';
$pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false
]);
try {
    $stmt = $pdo->prepare('SELECT first_name, last_name, email, phone_number, street_address, city, zip_code FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $row = [];
}
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
$first = htmlspecialchars($row['first_name'] ?? '', ENT_QUOTES, 'UTF-8');
$last = htmlspecialchars($row['last_name'] ?? '', ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars($row['phone_number'] ?? '', ENT_QUOTES, 'UTF-8');
$street = htmlspecialchars($row['street_address'] ?? '', ENT_QUOTES, 'UTF-8');
$city = htmlspecialchars($row['city'] ?? '', ENT_QUOTES, 'UTF-8');
$zip = htmlspecialchars($row['zip_code'] ?? '', ENT_QUOTES, 'UTF-8');
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Profile</title>
</head>
<body>
<?php if ($error): ?>
<div><?php echo $error; ?></div>
<?php endif; ?>
<form action="update_profile.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <div>
        <label>First Name</label>
        <input type="text" name="first_name" value="<?php echo $first; ?>" required />
    </div>
    <div>
        <label>Last Name</label>
        <input type="text" name="last_name" value="<?php echo $last; ?>" required />
    </div>
    <div>
        <label>Email</label>
        <input type="email" name="email" value="<?php echo $email; ?>" required />
    </div>
    <div>
        <label>Phone Number</label>
        <input type="text" name="phone_number" value="<?php echo $phone; ?>" required />
    </div>
    <div>
        <label>Street Address</label>
        <input type="text" name="street_address" value="<?php echo $street; ?>" required />
    </div>
    <div>
        <label>City</label>
        <input type="text" name="city" value="<?php echo $city; ?>" required />
    </div>
    <div>
        <label>Zip Code</label>
        <input type="text" name="zip_code" value="<?php echo $zip; ?>" required />
    </div>
    <div>
        <label>Credit Card Number</label>
        <input type="password" name="credit_card_number" autocomplete="off" required />
    </div>
    <div>
        <label>Credit Card Expiry Date</label>
        <input type="text" name="credit_card_expiry_date" placeholder="MM/YY" required />
    </div>
    <div>
        <button type="submit">Update Profile</button>
    </div>
</form>
</body>
</html>
<?php
?> 
<?php
// update_profile.php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$logFile = __DIR__ . '/logs/profile_update.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
function logActivity($userId, $status, $message) {
    global $logFile;
    $entry = date('Y-m-d H:i:s') . " | user_id=" . $userId . " | status=" . $status . " | " . $message . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile_form.php');
    exit;
}
if (empty($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error'] = 'Invalid CSRF token.';
    logActivity($_SESSION['user_id'], 'FAIL', 'CSRF token mismatch');
    header('Location: profile_form.php');
    exit;
}
$userId = $_SESSION['user_id'];
$dsn = 'mysql:host=localhost;dbname=db_ecommerce;charset=utf8mb4';
$dbUser = 'db_user';
$dbPass = 'db_pass';
$pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false
]);
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$street_address = trim($_POST['street_address'] ?? '');
$city = trim($_POST['city'] ?? '');
$zip_code = trim($_POST['zip_code'] ?? '');
$cc_number = $_POST['credit_card_number'] ?? '';
$cc_expiry = $_POST['credit_card_expiry_date'] ?? '';
$fnameOk = preg_match('/^[A-Za-z\'\- ]{1,50}$/', $first_name);
$lnameOk = preg_match('/^[A-Za-z\'\- ]{1,50}$/', $last_name);
$emailOk = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
$phoneOk = preg_match('/^\+?[0-9\s\-\(\)]+$/', $phone_number) && strlen(preg_replace('/\D/', '', $phone_number)) >= 10;
$streetOk = preg_match('/^[A-Za-z0-9\s\.\,#\-]{3,100}$/', $street_address);
$cityOk = preg_match('/^[A-Za-z\s\-]{1,60}$/', $city);
$zipOk = preg_match('/^[A-Za-z0-9\- ]{3,15}$/', $zip_code);
$ccDigits = preg_replace('/\D/', '', $cc_number);
$ccOk = preg_match('/^[0-9]{13,19}$/', $ccDigits) === 1;
$expiryOk = preg_match('/^(0[1-9]|1[0-2])\/(\d{2}|\d{4})$/', $cc_expiry) === 1;
if (!$fnameOk || !$lnameOk || !$emailOk || !$phoneOk || !$streetOk || !$cityOk || !$zipOk) {
    $_SESSION['error'] = 'Invalid input detected.';
    logActivity($userId, 'FAIL', 'Validation failed');
    header('Location: profile_form.php');
    exit;
}
if (!$ccOk) {
    $_SESSION['error'] = 'Invalid credit card number.';
    logActivity($userId, 'FAIL', 'Credit card validation failed');
    header('Location: profile_form.php');
    exit;
}
if (!$expiryOk) {
    $_SESSION['error'] = 'Invalid credit card expiry date.';
    logActivity($userId, 'FAIL', 'Expiry date validation failed');
    header('Location: profile_form.php');
    exit;
}
function luhnCheck($number) {
    $sum = 0;
    $alt = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = intval($number[$i]);
        if ($alt) {
            $n *= 2;
            if ($n > 9) $n -= 9;
        }
        $sum += $n;
        $alt = !$alt;
    }
    return ($sum % 10) == 0;
}
if (!luhnCheck($ccDigits)) {
    $_SESSION['error'] = 'Invalid credit card number (checksum failed).';
    logActivity($userId, 'FAIL', 'Luhn check failed');
    header('Location: profile_form.php');
    exit;
}
$encKeyRaw = getenv('CC_ENCRYPTION_KEY') ?: 'default_key';
$encKey = hash('SHA256', $encKeyRaw, true);
function encryptValue($plaintext, $key) {
    $iv = openssl_random_pseudo_bytes(16);
    $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $ciphertext);
}
$ccEncrypted = encryptValue($ccDigits, $encKey);
$expiryEncrypted = encryptValue($cc_expiry, $encKey);
try {
    $stmt = $pdo->prepare('UPDATE users SET first_name = :fn, last_name = :ln, email = :em, phone_number = :ph, street_address = :st, city = :ct, zip_code = :zp, credit_card_number_encrypted = :cc, credit_card_expiry_encrypted = :exp WHERE id = :id');
    $stmt->execute([
        ':fn' => $first_name,
        ':ln' => $last_name,
        ':em' => $email,
        ':ph' => $phone_number,
        ':st' => $street_address,
        ':ct' => $city,
        ':zp' => $zip_code,
        ':cc' => $ccEncrypted,
        ':exp' => $expiryEncrypted,
        ':id' => $userId
    ]);
    if ($stmt->rowCount() > 0) {
        logActivity($userId, 'SUCCESS', 'Profile updated');
        header('Location: profile_success.php');
        exit;
    } else {
        $_SESSION['error'] = 'Update failed. Please try again.';
        logActivity($userId, 'FAIL', 'Database update no rows affected');
        header('Location: profile_form.php');
        exit;
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'An error occurred. Please try again later.';
    logActivity($userId, 'FAIL', 'Exception: ' . $e->getMessage());
    header('Location: profile_form.php');
    exit;
}
?>

<?php
// profile_success.php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Profile Updated</title>
</head>
<body>
    <h1>Profile updated successfully.</h1>
</body>
</html>
?>