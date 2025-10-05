<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function getPdo() {
    $host = 'localhost';
    $db   = 'db_ecommerce';
    $user = getenv('DB_USERNAME') ?: 'root';
    $pass = getenv('DB_PASSWORD') ?: '';
    $dsn  = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
}

$pdo = null;
$user = [];
try {
    $pdo = getPdo();
    $stmt = $pdo->prepare('SELECT first_name, last_name, email, phone_number, street_address, city, zip_code, credit_card_expiry_date FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch() ?? [];
} catch (Exception $e) {
    $user = [];
}

$first_name = htmlspecialchars($user['first_name'] ?? '');
$last_name = htmlspecialchars($user['last_name'] ?? '');
$email = htmlspecialchars($user['email'] ?? '');
$phone = htmlspecialchars($user['phone_number'] ?? '');
$street = htmlspecialchars($user['street_address'] ?? '');
$city = htmlspecialchars($user['city'] ?? '');
$zip = htmlspecialchars($user['zip_code'] ?? '');
$expiry = htmlspecialchars($user['credit_card_expiry_date'] ?? '');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Profile</title>
</head>
<body>
<?php
if (isset($_GET['error'])) {
    echo '<p style="color:red;">' . htmlspecialchars($_GET['error']) . '</p>';
}
?>
<form action="update_profile.php" method="post">
    <label>First Name: <input type="text" name="first_name" value="<?php echo $first_name; ?>" required></label><br>
    <label>Last Name: <input type="text" name="last_name" value="<?php echo $last_name; ?>" required></label><br>
    <label>Email: <input type="email" name="email" value="<?php echo $email; ?>" required></label><br>
    <label>Phone Number: <input type="text" name="phone_number" value="<?php echo $phone; ?>" required></label><br>
    <label>Street Address: <input type="text" name="street_address" value="<?php echo $street; ?>" required></label><br>
    <label>City: <input type="text" name="city" value="<?php echo $city; ?>" required></label><br>
    <label>Zip Code: <input type="text" name="zip_code" value="<?php echo $zip; ?>" required></label><br>
    <label>Credit Card Number: <input type="text" name="credit_card_number" required></label><br>
    <label>Credit Card Expiry Date (MM/YY): <input type="text" name="credit_card_expiry_date" value="<?php echo $expiry; ?>" placeholder="MM/YY" required></label><br>
    <button type="submit">Update Profile</button>
</form>
</body>
</html><?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function getPdo() {
    $host = 'localhost';
    $db   = 'db_ecommerce';
    $user = getenv('DB_USERNAME') ?: 'root';
    $pass = getenv('DB_PASSWORD') ?: '';
    $dsn  = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
}

function luhnCheck($number) {
    $number = preg_replace('/\D/', '', $number);
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

function encryptCardNumber($cc, $key) {
    $ivLen = openssl_cipher_iv_length('AES-256-CBC');
    $iv = openssl_random_pseudo_bytes($ivLen);
    $enc = openssl_encrypt($cc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}

$pdo = null;
$user = [];
try {
    $pdo = getPdo();
    $stmt = $pdo->prepare('SELECT first_name, last_name, email, phone_number, street_address, city, zip_code, credit_card_expiry_date FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch() ?? [];
} catch (Exception $e) {
    $user = [];
}

$first_name = htmlspecialchars($user['first_name'] ?? '');
$last_name = htmlspecialchars($user['last_name'] ?? '');
$email = htmlspecialchars($user['email'] ?? '');
$phone = htmlspecialchars($user['phone_number'] ?? '');
$street = htmlspecialchars($user['street_address'] ?? '');
$city = htmlspecialchars($user['city'] ?? '');
$zip = htmlspecialchars($user['zip_code'] ?? '');
$expiry = htmlspecialchars($user['credit_card_expiry_date'] ?? '');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Profile</title>
</head>
<body>
<?php
if (isset($_GET['error'])) {
    echo '<p style="color:red;">' . htmlspecialchars($_GET['error']) . '</p>';
}
?>
<form action="update_profile.php" method="post">
    <label>First Name: <input type="text" name="first_name" value="<?php echo $first_name; ?>" required></label><br>
    <label>Last Name: <input type="text" name="last_name" value="<?php echo $last_name; ?>" required></label><br>
    <label>Email: <input type="email" name="email" value="<?php echo $email; ?>" required></label><br>
    <label>Phone Number: <input type="text" name="phone_number" value="<?php echo $phone; ?>" required></label><br>
    <label>Street Address: <input type="text" name="street_address" value="<?php echo $street; ?>" required></label><br>
    <label>City: <input type="text" name="city" value="<?php echo $city; ?>" required></label><br>
    <label>Zip Code: <input type="text" name="zip_code" value="<?php echo $zip; ?>" required></label><br>
    <label>Credit Card Number: <input type="text" name="credit_card_number" required></label><br>
    <label>Credit Card Expiry Date (MM/YY): <input type="text" name="credit_card_expiry_date" value="<?php echo $expiry; ?>" placeholder="MM/YY" required></label><br>
    <button type="submit">Update Profile</button>
</form>
</body>
</html><?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function getPdo() {
    $host = 'localhost';
    $db   = 'db_ecommerce';
    $user = getenv('DB_USERNAME') ?: 'root';
    $pass = getenv('DB_PASSWORD') ?: '';
    $dsn  = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
}

function encryptCardNumber($cc, $key) {
    $ivLen = openssl_cipher_iv_length('AES-256-CBC');
    $iv = openssl_random_pseudo_bytes($ivLen);
    $enc = openssl_encrypt($cc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}

$pdo = null;
$first_name = '';
$last_name = '';
$email = '';
$phone_number = '';
$street_address = '';
$city = '';
$zip_code = '';
$credit_expiry = '';

try {
    $pdo = getPdo();
    $stmt = $pdo->prepare('SELECT first_name, last_name, email, phone_number, street_address, city, zip_code, credit_card_expiry_date FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $row = $stmt->fetch();
    if ($row) {
        $first_name = htmlspecialchars($row['first_name'] ?? '');
        $last_name = htmlspecialchars($row['last_name'] ?? '');
        $email = htmlspecialchars($row['email'] ?? '');
        $phone_number = htmlspecialchars($row['phone_number'] ?? '');
        $street_address = htmlspecialchars($row['street_address'] ?? '');
        $city = htmlspecialchars($row['city'] ?? '');
        $zip_code = htmlspecialchars($row['zip_code'] ?? '');
        $credit_expiry = htmlspecialchars($row['credit_card_expiry_date'] ?? '');
    }
} catch (Exception $e) {
    // ignore, fallback to empty form
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Profile</title>
</head>
<body>
<?php
if (isset($_GET['error'])) {
    echo '<p style="color:red;">' . htmlspecialchars($_GET['error']) . '</p>';
}
?>
<form action="update_profile.php" method="post">
    <label>First Name: <input type="text" name="first_name" value="<?php echo $first_name; ?>" required></label><br>
    <label>Last Name: <input type="text" name="last_name" value="<?php echo $last_name; ?>" required></label><br>
    <label>Email: <input type="email" name="email" value="<?php echo $email; ?>" required></label><br>
    <label>Phone Number: <input type="text" name="phone_number" value="<?php echo $phone_number; ?>" required></label><br>
    <label>Street Address: <input type="text" name="street_address" value="<?php echo $street_address; ?>" required></label><br>
    <label>City: <input type="text" name="city" value="<?php echo $city; ?>" required></label><br>
    <label>Zip Code: <input type="text" name="zip_code" value="<?php echo $zip_code; ?>" required></label><br>
    <label>Credit Card Number: <input type="text" name="credit_card_number" required></label><br>
    <label>Credit Card Expiry Date (MM/YY): <input type="text" name="credit_card_expiry_date" value="<?php echo $credit_expiry; ?>" placeholder="MM/YY" required></label><br>
    <button type="submit">Update Profile</button>
</form>
</body>
</html><?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function getPdo() {
    $host = 'localhost';
    $db   = 'db_ecommerce';
    $user = getenv('DB_USERNAME') ?: 'root';
    $pass = getenv('DB_PASSWORD') ?: '';
    $dsn  = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
}

function luhnCheck($number) {
    $number = preg_replace('/\D/', '', $number);
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

function encryptCardNumber($cc, $key) {
    $ivLen = openssl_cipher_iv_length('AES-256-CBC');
    $iv = openssl_random_pseudo_bytes($ivLen);
    $enc = openssl_encrypt($cc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}

$pdo = null;
$first_name = '';
$last_name = '';
$email = '';
$phone_number = '';
$street_address = '';
$city = '';
$zip_code = '';
$credit_expiry = '';

try {
    $pdo = getPdo();
    $stmt = $pdo->prepare('SELECT first_name, last_name, email, phone_number, street_address, city, zip_code, credit_card_expiry_date FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $row = $stmt->fetch();
    if ($row) {
        $first_name = htmlspecialchars($row['first_name'] ?? '');
        $last_name = htmlspecialchars($row['last_name'] ?? '');
        $email = htmlspecialchars($row['email'] ?? '');
        $phone_number = htmlspecialchars($row['phone_number'] ?? '');
        $street_address = htmlspecialchars($row['street_address'] ?? '');
        $city = htmlspecialchars($row['city'] ?? '');
        $zip_code = htmlspecialchars($row['zip_code'] ?? '');
        $credit_expiry = htmlspecialchars($row['credit_card_expiry_date'] ?? '');
    }
} catch (Exception $e) {
    // ignore
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Profile</title>
</head>
<body>
<?php
if (isset($_GET['error'])) {
    echo '<p style="color:red;">' . htmlspecialchars($_GET['error']) . '</p>';
}
?>
<form action="update_profile.php" method="post">
    <label>First Name: <input type="text" name="first_name" value="<?php echo $first_name; ?>" required></label><br>
    <label>Last Name: <input type="text" name="last_name" value="<?php echo $last_name; ?>" required></label><br>
    <label>Email: <input type="email" name="email" value="<?php echo $email; ?>" required></label><br>
    <label>Phone Number: <input type="text" name="phone_number" value="<?php echo $phone_number; ?>" required></label><br>
    <label>Street Address: <input type="text" name="street_address" value="<?php echo $street_address; ?>" required></label><br>
    <label>City: <input type="text" name="city" value="<?php echo $city; ?>" required></label><br>
    <label>Zip Code: <input type="text" name="zip_code" value="<?php echo $zip_code; ?>" required></label><br>
    <label>Credit Card Number: <input type="text" name="credit_card_number" required></label><br>
    <label>Credit Card Expiry Date (MM/YY): <input type="text" name="credit_card_expiry_date" value="<?php echo $credit_expiry; ?>" placeholder="MM/YY" required></label><br>
    <button type="submit">Update Profile</button>
</form>
</body>
</html>
?>