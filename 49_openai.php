<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$dbHost = 'localhost';
$dbName = 'db_ecommerce';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone_number, street_address, city, zip_code, credit_card_number, credit_card_expiry_date FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    header('Location: profile_form.php');
    exit;
}
$firstName = $user['first_name'] ?? '';
$lastName = $user['last_name'] ?? '';
$email = $user['email'] ?? '';
$phone = $user['phone_number'] ?? '';
$street = $user['street_address'] ?? '';
$city = $user['city'] ?? '';
$zip = $user['zip_code'] ?? '';
$ccNum = $user['credit_card_number'] ?? '';
$ccExp = $user['credit_card_expiry_date'] ?? '';
?>
<!DOCTYPE html>
<html>
<head><title>Profile</title></head>
<body>
<?php
if (isset($_SESSION['error'])) {
    echo '<div style="color:red;">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}
?>
<form method="POST" action="update_profile.php">
  <label>First Name: <input type="text" name="first_name" value="<?php echo htmlspecialchars($firstName); ?>"></label><br>
  <label>Last Name: <input type="text" name="last_name" value="<?php echo htmlspecialchars($lastName); ?>"></label><br>
  <label>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>"></label><br>
  <label>Phone Number: <input type="text" name="phone_number" value="<?php echo htmlspecialchars($phone); ?>"></label><br>
  <label>Street Address: <input type="text" name="street_address" value="<?php echo htmlspecialchars($street); ?>"></label><br>
  <label>City: <input type="text" name="city" value="<?php echo htmlspecialchars($city); ?>"></label><br>
  <label>ZIP Code: <input type="text" name="zip_code" value="<?php echo htmlspecialchars($zip); ?>"></label><br>
  <label>Credit Card Number: <input type="text" name="credit_card_number" value="<?php echo htmlspecialchars($ccNum); ?>"></label><br>
  <label>Credit Card Expiry (MM/YY): <input type="text" name="credit_card_expiry_date" value="<?php echo htmlspecialchars($ccExp); ?>"></label><br>
  <button type="submit">Update Profile</button>
</form>
</body>
</html>

<?php
// end of profile_form.php
?>

<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$errors = [];
$fields = ['first_name','last_name','email','phone_number','street_address','city','zip_code','credit_card_number','credit_card_expiry_date'];
$data = [];
foreach ($fields as $f) {
    $data[$f] = isset($_POST[$f]) ? trim($_POST[$f]) : '';
}
if ($data['first_name'] === '') $errors[] = 'First name is required.';
if ($data['last_name'] === '') $errors[] = 'Last name is required.';
if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
if ($data['phone_number'] === '') $errors[] = 'Phone number is required.';
if ($data['street_address'] === '') $errors[] = 'Street address is required.';
if ($data['city'] === '') $errors[] = 'City is required.';
if ($data['zip_code'] === '' || !preg_match('/^\d{5}(-\d{4})?$/', $data['zip_code'])) $errors[] = 'ZIP code is invalid.';
$ccRaw = preg_replace('/[\s-]/','',$data['credit_card_number']);
if ($ccRaw === '' || !ctype_digit($ccRaw)) $errors[] = 'Credit card number is invalid.';

function luhnCheck($number) {
    $sum = 0;
    $alt = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = (int)$number[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) $n -= 9;
        }
        $sum += $n;
        $alt = !$alt;
    }
    return ($sum % 10) == 0;
}
if ($ccRaw !== '' && !luhnCheck($ccRaw)) $errors[] = 'Credit card number failed validation.';

if ($data['credit_card_expiry_date'] === '' || !preg_match('/^(0[1-9]|1[0-2])\/(\d{2}|\d{4})$/', $data['credit_card_expiry_date'])) {
    $errors[] = 'Credit card expiry date must be MM/YY or MM/YYYY.';
} else {
    $parts = explode('/', $data['credit_card_expiry_date']);
    $expMonth = (int)$parts[0];
    $expYearRaw = $parts[1];
    $expYear = strlen($expYearRaw) == 2 ? (2000 + (int)$expYearRaw) : (int)$expYearRaw;
    $expiryDate = new DateTime();
    $expiryDate->setDate($expYear, $expMonth, 1);
    $expiryDate->modify('last day of this month');
    $now = new DateTime();
    $now->setTime(0,0,0);
    if ($expiryDate < $now) $errors[] = 'Credit card expiry date must be in the future.';
}
if (!empty($errors)) {
    $_SESSION['error'] = implode(' ', $errors);
    header('Location: profile_form.php');
    exit;
}
$dbHost = 'localhost';
$dbName = 'db_ecommerce';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $ccStored = $ccRaw;
    $update = "UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, phone_number = :phone_number, street_address = :street_address, city = :city, zip_code = :zip_code, credit_card_number = :credit_card_number, credit_card_expiry_date = :credit_card_expiry_date WHERE id = :id";
    $stmt = $pdo->prepare($update);
    $params = [
        ':first_name' => $data['first_name'],
        ':last_name' => $data['last_name'],
        ':email' => $data['email'],
        ':phone_number' => $data['phone_number'],
        ':street_address' => $data['street_address'],
        ':city' => $data['city'],
        ':zip_code' => $data['zip_code'],
        ':credit_card_number' => $ccStored,
        ':credit_card_expiry_date' => $data['credit_card_expiry_date'],
        ':id' => $_SESSION['user_id']
    ];
    $stmt->execute($params);
    if ($stmt->rowCount() > 0) {
        header('Location: profile_success.php');
        exit;
    } else {
        $_SESSION['error'] = 'Profile update failed. Please ensure you are logged in.';
        header('Location: profile_form.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    header('Location: profile_form.php');
    exit;
}
?>

<?php
// end of update_profile.php
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
<head><title>Profile Updated</title></head>
<body>
<p>Your profile has been updated successfully.</p>
<a href="profile_form.php">Back to profile</a>
</body>
</html>
?>