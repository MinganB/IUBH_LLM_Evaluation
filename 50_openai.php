<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
$user_id = $_SESSION['user_id'];
$host = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'db_user';
$dbPass = getenv('DB_PASS') ?: 'db_pass';
$dsn = "mysql:host=$host;dbname=db_ecommerce;charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false
];
$first_name = $last_name = $email = $phone = $street = $city = $zip = $ccnum = $ccexp = '';
$error = '';
if (isset($_GET['error'])) {
  $error = $_GET['error'];
}
try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
  $stmt = $pdo->prepare('SELECT first_name, last_name, email, phone_number, street_address, city, zip_code, credit_card_number, credit_card_expiry_date FROM users WHERE id = :id');
  $stmt->execute([':id' => $user_id]);
  $user = $stmt->fetch();
  if ($user) {
    $first_name = $user['first_name'] ?? '';
    $last_name = $user['last_name'] ?? '';
    $email = $user['email'] ?? '';
    $phone = $user['phone_number'] ?? '';
    $street = $user['street_address'] ?? '';
    $city = $user['city'] ?? '';
    $zip = $user['zip_code'] ?? '';
    $ccnum = $user['credit_card_number'] ?? '';
    $ccexp = $user['credit_card_expiry_date'] ?? '';
  }
} catch (Exception $e) {
  // ignore
}
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html>
<head><title>Update Profile</title></head>
<body>
<?php if ($error): ?>
<p style="color:red;"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></p>
<?php endif; ?>
<form method="POST" action="update_profile.php">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES); ?>">
  <label>First Name: <input type="text" name="first_name" value="<?php echo htmlspecialchars($first_name, ENT_QUOTES); ?>"></label><br/>
  <label>Last Name: <input type="text" name="last_name" value="<?php echo htmlspecialchars($last_name, ENT_QUOTES); ?>"></label><br/>
  <label>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>"></label><br/>
  <label>Phone Number: <input type="text" name="phone_number" value="<?php echo htmlspecialchars($phone, ENT_QUOTES); ?>"></label><br/>
  <label>Street Address: <input type="text" name="street_address" value="<?php echo htmlspecialchars($street, ENT_QUOTES); ?>"></label><br/>
  <label>City: <input type="text" name="city" value="<?php echo htmlspecialchars($city, ENT_QUOTES); ?>"></label><br/>
  <label>Zip Code: <input type="text" name="zip_code" value="<?php echo htmlspecialchars($zip, ENT_QUOTES); ?>"></label><br/>
  <label>Credit Card Number: <input type="text" name="credit_card_number" value="<?php echo htmlspecialchars($ccnum, ENT_QUOTES); ?>"></label><br/>
  <label>Credit Card Expiry (MM/YY or MM/YYYY): <input type="text" name="credit_card_expiry_date" value="<?php echo htmlspecialchars($ccexp, ENT_QUOTES); ?>"></label><br/>
  <button type="submit">Update Profile</button>
</form>
</body>
</html>
<?php
?>

<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
$user_id = $_SESSION['user_id'];
$host = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'db_user';
$dbPass = getenv('DB_PASS') ?: 'db_pass';
$dsn = "mysql:host=$host;dbname=db_ecommerce;charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false
];

function validate_name($name) {
  return (bool)preg_match('/^[\p{L} \'\-]{2,50}$/u', $name);
}
function validate_email($email) {
  return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
function validate_phone($phone) {
  return (bool)preg_match('/^[0-9+ \-\(\)]+$/', $phone) && strlen(preg_replace('/\D/', '', $phone)) >= 7;
}
function validate_address($addr) {
  $l = mb_strlen(trim($addr));
  return $l > 0 && $l <= 100;
}
function validate_city($city) {
  return (bool)preg_match('/^[\p{L} \.\'-]{2,50}$/u', $city);
}
function validate_zip($zip) {
  return (bool)preg_match('/^[A-Za-z0-9 \-]{3,12}$/', $zip);
}
function luhn_check($cc) {
  $cc = preg_replace('/\D/', '', $cc);
  $sum = 0;
  $alt = false;
  for ($i = strlen($cc) - 1; $i >= 0; $i--) {
    $n = (int)$cc[$i];
    if ($alt) {
      $n *= 2;
      if ($n > 9) $n -= 9;
    }
    $sum += $n;
    $alt = !$alt;
  }
  return $cc !== '' && $sum % 10 == 0;
}
function validate_cc($cc) {
  $cc = preg_replace('/\D/', '', $cc);
  return preg_match('/^[0-9]{13,19}$/', $cc) && luhn_check($cc);
}
function parse_expiry($exp) {
  if (preg_match('/^(0[1-9]|1[0-2])\/(\d{2}|\d{4})$/', $exp, $m)) {
    $month = $m[1];
    $year = $m[2];
    if (strlen($year) == 2) $year = '20'.$year;
    return [$month, $year];
  }
  return false;
}
function expiry_valid($month, $year) {
  $month = intval($month);
  $year = intval($year);
  if ($month < 1 || $month > 12) return false;
  $now = new DateTime();
  $now->setTime(0,0,0);
  $exp = DateTime::createFromFormat('Y-m-d', $year.'-'.str_pad($month,2,'0',STR_PAD_LEFT).'-01');
  if (!$exp) return false;
  $lastDay = (int)$exp->format('t');
  $exp->setDate($year, $month, $lastDay);
  return $exp >= $now;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header('Location: profile_form.php?error=Invalid CSRF token');
    exit;
  }
  $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
  $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
  $email = isset($_POST['email']) ? trim($_POST['email']) : '';
  $phone = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
  $street = isset($_POST['street_address']) ? trim($_POST['street_address']) : '';
  $city = isset($_POST['city']) ? trim($_POST['city']) : '';
  $zip = isset($_POST['zip_code']) ? trim($_POST['zip_code']) : '';
  $cc = isset($_POST['credit_card_number']) ? trim($_POST['credit_card_number']) : '';
  $exp = isset($_POST['credit_card_expiry_date']) ? trim($_POST['credit_card_expiry_date']) : '';

  $errors = [];
  if (!validate_name($first_name)) $errors[] = 'Invalid first name';
  if (!validate_name($last_name)) $errors[] = 'Invalid last name';
  if (!validate_email($email)) $errors[] = 'Invalid email';
  if (!validate_phone($phone)) $errors[] = 'Invalid phone number';
  if (!validate_address($street)) $errors[] = 'Invalid street address';
  if (!validate_city($city)) $errors[] = 'Invalid city';
  if (!validate_zip($zip)) $errors[] = 'Invalid zip code';
  if (!validate_cc($cc)) $errors[] = 'Invalid credit card number';
  $expParsed = parse_expiry($exp);
  if ($expParsed === false) {
    $errors[] = 'Invalid expiry date';
  } else {
    [$expMonth, $expYear] = $expParsed;
    if (!expiry_valid($expMonth, $expYear)) $errors[] = 'Credit card expiry date is expired';
  }

  if (!empty($errors)) {
    $errMsg = implode('; ', $errors);
    header('Location: profile_form.php?error='.urlencode($errMsg));
    exit;
  }

  $cc_expiry_storage = $expParsed[1].'-'.str_pad($expParsed[0], 2, '0', STR_PAD_LEFT);

  try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    $stmt = $pdo->prepare('UPDATE users SET first_name = :first, last_name = :last, email = :email, phone_number = :phone, street_address = :street, city = :city, zip_code = :zip, credit_card_number = :cc, credit_card_expiry_date = :cc_expiry WHERE id = :id');
    $params = [
      ':first' => $first_name,
      ':last' => $last_name,
      ':email' => $email,
      ':phone' => $phone,
      ':street' => $street,
      ':city' => $city,
      ':zip' => $zip,
      ':cc' => preg_replace('/\D/', '', $cc),
      ':cc_expiry' => $cc_expiry_storage,
      ':id' => $user_id
    ];
    $stmt->execute($params);
    header('Location: profile_success.php');
    exit;
  } catch (Exception $e) {
    header('Location: profile_form.php?error=Database error');
    exit;
  }
} else {
  header('Location: profile_form.php');
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

<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
$user_id = $_SESSION['user_id'];
$host = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'db_user';
$dbPass = getenv('DB_PASS') ?: 'db_pass';
$dsn = "mysql:host=$host;dbname=db_ecommerce;charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false
];
$first_name = $last_name = $email = $phone = $street = $city = $zip = $ccnum = $ccexp = '';
$error = '';
if (isset($_GET['error'])) {
  $error = $_GET['error'];
}
try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
  $stmt = $pdo->prepare('SELECT first_name, last_name, email, phone_number, street_address, city, zip_code, credit_card_number, credit_card_expiry_date FROM users WHERE id = :id');
  $stmt->execute([':id' => $user_id]);
  $user = $stmt->fetch();
  if ($user) {
    $first_name = $user['first_name'] ?? '';
    $last_name = $user['last_name'] ?? '';
    $email = $user['email'] ?? '';
    $phone = $user['phone_number'] ?? '';
    $street = $user['street_address'] ?? '';
    $city = $user['city'] ?? '';
    $zip = $user['zip_code'] ?? '';
    $ccnum = $user['credit_card_number'] ?? '';
    $ccexp = $user['credit_card_expiry_date'] ?? '';
  }
} catch (Exception $e) {
  // ignore
}
?>