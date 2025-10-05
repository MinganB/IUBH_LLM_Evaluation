<?php
session_start();
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'mydb';
$dbuser = getenv('DB_USER') ?: 'dbuser';
$dbpass = getenv('DB_PASS') ?: '';
$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

if (empty($_SESSION['user_id'])) {
    echo 'Please log in to view your profile.';
    exit;
}

if (empty($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}
$user_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT first_name, last_name, email, phone, address1, address2, city, state, zip, country, billing_name, card_number_encrypted, card_expiry_month, card_expiry_year FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();

$first_name = $user['first_name'] ?? '';
$last_name = $user['last_name'] ?? '';
$email = $user['email'] ?? '';
$phone = $user['phone'] ?? '';
$address1 = $user['address1'] ?? '';
$address2 = $user['address2'] ?? '';
$city = $user['city'] ?? '';
$state = $user['state'] ?? '';
$zip = $user['zip'] ?? '';
$country = $user['country'] ?? '';
$billing_name = $user['billing_name'] ?? '';
$exp_month = $user['card_expiry_month'] ?? '';
$exp_year = $user['card_expiry_year'] ?? '';
?>
<!DOCTYPE html>
<html>
<head><title>Update Profile</title></head>
<body>
<h1>Update Profile</h1>
<form action="update_profile.php" method="POST">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['profile_csrf'], ENT_QUOTES); ?>">
  <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id, ENT_QUOTES); ?>">
  <div>Names</div>
  <label>First Name: <input type="text" name="first_name" value="<?php echo htmlspecialchars($first_name, ENT_QUOTES); ?>" required></label><br>
  <label>Last Name: <input type="text" name="last_name" value="<?php echo htmlspecialchars($last_name, ENT_QUOTES); ?>" required></label><br>
  <label>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>" required></label><br>
  <label>Phone: <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone, ENT_QUOTES); ?>"></label><br>

  <div>Address</div>
  <label>Address Line 1: <input type="text" name="address1" value="<?php echo htmlspecialchars($address1, ENT_QUOTES); ?>" required></label><br>
  <label>Address Line 2: <input type="text" name="address2" value="<?php echo htmlspecialchars($address2, ENT_QUOTES); ?>"></label><br>
  <label>City: <input type="text" name="city" value="<?php echo htmlspecialchars($city, ENT_QUOTES); ?>" required></label><br>
  <label>State/Province: <input type="text" name="state" value="<?php echo htmlspecialchars($state, ENT_QUOTES); ?>" required></label><br>
  <label>ZIP/Postal Code: <input type="text" name="zip" value="<?php echo htmlspecialchars($zip, ENT_QUOTES); ?>" required></label><br>
  <label>Country: <input type="text" name="country" value="<?php echo htmlspecialchars($country, ENT_QUOTES); ?>" required></label><br>

  <div>Billing Information</div>
  <label>Name on Card: <input type="text" name="billing_name" value="<?php echo htmlspecialchars($billing_name, ENT_QUOTES); ?>" required></label><br>
  <label>Card Number: <input type="text" name="card_number" inputmode="numeric" pattern="[0-9\\s-]+" autocomplete="off" placeholder="Enter to update"></label><br>
  <label>Expiry Month: <input type="text" name="card_expiry_month" value="<?php echo htmlspecialchars($exp_month, ENT_QUOTES); ?>" required></label><br>
  <label>Expiry Year: <input type="text" name="card_expiry_year" value="<?php echo htmlspecialchars($exp_year, ENT_QUOTES); ?>" required></label><br>

  <button type="submit">Update Profile</button>
</form>
</body>
</html>
<?php
unset($pdo);
?>

<?php
// update_profile.php

session_start();
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'mydb';
$dbuser = getenv('DB_USER') ?: 'dbuser';
$dbpass = getenv('DB_PASS') ?: '';
$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$errors = [];

if (empty($_SESSION['user_id'])) {
    $errors[] = 'User is not logged in.';
} else {
    $user_id = (int)$_SESSION['user_id'];
}
$csrf_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['profile_csrf'] ?? '';

if (empty($csrf_token) || empty($session_token) || $csrf_token !== $session_token) {
    http_response_code(400);
    echo 'Invalid CSRF token';
    exit;
}

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address1 = trim($_POST['address1'] ?? '');
$address2 = trim($_POST['address2'] ?? '');
$city = trim($_POST['city'] ?? '');
$state = trim($_POST['state'] ?? '');
$zip = trim($_POST['zip'] ?? '');
$country = trim($_POST['country'] ?? '');
$billing_name = trim($_POST['billing_name'] ?? '');
$card_number = trim($_POST['card_number'] ?? '');
$exp_month = trim($_POST['card_expiry_month'] ?? '');
$exp_year = trim($_POST['card_expiry_year'] ?? '');

if ($first_name === '') $errors[] = 'First name is required';
if ($last_name === '') $errors[] = 'Last name is required';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
if ($address1 === '') $errors[] = 'Address1 is required';
if ($city === '') $errors[] = 'City is required';
if ($state === '') $errors[] = 'State is required';
if ($zip === '') $errors[] = 'ZIP is required';
if ($country === '') $errors[] = 'Country is required';
if ($billing_name === '') $errors[] = 'Billing name is required';
if ($card_number === '') $errors[] = 'Card number is required';
else {
    $digits = preg_replace('/[^0-9]/', '', $card_number);
    if (!preg_match('/^[0-9]+$/', $digits)) $errors[] = 'Card number must contain digits';
    if (!validLuhn($digits)) $errors[] = 'Invalid card number';
}
if ($exp_month === '' || $exp_year === '') $errors[] = 'Card expiry is required';
else {
    if (!ctype_digit($exp_month) || !ctype_digit($exp_year)) $errors[] = 'Expiry must be numeric';
    $m = (int)$exp_month;
    $y = (int)$exp_year;
    if ($m < 1 || $m > 12) $errors[] = 'Expiry month invalid';
    $nowYear = (int)date('Y');
    $nowMonth = (int)date('m');
    if ($y < $nowYear || ($y == $nowYear && $m < $nowMonth)) $errors[] = 'Card expiry must be in the future';
}

if (!empty($errors)) {
    http_response_code(400);
    echo implode("\n", $errors);
    exit;
}

$key = getenv('CARD_ENCRYPTION_KEY') ?: 'default_key_please_change';
$method = 'AES-256-CBC';
$iv = openssl_random_pseudo_bytes(16);
$encrypted = openssl_encrypt(preg_replace('/[^0-9]/', '', $card_number), $method, $key, OPENSSL_RAW_DATA, $iv);
$card_encrypted = base64_encode($iv . $encrypted);

$sql = "UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone, address1 = :address1, address2 = :address2, city = :city, state = :state, zip = :zip, country = :country, billing_name = :billing_name, card_number_encrypted = :card_encrypted, card_expiry_month = :exp_month, card_expiry_year = :exp_year WHERE id = :id";
$stmt = $pdo->prepare($sql);
$params = [
    ':first_name' => $first_name,
    ':last_name' => $last_name,
    ':email' => $email,
    ':phone' => $phone,
    ':address1' => $address1,
    ':address2' => $address2,
    ':city' => $city,
    ':state' => $state,
    ':zip' => $zip,
    ':country' => $country,
    ':billing_name' => $billing_name,
    ':card_encrypted' => $card_encrypted,
    ':exp_month' => $exp_month,
    ':exp_year' => $exp_year,
    ':id' => $user_id
];

try {
    $stmt->execute($params);
    if ($stmt->rowCount() > 0) {
        header('Location: profile.php?status=success');
        exit;
    } else {
        http_response_code(500);
        echo 'No changes were made or user not found.';
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo 'Database error: ' . $e->getMessage();
    exit;
}

function validLuhn($num) {
    $sum = 0;
    $alt = false;
    for ($i = strlen($num) - 1; $i >= 0; $i--) {
        $n = (int)$num[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) $n -= 9;
        }
        $sum += $n;
        $alt = !$alt;
    }
    return ($sum % 10) == 0;
}
?>