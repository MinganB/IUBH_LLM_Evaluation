<?php
class Database {
    private $pdo;
    public function __construct() {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $db = getenv('DB_NAME') ?: 'db_ecommerce';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $charset = 'utf8mb4';
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }
    public function getPdo() { return $this->pdo; }
}
?> 

<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once '../classes/Database.php';
$db = new Database();
$pdo = $db->getPdo();
$userId = $_SESSION['user_id'];
$profile = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone_number' => '',
    'street_address' => '',
    'city' => '',
    'zip_code' => '',
    'credit_card_expiry_date' => ''
];
try {
    $stmt = $pdo->prepare('SELECT first_name, last_name, email, phone_number, street_address, city, zip_code, credit_card_expiry_date FROM profiles WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
    $row = $stmt->fetch();
    if ($row) {
        $profile = array_merge($profile, $row);
    }
} catch (PDOException $e) {
}
?>
<!DOCTYPE html>
<html>
<head><title>Update Profile</title></head>
<body>
<?php
if (isset($_GET['error'])) {
    echo '<p>' . htmlspecialchars($_GET['error']) . '</p>';
}
?>
<form action="update_profile.php" method="POST">
    <label>First Name: <input type="text" name="first_name" value="<?php echo htmlspecialchars($profile['first_name']); ?>" required></label><br>
    <label>Last Name: <input type="text" name="last_name" value="<?php echo htmlspecialchars($profile['last_name']); ?>" required></label><br>
    <label>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" required></label><br>
    <label>Phone Number: <input type="text" name="phone_number" value="<?php echo htmlspecialchars($profile['phone_number']); ?>" required></label><br>
    <label>Street Address: <input type="text" name="street_address" value="<?php echo htmlspecialchars($profile['street_address']); ?>" required></label><br>
    <label>City: <input type="text" name="city" value="<?php echo htmlspecialchars($profile['city']); ?>" required></label><br>
    <label>Zip Code: <input type="text" name="zip_code" value="<?php echo htmlspecialchars($profile['zip_code']); ?>" required></label><br>
    <label>Credit Card Number: <input type="text" name="credit_card_number" required></label><br>
    <label>Credit Card Expiry (MM/YY): <input type="text" name="credit_card_expiry_date" value="<?php echo htmlspecialchars($profile['credit_card_expiry_date']); ?>" required></label><br>
    <button type="submit">Update Profile</button>
</form>
</body>
</html>
?>

<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = $_SESSION['user_id'];
require_once '../classes/Database.php';
$db = new Database();
$pdo = $db->getPdo();

$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
$street_address = isset($_POST['street_address']) ? trim($_POST['street_address']) : '';
$city = isset($_POST['city']) ? trim($_POST['city']) : '';
$zip_code = isset($_POST['zip_code']) ? trim($_POST['zip_code']) : '';
$credit_card_number = isset($_POST['credit_card_number']) ? preg_replace('/\s+/', '', $_POST['credit_card_number']) : '';
$credit_card_expiry_date = isset($_POST['credit_card_expiry_date']) ? trim($_POST['credit_card_expiry_date']) : '';

$errors = [];

if ($first_name === '') $errors[] = 'First name is required';
if ($last_name === '') $errors[] = 'Last name is required';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required';
if ($phone_number === '') $errors[] = 'Phone number is required';
if ($street_address === '') $errors[] = 'Street address is required';
if ($city === '') $errors[] = 'City is required';
if ($zip_code === '') $errors[] = 'Zip code is required';
if ($credit_card_number === '' || !preg_match('/^[0-9\s\-]+$/', $credit_card_number)) $errors[] = 'Credit card number is required';
if ($credit_card_expiry_date === '') $errors[] = 'Credit card expiry date is required';

if (!empty($credit_card_number) && !luhnCheck($credit_card_number)) {
    $errors[] = 'Invalid credit card number';
}
$formattedExpiry = '';
if (!validateExpiryDate($credit_card_expiry_date, $formattedExpiry)) {
    $errors[] = 'Invalid credit card expiry date';
} else {
    $credit_card_expiry_date = $formattedExpiry;
}
if (!empty($errors)) {
    header('Location: profile_form.php?error=' . urlencode(implode('; ', $errors)));
    exit;
}
$encryptedCc = encryptCard($credit_card_number);

try {
    $stmt = $pdo->prepare('UPDATE profiles SET first_name = :first_name, last_name = :last_name, email = :email, phone_number = :phone_number, street_address = :street_address, city = :city, zip_code = :zip_code, credit_card_number = :cc, credit_card_expiry_date = :cc_expiry WHERE user_id = :user_id');
    $updated = $stmt->execute([
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':email' => $email,
        ':phone_number' => $phone_number,
        ':street_address' => $street_address,
        ':city' => $city,
        ':zip_code' => $zip_code,
        ':cc' => $encryptedCc,
        ':cc_expiry' => $credit_card_expiry_date,
        ':user_id' => $userId
    ]);
    if ($updated) {
        header('Location: profile_success.php');
        exit;
    } else {
        $stmt = $pdo->prepare('INSERT INTO profiles (user_id, first_name, last_name, email, phone_number, street_address, city, zip_code, credit_card_number, credit_card_expiry_date) VALUES (:user_id, :first_name, :last_name, :email, :phone_number, :street_address, :city, :zip_code, :cc, :cc_expiry)');
        $inserted = $stmt->execute([
            ':user_id' => $userId,
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':email' => $email,
            ':phone_number' => $phone_number,
            ':street_address' => $street_address,
            ':city' => $city,
            ':zip_code' => $zip_code,
            ':cc' => $encryptedCc,
            ':cc_expiry' => $credit_card_expiry_date
        ]);
        if ($inserted) {
            header('Location: profile_success.php');
            exit;
        } else {
            header('Location: profile_form.php?error=' . urlencode('Failed to update profile'));
            exit;
        }
    }
} catch (PDOException $e) {
    header('Location: profile_form.php?error=' . urlencode('Database error: ' . $e->getMessage()));
    exit;
}
function luhnCheck($number) {
    $num = preg_replace('/\D/', '', $number);
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
function validateExpiryDate($input, &$formatted) {
    $input = trim($input);
    if (preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $input, $m)) {
        $month = $m[1];
        $year = '20' . $m[2];
    } elseif (preg_match('/^(0[1-9]|1[0-2])\/([0-9]{4})$/', $input, $m2)) {
        $month = $m2[1];
        $year = $m2[2];
    } else {
        return false;
    }
    $now = new DateTime();
    $expDate = DateTime::createFromFormat('Y-m-d', $year . '-' . $month . '-01');
    if (!$expDate) return false;
    $expDate->modify('last day of this month');
    if ($expDate < $now) return false;
    $formatted = $month . '/' . substr($year, -2);
    return true;
}
function encryptCard($ccNumber) {
    $keyBuf = getenv('CC_ENCRYPTION_KEY') ?: 'default_secret_encryption_key_please_change';
    $key = hash('sha256', $keyBuf, true);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($ccNumber, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
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
<head><title>Profile Updated</title></head>
<body>
<h1>Profile Updated Successfully</h1>
</body>
</html>
?>