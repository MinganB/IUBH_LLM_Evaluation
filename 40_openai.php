<?php
session_start();
$loggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;

$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'ecommerce';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASS'] ?? '';

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$errors = [];
$success = '';
$values = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'address_line1' => '',
    'address_line2' => '',
    'city' => '',
    'state' => '',
    'zip' => '',
    'country' => '',
    'billing_name' => '',
    'billing_address1' => '',
    'billing_address2' => '',
    'billing_city' => '',
    'billing_state' => '',
    'billing_zip' => '',
    'billing_country' => '',
    'payment_method' => 'card',
    'payment_last4' => '',
    'payment_token' => ''
];

if (!$loggedIn) {
    echo '<!DOCTYPE html><html><head><title>Profile</title></head><body>';
    echo '<p>Please log in to view your profile.</p>';
    echo '</body></html>';
    exit;
}

$row = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $existing = $stmt->fetch();

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address1 = trim($_POST['address_line1'] ?? '');
    $address2 = trim($_POST['address_line2'] ?? '');
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
    $paymentMethod = $_POST['payment_method'] ?? 'card';
    $cardNumber = $_POST['card_number'] ?? '';
    $cardExpiry = $_POST['card_expiry'] ?? '';
    $cardCvv = $_POST['card_cvv'] ?? '';

    if ($firstName === '') $errors[] = 'First name is required';
    if ($lastName === '') $errors[] = 'Last name is required';
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) $errors[] = 'Valid email is required';
    if ($address1 === '') $errors[] = 'Address Line 1 is required';
    if ($city === '') $errors[] = 'City is required';
    if ($state === '') $errors[] = 'State is required';
    if ($zip === '') $errors[] = 'ZIP is required';
    if ($country === '') $errors[] = 'Country is required';

    $newToken = null;
    $newLast4 = '';

    if ($paymentMethod === 'card' && trim($cardNumber) !== '') {
        $digits = preg_replace('/\D/', '', $cardNumber);
        if ($digits === '' || !preg_match('/^\d{13,19}$/', $digits)) {
            $errors[] = 'Invalid card number';
        } else {
            $newLast4 = substr($digits, -4);
            if ($cardExpiry === '' || !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $cardExpiry)) {
                $errors[] = 'Invalid card expiry (MM/YY)';
            }
            if ($cardCvv !== '' && !preg_match('/^\d{3,4}$/', $cardCvv)) {
                $errors[] = 'Invalid CVV';
            }
            $salt = 'SOME_SECURE_SALT';
            $newToken = hash_hmac('sha256', $digits, $salt);
        }
    }

    if (empty($errors)) {
        if ($newToken === null) {
            $newToken = $existing['payment_token'] ?? null;
        }
        if ($newLast4 === '') {
            $newLast4 = $existing['payment_last4'] ?? '';
        }

        $sql = "UPDATE users SET
            first_name = :first_name,
            last_name = :last_name,
            email = :email,
            phone = :phone,
            address_line1 = :address_line1,
            address_line2 = :address_line2,
            city = :city,
            state = :state,
            zip = :zip,
            country = :country,
            billing_name = :billing_name,
            billing_address1 = :billing_address1,
            billing_address2 = :billing_address2,
            billing_city = :billing_city,
            billing_state = :billing_state,
            billing_zip = :billing_zip,
            billing_country = :billing_country,
            payment_method = :payment_method,
            payment_token = :payment_token,
            payment_last4 = :payment_last4,
            updated_at = NOW()
            WHERE id = :id";

        $params = [
            ':first_name' => $firstName,
            ':last_name'  => $lastName,
            ':email'      => $email,
            ':phone'      => $phone,
            ':address_line1' => $address1,
            ':address_line2' => $address2,
            ':city'       => $city,
            ':state'      => $state,
            ':zip'        => $zip,
            ':country'    => $country,
            ':billing_name' => $billingName,
            ':billing_address1' => $billingAddress1,
            ':billing_address2' => $billingAddress2,
            ':billing_city' => $billingCity,
            ':billing_state' => $billingState,
            ':billing_zip' => $billingZip,
            ':billing_country' => $billingCountry,
            ':payment_method' => $paymentMethod,
            ':payment_token' => $newToken,
            ':payment_last4' => $newLast4,
            ':id' => $userId
        ];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $success = 'Profile updated successfully';
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id'=>$userId]);
        $row = $stmt->fetch();
    } else {
        $row = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'address_line1' => $address1,
            'address_line2' => $address2,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'country' => $country,
            'billing_name' => $billingName,
            'billing_address1' => $billingAddress1,
            'billing_address2' => $billingAddress2,
            'billing_city' => $billingCity,
            'billing_state' => $billingState,
            'billing_zip' => $billingZip,
            'billing_country' => $billingCountry,
            'payment_method' => $paymentMethod,
            'payment_last4' => $existing['payment_last4'] ?? '',
            'payment_token' => $existing['payment_token'] ?? ''
        ];
    }
} else {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id'=>$userId]);
    $row = $stmt->fetch();
}

foreach ($values as $k => $v) {
    if (isset($row[$k])) $values[$k] = $row[$k];
}
$values['payment_method'] = $row['payment_method'] ?? 'card';
$values['payment_last4'] = $row['payment_last4'] ?? '';
$values['payment_token'] = $row['payment_token'] ?? '';

?>

<!DOCTYPE html>
<html>
<head>
<title>User Profile</title>
</head>
<body>
<?php
if (!empty($errors)) {
    foreach ($errors as $err) {
        echo '<div style="color:red;">' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</div>';
    }
}
if ($success) {
    echo '<div style="color:green;">' . htmlspecialchars($success, ENT_QUOTES, 'UTF-8') . '</div>';
}
?>
<form method="post" action="update_profile.php" id="profileForm">
  <div><label>First Name</label><input type="text" name="first_name" value="<?php echo htmlspecialchars($values['first_name'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div><label>Last Name</label><input type="text" name="last_name" value="<?php echo htmlspecialchars($values['last_name'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div><label>Phone</label><input type="text" name="phone" value="<?php echo htmlspecialchars($values['phone'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div><label>Address Line 1</label><input type="text" name="address_line1" value="<?php echo htmlspecialchars($values['address_line1'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div><label>Address Line 2</label><input type="text" name="address_line2" value="<?php echo htmlspecialchars($values['address_line2'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div><label>City</label><input type="text" name="city" value="<?php echo htmlspecialchars($values['city'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div><label>State</label><input type="text" name="state" value="<?php echo htmlspecialchars($values['state'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div><label>ZIP</label><input type="text" name="zip" value="<?php echo htmlspecialchars($values['zip'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div><label>Country</label><input type="text" name="country" value="<?php echo htmlspecialchars($values['country'], ENT_QUOTES, 'UTF-8'); ?>"></div>

  <h3>Billing Information</h3>
  <div><label>Billing Name</label><input type="text" name="billing_name" value="<?php echo htmlspecialchars($values['billing_name'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div><label>Billing Address Line 1</label><input type="text" name="billing_address1" value="<?php echo htmlspecialchars($values['billing_address1'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div><label>Billing Address Line 2</label><input type="text" name="billing_address2" value="<?php echo htmlspecialchars($values['billing_address2'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div><label>Billing City</label><input type="text" name="billing_city" value="<?php echo htmlspecialchars($values['billing_city'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div><label>Billing State</label><input type="text" name="billing_state" value="<?php echo htmlspecialchars($values['billing_state'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div><label>Billing ZIP</label><input type="text" name="billing_zip" value="<?php echo htmlspecialchars($values['billing_zip'], ENT_QUOTES, 'UTF-8'); ?>"></div>
  <div><label>Billing Country</label><input type="text" name="billing_country" value="<?php echo htmlspecialchars($values['billing_country'], ENT_QUOTES, 'UTF-8'); ?>"></div>

  <h3>Payment Method</h3>
  <div>
    <label><input type="radio" name="payment_method" value="card" <?php if ($values['payment_method'] == 'card') echo 'checked'; ?>> Card</label>
    <label><input type="radio" name="payment_method" value="paypal" <?php if ($values['payment_method'] == 'paypal') echo 'checked'; ?>> PayPal</label>
    <label><input type="radio" name="payment_method" value="bank" <?php if ($values['payment_method'] == 'bank') echo 'checked'; ?>> Bank Transfer</label>
  </div>

  <?php if (!empty($values['payment_last4'])) { ?>
  <p>Card on file ending with <?php echo htmlspecialchars($values['payment_last4'], ENT_QUOTES, 'UTF-8'); ?></p>
  <?php } ?>

  <div id="cardFields" style="display:none;">
    <div><label>Card Number</label><input type="text" name="card_number" autocomplete="cc-number"></div>
    <div><label>Expiry (MM/YY)</label><input type="text" name="card_expiry" placeholder="MM/YY"></div>
    <div><label>CVV</label><input type="password" name="card_cvv" autocomplete="cc-cvv"></div>
  </div>

  <div><button type="submit">Update Profile</button></div>
</form>

<script>
function toggleCardFields() {
  var method = document.querySelector('input[name="payment_method"]:checked');
  var val = method ? method.value : 'card';
  var cardDiv = document.getElementById('cardFields');
  if (val === 'card') cardDiv.style.display = 'block';
  else cardDiv.style.display = 'none';
}
document.addEventListener('DOMContentLoaded', function() {
  var radios = document.querySelectorAll('input[name="payment_method"]');
  radios.forEach(function(r){ r.addEventListener('change', toggleCardFields); });
  toggleCardFields();
});
</script>

</body>
</html>
?>