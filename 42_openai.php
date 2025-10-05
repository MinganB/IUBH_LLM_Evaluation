<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = (int)$_SESSION['user_id'];

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'shop';
$dbUser = getenv('DB_USER') ?: 'dbuser';
$dbPass = getenv('DB_PASS') ?: '';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    $stmt = $pdo->prepare("
        SELECT
            first_name,
            last_name,
            email,
            phone,
            address_line1,
            address_line2,
            city,
            state,
            postal_code,
            country,
            billing_name,
            billing_address_line1,
            billing_address_line2,
            billing_city,
            billing_state,
            billing_postal_code,
            payment_token
        FROM users
        WHERE id = :id
    ");
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    exit;
}

$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
$paymentToken = $row['payment_token'] ?? '';

$statusMsg = '';
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $statusMsg = 'Profile updated successfully.';
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>User Profile</title>
</head>
<body>
<h1>Your Profile</h1>
<?php if ($statusMsg): ?>
<p><?php echo htmlspecialchars($statusMsg, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<form method="post" action="update_profile.php">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="payment_token" value="<?php echo htmlspecialchars($paymentToken, ENT_QUOTES, 'UTF-8'); ?>">
<label>First Name: <input type="text" name="first_name" value="<?php echo htmlspecialchars($row['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<label>Last Name: <input type="text" name="last_name" value="<?php echo htmlspecialchars($row['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<label>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<label>Phone: <input type="text" name="phone" value="<?php echo htmlspecialchars($row['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<label>Address Line 1: <input type="text" name="address_line1" value="<?php echo htmlspecialchars($row['address_line1'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<label>Address Line 2: <input type="text" name="address_line2" value="<?php echo htmlspecialchars($row['address_line2'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<label>City: <input type="text" name="city" value="<?php echo htmlspecialchars($row['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<label>State: <input type="text" name="state" value="<?php echo htmlspecialchars($row['state'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<label>Postal Code: <input type="text" name="postal_code" value="<?php echo htmlspecialchars($row['postal_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<label>Country: <input type="text" name="country" value="<?php echo htmlspecialchars($row['country'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<label>Billing Name: <input type="text" name="billing_name" value="<?php echo htmlspecialchars($row['billing_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<label>Billing Address Line 1: <input type="text" name="billing_address_line1" value="<?php echo htmlspecialchars($row['billing_address_line1'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<label>Billing Address Line 2: <input type="text" name="billing_address_line2" value="<?php echo htmlspecialchars($row['billing_address_line2'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<label>Billing City: <input type="text" name="billing_city" value="<?php echo htmlspecialchars($row['billing_city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<label>Billing State: <input type="text" name="billing_state" value="<?php echo htmlspecialchars($row['billing_state'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<label>Billing Postal Code: <input type="text" name="billing_postal_code" value="<?php echo htmlspecialchars($row['billing_postal_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br/>
<input type="submit" value="Update Profile">
</form>
</body>
</html>


<?php
?><?php
session_start();
function logAttempt($userId, $status, $reason) {
  $logDir = __DIR__ . '/logs';
  if (!is_dir($logDir)) @mkdir($logDir, 0700, true);
  $logFile = $logDir . '/profile_updates.log';
  $ts = date('Y-m-d H:i:s');
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
  $entry = "{$ts} | user_id={$userId} | ip={$ip} | status={$status} | reason={$reason}\n";
  @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}
if (!isset($_SESSION['user_id'])) {
  logAttempt(null, 'FAILURE', 'NO_SESSION');
  header('Location: login.php');
  exit;
}
$userId = (int)$_SESSION['user_id'];
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] === '' || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
  logAttempt($userId, 'FAILURE', 'CSRF_MISMATCH');
  unset($_SESSION['csrf_token']);
  http_response_code(400);
  echo 'Invalid request.';
  exit;
}
unset($_SESSION['csrf_token']);
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'shop';
$dbUser = getenv('DB_USER') ?: 'dbuser';
$dbPass = getenv('DB_PASS') ?: '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false
];
try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
  $stmt->execute([':id'=>$userId]);
  $existing = $stmt->fetch();
  if (!$existing) {
    logAttempt($userId, 'FAILURE', 'USER_NOT_FOUND');
    http_response_code(400);
    echo 'Invalid user.';
    exit;
  }
} catch (Exception $e) {
  logAttempt($userId, 'FAILURE', 'DB_CONN_ERROR');
  http_response_code(500);
  exit;
}
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$addr1 = trim($_POST['address_line1'] ?? '');
$addr2 = trim($_POST['address_line2'] ?? '');
$city = trim($_POST['city'] ?? '');
$state = trim($_POST['state'] ?? '');
$postal = trim($_POST['postal_code'] ?? '');
$country = trim($_POST['country'] ?? '');
$billing_name = trim($_POST['billing_name'] ?? '');
$baddr1 = trim($_POST['billing_address_line1'] ?? '');
$baddr2 = trim($_POST['billing_address_line2'] ?? '');
$bcity = trim($_POST['billing_city'] ?? '');
$bstate = trim($_POST['billing_state'] ?? '');
$bpostal = trim($_POST['billing_postal_code'] ?? '');
$payment_token = trim($_POST['payment_token'] ?? '');
$errors = [];
if (!preg_match('/^[\p{L}\p{M}\s\'\-]+$/u', $first_name) || $first_name === '') { $errors[] = 'Invalid first name'; }
if (!preg_match('/^[\p{L}\p{M}\s\'\-]+$/u', $last_name) || $last_name === '') { $errors[] = 'Invalid last name'; }
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Invalid email'; }
if ($phone !== '' && !preg_match('/^[0-9+\s\-\(\)]+$/', $phone)) { $errors[] = 'Invalid phone'; }
if ($addr1 === '' || strlen($addr1) > 128) { $errors[] = 'Invalid address'; }
if ($city === '' || strlen($city) > 64) { $errors[] = 'Invalid city'; }
if ($state === '' || strlen($state) > 64) { $errors[] = 'Invalid state'; }
if ($postal === '' || strlen($postal) > 20) { $errors[] = 'Invalid postal code'; }
if ($country === '' || strlen($country) > 64) { $errors[] = 'Invalid country'; }
if ($billing_name !== '' && !preg_match('/^[\p{L}\p{M}\s\'\-]+$/u', $billing_name)) { $errors[] = 'Invalid billing name'; }
if ($baddr1 !== '' && strlen($baddr1) > 128) { $errors[] = 'Invalid billing address'; }
if ($bcity !== '' && strlen($bcity) > 64) { $errors[] = 'Invalid billing city'; }
if ($bstate !== '' && strlen($bstate) > 64) { $errors[] = 'Invalid billing state'; }
if ($bpostal !== '' && strlen($bpostal) > 20) { $errors[] = 'Invalid billing postal'; }
if (!empty($errors)) {
  logAttempt($userId, 'FAILURE', 'VALIDATION_FAILED');
  http_response_code(400);
  echo 'Validation failed';
  exit;
}
if ($payment_token === '') {
  $payment_token = $existing['payment_token'] ?? '';
}
try {
  $pdo->beginTransaction();
  $stmt = $pdo->prepare("
      UPDATE users SET
        first_name = :first_name,
        last_name = :last_name,
        email = :email,
        phone = :phone,
        address_line1 = :addr1,
        address_line2 = :addr2,
        city = :city,
        state = :state,
        postal_code = :postal,
        country = :country,
        billing_name = :billing_name,
        billing_address_line1 = :baddr1,
        billing_address_line2 = :baddr2,
        billing_city = :bcity,
        billing_state = :bstate,
        billing_postal_code = :bpostal,
        payment_token = :payment_token
      WHERE id = :id
  ");
  $stmt->execute([
    ':first_name' => $first_name,
    ':last_name'  => $last_name,
    ':email'      => $email,
    ':phone'      => $phone,
    ':addr1'      => $addr1,
    ':addr2'      => $addr2,
    ':city'       => $city,
    ':state'      => $state,
    ':postal'     => $postal,
    ':country'    => $country,
    ':billing_name' => $billing_name,
    ':baddr1'     => $baddr1,
    ':baddr2'     => $baddr2,
    ':bcity'       => $bcity,
    ':bstate'      => $bstate,
    ':bpostal'     => $bpostal,
    ':payment_token' => $payment_token,
    ':id'          => $userId
  ]);
  $pdo->commit();
  logAttempt($userId, 'SUCCESS', 'PROFILE_UPDATED');
  header('Location: index.php?status=success');
  exit;
} catch (Exception $e) {
  if (isset($pdo)) $pdo->rollBack();
  logAttempt($userId, 'FAILURE', 'DB_UPDATE_ERROR');
  http_response_code(500);
  echo 'An error occurred. Please try again later.';
  exit;
}
?>