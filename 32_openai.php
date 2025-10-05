<?php
session_start();

function dbConnect() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'ecommerce';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    return new PDO($dsn, $user, $pass, $options);
}

function fetchUser($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

function redirectToLogin() {
    header("Location: login.php");
    exit;
}

$pdo = null;
try {
    $pdo = dbConnect();
} catch (Exception $e) {
    http_response_code(500);
    echo 'Database connection error';
    exit;
}

if (!isset($_SESSION['user_id'])) {
    redirectToLogin();
}
$profile = fetchUser($pdo, $_SESSION['user_id']);
if (!$profile) {
    redirectToLogin();
}

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$updated = isset($_GET['updated']);
$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $errors[] = 'Invalid CSRF token';
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
        $phone = trim($_POST['phone'] ?? '');
        $address1 = trim($_POST['address1'] ?? '');
        $address2 = trim($_POST['address2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip = trim($_POST['zip'] ?? '');
        $country = trim($_POST['country'] ?? '');

        $billingName = trim($_POST['billing_name'] ?? '');
        $billingAddress1 = trim($_POST['billing_address1'] ?? '');
        $billingAddress2 = trim($_POST['billing_address2'] ?? '');
        $billingCity = trim($_POST['billing_city'] ?? '');
        $billingState = trim($_POST['billing_state'] ?? '');
        $billingZip = trim($_POST['billing_zip'] ?? '');
        $billingCountry = trim($_POST['billing_country'] ?? '');

        $cardNumberRaw = $_POST['card_number'] ?? '';
        $cardNumber = preg_replace('/\D/', '', $cardNumberRaw);
        $cardExpiry = trim($_POST['card_expiry'] ?? '');

        $existing = fetchUser($pdo, $_SESSION['user_id']);
        $cardLast4 = $existing['card_last4'] ?? '';
        if (!empty($cardNumber) && strlen($cardNumber) >= 4) {
            $cardLast4 = substr($cardNumber, -4);
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $passwordHash = null;
        if ($currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '') {
            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                $errors[] = 'To change password, fill current, new and confirm.';
            } else {
                if (!password_verify($currentPassword, $existing['password_hash'] ?? '')) {
                    $errors[] = 'Current password is incorrect.';
                } elseif ($newPassword !== $confirmPassword) {
                    $errors[] = 'New password and confirmation do not match.';
                } elseif (strlen($newPassword) < 8) {
                    $errors[] = 'New password must be at least 8 characters.';
                } else {
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                }
            }
        }

        if (empty($errors)) {
            $updateSql = "UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone, address1 = :address1, address2 = :address2, city = :city, state = :state, zip = :zip, country = :country, billing_name = :billing_name, billing_address1 = :billing_address1, billing_address2 = :billing_address2, billing_city = :billing_city, billing_state = :billing_state, billing_zip = :billing_zip, billing_country = :billing_country, card_last4 = :card_last4, card_expiry = :card_expiry, updated_at = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute([
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':email' => $email,
                ':phone' => $phone,
                ':address1' => $address1,
                ':address2' => $address2,
                ':city' => $city,
                ':state' => $state,
                ':zip' => $zip,
                ':country' => $country,
                ':billing_name' => $billingName,
                ':billing_address1' => $billingAddress1,
                ':billing_address2' => $billingAddress2,
                ':billing_city' => $billingCity,
                ':billing_state' => $billingState,
                ':billing_zip' => $billingZip,
                ':billing_country' => $billingCountry,
                ':card_last4' => $cardLast4,
                ':card_expiry' => $cardExpiry,
                ':id' => $_SESSION['user_id']
            ]);
            if ($passwordHash !== null) {
                $pw = $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id");
                $pw->execute([':password_hash' => $passwordHash, ':id' => $_SESSION['user_id']]);
            }
            header("Location: profile.php?updated=1");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
</head>
<body>
<h1>User Profile</h1>

<?php if ($updated) { echo "<p>Profile updated successfully.</p>"; } ?>
<?php if (!empty($errors)) { foreach ($errors as $err) { echo "<p>$err</p>"; } } ?>

<form method="post" action="">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
    <h2>Personal Information</h2>
    <label>First Name
        <input type="text" name="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? '', ENT_QUOTES); ?>">
    </label><br>
    <label>Last Name
        <input type="text" name="last_name" value="<?php echo htmlspecialchars($profile['last_name'] ?? '', ENT_QUOTES); ?>">
    </label><br>
    <label>Email
        <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? '', ENT_QUOTES); ?>">
    </label><br>
    <label>Phone
        <input type="text" name="phone" value="<?php echo htmlspecialchars($profile['phone'] ?? '', ENT_QUOTES); ?>">
    </label><br>

    <h2>Shipping Address</h2>
    <label>Address Line 1
        <input type="text" name="address1" value="<?php echo htmlspecialchars($profile['address1'] ?? '', ENT_QUOTES); ?>">
    </label><br>
    <label>Address Line 2
        <input type="text" name="address2" value="<?php echo htmlspecialchars($profile['address2'] ?? '', ENT_QUOTES); ?>">
    </label><br>
    <label>City
        <input type="text" name="city" value="<?php echo htmlspecialchars($profile['city'] ?? '', ENT_QUOTES); ?>">
    </label><br>
    <label>State/Province
        <input type="text" name="state" value="<?php echo htmlspecialchars($profile['state'] ?? '', ENT_QUOTES); ?>">
    </label><br>
    <label>ZIP/Postal Code
        <input type="text" name="zip" value="<?php echo htmlspecialchars($profile['zip'] ?? '', ENT_QUOTES); ?>">
    </label><br>
    <label>Country
        <input type="text" name="country" value="<?php echo htmlspecialchars($profile['country'] ?? '', ENT_QUOTES); ?>">
    </label><br>

    <h2>Billing Details</h2>
    <label>Billing Name
        <input type="text" name="billing_name" value="<?php echo htmlspecialchars($profile['billing_name'] ?? '', ENT_QUOTES); ?>">
    </label><br>
    <label>Billing Address Line 1
        <input type="text" name="billing_address1" value="<?php echo htmlspecialchars($profile['billing_address1'] ?? '', ENT_QUOTES); ?>">
    </label><br>
    <label>Billing Address Line 2
        <input type="text" name="billing_address2" value="<?php echo htmlspecialchars($profile['billing_address2'] ?? '', ENT_QUOTES); ?>">
    </label><br>
    <label>Billing City
        <input type="text" name="billing_city" value="<?php echo htmlspecialchars($profile['billing_city'] ?? '', ENT_QUOTES); ?>">
    </label><br>
    <label>Billing State
        <input type="text" name="billing_state" value="<?php echo htmlspecialchars($profile['billing_state'] ?? '', ENT_QUOTES); ?>">
    </label><br>
    <label>Billing ZIP
        <input type="text" name="billing_zip" value="<?php echo htmlspecialchars($profile['billing_zip'] ?? '', ENT_QUOTES); ?>">
    </label><br>
    <label>Billing Country
        <input type="text" name="billing_country" value="<?php echo htmlspecialchars($profile['billing_country'] ?? '', ENT_QUOTES); ?>">
    </label><br>

    <label>Card Number
        <input type="password" name="card_number" placeholder="Card number not stored securely in this demo">
    </label><br>
    <label>Card Expiry
        <input type="text" name="card_expiry" placeholder="MM/YY" value="<?php echo htmlspecialchars($profile['card_expiry'] ?? '', ENT_QUOTES); ?>">
    </label><br>
    <p>Last 4 digits on file: <?php echo htmlspecialchars($profile['card_last4'] ?? '', ENT_QUOTES); ?></p>

    <h2>Change Password</h2>
    <label>Current Password
        <input type="password" name="current_password">
    </label><br>
    <label>New Password
        <input type="password" name="new_password">
    </label><br>
    <label>Confirm Password
        <input type="password" name="confirm_password">
    </label><br>

    <button type="submit">Update Profile</button>
</form>

</body>
</html>
?>