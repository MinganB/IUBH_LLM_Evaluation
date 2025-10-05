<?php
session_start();

$errors = [];
$success = '';
$values = [
    'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '',
    'address_line1' => '', 'address_line2' => '', 'city' => '', 'state' => '', 'postal_code' => '', 'country' => '',
    'billing_address1' => '', 'billing_address2' => '', 'billing_city' => '', 'billing_state' => '', 'billing_postal_code' => '', 'billing_country' => '', 'billing_phone' => '',
    'preferred_payment_method' => 'invoice'
];

function getPDO() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $db   = getenv('DB_NAME') ?: 'mydb';
    $user = getenv('DB_USER') ?: 'dbuser';
    $pass = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    return $pdo;
}

$pdo = null;
try {
    $pdo = getPDO();
} catch (Exception $e) {
    http_response_code(500);
    echo 'Database connection error';
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo 'Please log in to edit your profile.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid CSRF token';
    }

    $values['first_name'] = trim($_POST['first_name'] ?? '');
    $values['last_name'] = trim($_POST['last_name'] ?? '');
    $values['email'] = trim($_POST['email'] ?? '');
    $values['phone'] = trim($_POST['phone'] ?? '');
    $values['address_line1'] = trim($_POST['address_line1'] ?? '');
    $values['address_line2'] = trim($_POST['address_line2'] ?? '');
    $values['city'] = trim($_POST['city'] ?? '');
    $values['state'] = trim($_POST['state'] ?? '');
    $values['postal_code'] = trim($_POST['postal_code'] ?? '');
    $values['country'] = trim($_POST['country'] ?? '');
    $values['billing_address1'] = trim($_POST['billing_address1'] ?? '');
    $values['billing_address2'] = trim($_POST['billing_address2'] ?? '');
    $values['billing_city'] = trim($_POST['billing_city'] ?? '');
    $values['billing_state'] = trim($_POST['billing_state'] ?? '');
    $values['billing_postal_code'] = trim($_POST['billing_postal_code'] ?? '');
    $values['billing_country'] = trim($_POST['billing_country'] ?? '');
    $values['billing_phone'] = trim($_POST['billing_phone'] ?? '');
    $values['preferred_payment_method'] = trim($_POST['preferred_payment_method'] ?? 'invoice');

    if ($values['first_name'] === '' || mb_strlen($values['first_name']) > 50) $errors[] = 'First name is required and must be at most 50 characters';
    if ($values['last_name'] === '' || mb_strlen($values['last_name']) > 50) $errors[] = 'Last name is required and must be at most 50 characters';
    if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required';
    if ($values['address_line1'] === '') $errors[] = 'Address line 1 is required';
    if ($values['city'] === '') $errors[] = 'City is required';
    if ($values['postal_code'] === '') $errors[] = 'Postal code is required';
    if ($values['country'] === '') $errors[] = 'Country is required';
    if ($values['billing_address1'] === '') $errors[] = 'Billing address line 1 is required';
    if ($values['billing_city'] === '') $errors[] = 'Billing city is required';
    if ($values['billing_postal_code'] === '') $errors[] = 'Billing postal code is required';
    if ($values['billing_country'] === '') $errors[] = 'Billing country is required';
    if (!in_array($values['preferred_payment_method'], ['invoice','card','paypal'], true)) $errors[] = 'Invalid payment method';

    if (empty($errors)) {
        $sql = "UPDATE users SET
            first_name = :first_name,
            last_name = :last_name,
            email = :email,
            phone = :phone,
            address_line1 = :address_line1,
            address_line2 = :address_line2,
            city = :city,
            state = :state,
            postal_code = :postal_code,
            country = :country,
            billing_address1 = :billing_address1,
            billing_address2 = :billing_address2,
            billing_city = :billing_city,
            billing_state = :billing_state,
            billing_postal_code = :billing_postal_code,
            billing_country = :billing_country,
            billing_phone = :billing_phone,
            preferred_payment_method = :preferred_payment_method
            WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':first_name' => $values['first_name'],
            ':last_name' => $values['last_name'],
            ':email' => $values['email'],
            ':phone' => $values['phone'],
            ':address_line1' => $values['address_line1'],
            ':address_line2' => $values['address_line2'],
            ':city' => $values['city'],
            ':state' => $values['state'],
            ':postal_code' => $values['postal_code'],
            ':country' => $values['country'],
            ':billing_address1' => $values['billing_address1'],
            ':billing_address2' => $values['billing_address2'],
            ':billing_city' => $values['billing_city'],
            ':billing_state' => $values['billing_state'],
            ':billing_postal_code' => $values['billing_postal_code'],
            ':billing_country' => $values['billing_country'],
            ':billing_phone' => $values['billing_phone'],
            ':preferred_payment_method' => $values['preferred_payment_method'],
            ':id' => $user_id
        ]);
        if ($stmt->rowCount() > 0) {
            $success = 'Profile updated successfully';
        } else {
            $success = 'No changes were made';
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT
        first_name, last_name, email, phone, address_line1, address_line2, city, state, postal_code, country,
        billing_address1, billing_address2, billing_city, billing_state, billing_postal_code, billing_country, billing_phone,
        preferred_payment_method
        FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $row = $stmt->fetch();
    if ($row) {
        foreach ($row as $k => $v) {
            $k = (string)$k;
            $values[$k] = $v;
        }
    } else {
        echo 'User not found';
        exit;
    }
} catch (Exception $e) {
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Update Profile</title>
</head>
<body>
<?php if (!empty($errors)): ?>
<div>
<?php foreach ($errors as $err): ?>
<p><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php if (isset($success)): ?>
<div><p><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p></div>
<?php endif; ?>
<form method="post" action="update_profile.php">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
<h2>Personal Information</h2>
<label>First Name: <input type="text" name="first_name" value="<?php echo htmlspecialchars($values['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>
<label>Last Name: <input type="text" name="last_name" value="<?php echo htmlspecialchars($values['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>
<label>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($values['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>
<label>Phone: <input type="text" name="phone" value="<?php echo htmlspecialchars($values['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>

<h2>Address</h2>
<label>Address Line 1: <input type="text" name="address_line1" value="<?php echo htmlspecialchars($values['address_line1'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>
<label>Address Line 2: <input type="text" name="address_line2" value="<?php echo htmlspecialchars($values['address_line2'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>
<label>City: <input type="text" name="city" value="<?php echo htmlspecialchars($values['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>
<label>State: <input type="text" name="state" value="<?php echo htmlspecialchars($values['state'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>
<label>Postal Code: <input type="text" name="postal_code" value="<?php echo htmlspecialchars($values['postal_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>
<label>Country: <input type="text" name="country" value="<?php echo htmlspecialchars($values['country'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>

<h2>Billing Information</h2>
<label>Billing Address Line 1: <input type="text" name="billing_address1" value="<?php echo htmlspecialchars($values['billing_address1'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>
<label>Billing Address Line 2: <input type="text" name="billing_address2" value="<?php echo htmlspecialchars($values['billing_address2'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>
<label>Billing City: <input type="text" name="billing_city" value="<?php echo htmlspecialchars($values['billing_city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>
<label>Billing State: <input type="text" name="billing_state" value="<?php echo htmlspecialchars($values['billing_state'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>
<label>Billing Postal Code: <input type="text" name="billing_postal_code" value="<?php echo htmlspecialchars($values['billing_postal_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>
<label>Billing Country: <input type="text" name="billing_country" value="<?php echo htmlspecialchars($values['billing_country'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>
<label>Billing Phone: <input type="text" name="billing_phone" value="<?php echo htmlspecialchars($values['billing_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><br>

<label>Preferred Payment Method:
<select name="preferred_payment_method">
<option value="invoice" <?php if (($values['preferred_payment_method'] ?? '') === 'invoice') echo 'selected'; ?>>Invoice</option>
<option value="card" <?php if (($values['preferred_payment_method'] ?? '') === 'card') echo 'selected'; ?>>Card</option>
<option value="paypal" <?php if (($values['preferred_payment_method'] ?? '') === 'paypal') echo 'selected'; ?>>PayPal</option>
</select>
</label><br>

<button type="submit">Update Profile</button>
</form>
</body>
</html>
?>