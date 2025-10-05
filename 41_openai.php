<?php
session_start();
$dbHost = 'localhost';
$dbName = 'ecommerce';
$dbUser = 'dbuser';
$dbPass = 'dbpass';
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
} catch (PDOException $e) {
  http_response_code(500);
  echo 'Database connection error';
  exit;
}
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, address_line1, address_line2, city, state, zip, country, billing_name, billing_address1, billing_address2, billing_city, billing_state, billing_zip, billing_country FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();
if (!$user) {
  http_response_code(404);
  echo 'User not found';
  exit;
}
$csrf = $_SESSION['csrf'] ?? null;
if (!$csrf) {
  $csrf = bin2hex(random_bytes(32));
  $_SESSION['csrf'] = $csrf;
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>User Profile</title></head>
<body>
<?php
if (isset($_SESSION['profile_errors']) && is_array($_SESSION['profile_errors'])) {
  foreach ($_SESSION['profile_errors'] as $err) {
    echo "<p style='color:red;margin:6px 0;'>".htmlspecialchars($err)."</p>";
  }
  unset($_SESSION['profile_errors']);
}
if (isset($_SESSION['profile_success'])) {
  echo "<p style='color:green;margin:6px 0;'>".htmlspecialchars($_SESSION['profile_success'])."</p>";
  unset($_SESSION['profile_success']);
}
?>
<h1>Update Profile</h1>
<form method="post" action="update_profile.php">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
<input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
<div>
  <label>First Name</label>
  <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
</div>
<div>
  <label>Last Name</label>
  <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
</div>
<div>
  <label>Email</label>
  <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
</div>
<div>
  <label>Phone</label>
  <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
</div>
<div>
  <label>Address Line 1</label>
  <input type="text" name="address_line1" value="<?php echo htmlspecialchars($user['address_line1'] ?? ''); ?>">
</div>
<div>
  <label>Address Line 2</label>
  <input type="text" name="address_line2" value="<?php echo htmlspecialchars($user['address_line2'] ?? ''); ?>">
</div>
<div>
  <label>City</label>
  <input type="text" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
</div>
<div>
  <label>State</label>
  <input type="text" name="state" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>">
</div>
<div>
  <label>ZIP</label>
  <input type="text" name="zip" value="<?php echo htmlspecialchars($user['zip'] ?? ''); ?>">
</div>
<div>
  <label>Country</label>
  <input type="text" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>">
</div>
<h3>Billing Information</h3>
<div>
  <label>Billing Name</label>
  <input type="text" name="billing_name" value="<?php echo htmlspecialchars($user['billing_name'] ?? ''); ?>">
</div>
<div>
  <label>Billing Address Line 1</label>
  <input type="text" name="billing_address1" value="<?php echo htmlspecialchars($user['billing_address1'] ?? ''); ?>">
</div>
<div>
  <label>Billing Address Line 2</label>
  <input type="text" name="billing_address2" value="<?php echo htmlspecialchars($user['billing_address2'] ?? ''); ?>">
</div>
<div>
  <label>Billing City</label>
  <input type="text" name="billing_city" value="<?php echo htmlspecialchars($user['billing_city'] ?? ''); ?>">
</div>
<div>
  <label>Billing State</label>
  <input type="text" name="billing_state" value="<?php echo htmlspecialchars($user['billing_state'] ?? ''); ?>">
</div>
<div>
  <label>Billing ZIP</label>
  <input type="text" name="billing_zip" value="<?php echo htmlspecialchars($user['billing_zip'] ?? ''); ?>">
</div>
<div>
  <label>Billing Country</label>
  <input type="text" name="billing_country" value="<?php echo htmlspecialchars($user['billing_country'] ?? ''); ?>">
</div>
<div>
  <button type="submit">Save Profile</button>
</div>
</form>
</body>
</html>

<?php
?><?php
session_start();
$dbHost = 'localhost';
$dbName = 'ecommerce';
$dbUser = 'dbuser';
$dbPass = 'dbpass';
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
} catch (PDOException $e) {
  http_response_code(500);
  echo 'Database connection error';
  exit;
}
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf'] ?? '')) {
  http_response_code(403);
  exit;
}
$userId = $_SESSION['user_id'];
$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$addr1 = isset($_POST['address_line1']) ? trim($_POST['address_line1']) : '';
$addr2 = isset($_POST['address_line2']) ? trim($_POST['address_line2']) : '';
$city = isset($_POST['city']) ? trim($_POST['city']) : '';
$state = isset($_POST['state']) ? trim($_POST['state']) : '';
$zip = isset($_POST['zip']) ? trim($_POST['zip']) : '';
$country = isset($_POST['country']) ? trim($_POST['country']) : '';
$billing_name = isset($_POST['billing_name']) ? trim($_POST['billing_name']) : '';
$billing_addr1 = isset($_POST['billing_address1']) ? trim($_POST['billing_address1']) : '';
$billing_addr2 = isset($_POST['billing_address2']) ? trim($_POST['billing_address2']) : '';
$billing_city = isset($_POST['billing_city']) ? trim($_POST['billing_city']) : '';
$billing_state = isset($_POST['billing_state']) ? trim($_POST['billing_state']) : '';
$billing_zip = isset($_POST['billing_zip']) ? trim($_POST['billing_zip']) : '';
$billing_country = isset($_POST['billing_country']) ? trim($_POST['billing_country']) : '';
$errors = [];
if ($first_name === '') $errors[] = 'First name is required';
if ($last_name === '') $errors[] = 'Last name is required';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
if ($addr1 === '') $errors[] = 'Address line 1 is required';
if ($city === '') $errors[] = 'City is required';
if ($country === '') $errors[] = 'Country is required';
if ($billing_name === '') $errors[] = 'Billing name is required';
if ($billing_addr1 === '') $errors[] = 'Billing address line 1 is required';
if ($billing_city === '') $errors[] = 'Billing city is required';
if ($billing_zip === '') $errors[] = 'Billing ZIP is required';
if ($billing_country === '') $errors[] = 'Billing country is required';
if (!empty($errors)) {
  $_SESSION['profile_errors'] = $errors;
  header('Location: profile.php');
  exit;
}
$sql = "UPDATE users SET
  first_name = :first_name,
  last_name = :last_name,
  email = :email,
  phone = :phone,
  address_line1 = :addr1,
  address_line2 = :addr2,
  city = :city,
  state = :state,
  zip = :zip,
  country = :country,
  billing_name = :billing_name,
  billing_address1 = :billing_addr1,
  billing_address2 = :billing_addr2,
  billing_city = :billing_city,
  billing_state = :billing_state,
  billing_zip = :billing_zip,
  billing_country = :billing_country
WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':first_name' => $first_name,
  ':last_name' => $last_name,
  ':email' => $email,
  ':phone' => $phone,
  ':addr1' => $addr1,
  ':addr2' => $addr2,
  ':city' => $city,
  ':state' => $state,
  ':zip' => $zip,
  ':country' => $country,
  ':billing_name' => $billing_name,
  ':billing_addr1' => $billing_addr1,
  ':billing_addr2' => $billing_addr2,
  ':billing_city' => $billing_city,
  ':billing_state' => $billing_state,
  ':billing_zip' => $billing_zip,
  ':billing_country' => $billing_country,
  ':id' => $userId
]);
$_SESSION['profile_success'] = 'Profile updated successfully';
header('Location: profile.php');
exit;
?>