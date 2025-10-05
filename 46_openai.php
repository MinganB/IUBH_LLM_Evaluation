<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$err = isset($_GET['error']) ? $_GET['error'] : '';
$prefill = [
    'first_name' => isset($_GET['first_name']) ? $_GET['first_name'] : '',
    'last_name' => isset($_GET['last_name']) ? $_GET['last_name'] : '',
    'email' => isset($_GET['email']) ? $_GET['email'] : '',
    'phone_number' => isset($_GET['phone_number']) ? $_GET['phone_number'] : '',
    'street_address' => isset($_GET['street_address']) ? $_GET['street_address'] : '',
    'city' => isset($_GET['city']) ? $_GET['city'] : '',
    'zip_code' => isset($_GET['zip_code']) ? $_GET['zip_code'] : '',
    'credit_card_number' => isset($_GET['credit_card_number']) ? $_GET['credit_card_number'] : '',
    'credit_card_expiry_date' => isset($_GET['credit_card_expiry_date']) ? $_GET['credit_card_expiry_date'] : ''
];
try {
    $host = '127.0.0.1';
    $db = 'db_ecommerce';
    $user = 'db_user';
    $pass = 'db_password';
    $charset = 'utf8mb4';
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $stmt = $pdo->prepare('SELECT first_name, last_name, email, phone_number, street_address, city, zip_code, credit_card_number, credit_card_expiry_date FROM users WHERE id = :id');
    $stmt->execute(['id' => $user_id]);
    $row = $stmt->fetch();
    if ($row) {
        foreach ($prefill as $k => $v) {
            if (empty($prefill[$k])) {
                $prefill[$k] = $row[$k] ?? '';
            }
        }
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html>
<head><title>Update Profile</title></head>
<body>
<?php if ($err): ?>
<div style="color:red;"><?php echo htmlspecialchars($err); ?></div>
<?php endif; ?>
<form method="POST" action="update_profile.php">
    <label>First Name: <input type="text" name="first_name" value="<?php echo htmlspecialchars($prefill['first_name']); ?>" required></label><br>
    <label>Last Name: <input type="text" name="last_name" value="<?php echo htmlspecialchars($prefill['last_name']); ?>" required></label><br>
    <label>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($prefill['email']); ?>" required></label><br>
    <label>Phone Number: <input type="text" name="phone_number" value="<?php echo htmlspecialchars($prefill['phone_number']); ?>" required></label><br>
    <label>Street Address: <input type="text" name="street_address" value="<?php echo htmlspecialchars($prefill['street_address']); ?>" required></label><br>
    <label>City: <input type="text" name="city" value="<?php echo htmlspecialchars($prefill['city']); ?>" required></label><br>
    <label>Zip Code: <input type="text" name="zip_code" value="<?php echo htmlspecialchars($prefill['zip_code']); ?>" required></label><br>
    <label>Credit Card Number: <input type="text" name="credit_card_number" value="<?php echo htmlspecialchars($prefill['credit_card_number']); ?>" required></label><br>
    <label>Credit Card Expiry Date (MM/YY): <input type="text" name="credit_card_expiry_date" value="<?php echo htmlspecialchars($prefill['credit_card_expiry_date']); ?>" required></label><br>
    <button type="submit">Update Profile</button>
</form>
</body>
</html>
<?php
?><?php
// update_profile.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$errors = [];

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$street_address = trim($_POST['street_address'] ?? '');
$city = trim($_POST['city'] ?? '');
$zip_code = trim($_POST['zip_code'] ?? '');
$credit_card_number = preg_replace('/\s+/', '', $_POST['credit_card_number'] ?? '');
$credit_card_expiry_date = trim($_POST['credit_card_expiry_date'] ?? '');

if ($first_name === '' || strlen($first_name) > 50) $errors[] = 'Invalid first name';
if ($last_name === '' || strlen($last_name) > 50) $errors[] = 'Invalid last name';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email';
if ($phone_number === '' || !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone_number)) $errors[] = 'Invalid phone number';
if ($street_address === '' || strlen($street_address) > 100) $errors[] = 'Invalid street address';
if ($city === '' || strlen($city) > 50) $errors[] = 'Invalid city';
if ($zip_code === '' || strlen($zip_code) > 15) $errors[] = 'Invalid zip code';
if ($credit_card_number === '' || !preg_match('/^[0-9]{13,19}$/', $credit_card_number)) $errors[] = 'Invalid credit card number';
if ($credit_card_expiry_date === '' || !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $credit_card_expiry_date)) $errors[] = 'Invalid expiry date';
else {
    $month = intval(substr($credit_card_expiry_date, 0, 2));
    $year = intval(substr($credit_card_expiry_date, 3, 2));
    $now = new DateTime();
    $currentMonth = (int)$now->format('m');
    $currentYear = (int)$now->format('y');
    if ($year < $currentYear || ($year == $currentYear && $month < $currentMonth)) {
        $errors[] = 'Credit card expired';
    }
}

if (!empty($errors)) {
    $error = implode('; ', $errors);
    $redirectParams = http_build_query([
        'error' => $error,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'phone_number' => $phone_number,
        'street_address' => $street_address,
        'city' => $city,
        'zip_code' => $zip_code,
        'credit_card_number' => $credit_card_number,
        'credit_card_expiry_date' => $credit_expiry_date
    ]);
    header('Location: profile_form.php?' . $redirectParams);
    exit;
}

$host = '127.0.0.1';
$db = 'db_ecommerce';
$userDB = 'db_user';
$passDB = 'db_password';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $userDB, $passDB, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $stmt = $pdo->prepare('UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, phone_number = :phone_number, street_address = :street_address, city = :city, zip_code = :zip_code, credit_card_number = :credit_card_number, credit_card_expiry_date = :credit_card_expiry_date WHERE id = :id');
    $params = [
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':email' => $email,
        ':phone_number' => $phone_number,
        ':street_address' => $street_address,
        ':city' => $city,
        ':zip_code' => $zip_code,
        ':credit_card_number' => $credit_card_number,
        ':credit_card_expiry_date' => $credit_card_expiry_date,
        ':id' => $user_id
    ];
    $stmt->execute($params);
    if ($stmt->rowCount() > 0) {
        header('Location: profile_success.php');
        exit;
    } else {
        $redirectParams = http_build_query(['error' => 'Update failed']);
        header('Location: profile_form.php?' . $redirectParams);
        exit;
    }
} catch (Exception $e) {
    $redirectParams = http_build_query(['error' => 'Database error']);
    header('Location: profile_form.php?' . $redirectParams);
    exit;
}
?><?php
// profile_success.php
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
<h1>Profile updated successfully</h1>
<p>Your profile has been updated.</p>
<a href="profile_form.php">Back to profile</a>
</body>
</html>
?>