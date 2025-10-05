<?php
session_start();

function getPDO(): PDO {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $db   = getenv('DB_NAME') ?: 'myapp';
    $user = getenv('DB_USER') ?: 'dbuser';
    $pass = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    return new PDO($dsn, $user, $pass, $options);
}

function clean($value) {
    if ($value === null) return null;
    return trim(strip_tags($value));
}

function getCardBrand($number) {
    $n = preg_replace('/\D/', '', $number);
    if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $n)) return 'Visa';
    if (preg_match('/^5[1-5][0-9]{14}$/', $n)) return 'Mastercard';
    if (preg_match('/^3[47][0-9]{13}$/', $n)) return 'American Express';
    if (preg_match('/^6(?:011|5[0-9]{2})[0-9]{12}$/', $n)) return 'Discover';
    return 'Unknown';
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';

$row = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'address_line1' => '',
    'address_line2' => '',
    'city' => '',
    'state' => '',
    'postal_code' => '',
    'country' => '',
    'card_brand' => '',
    'card_last4' => '',
    'card_exp_month' => '',
    'card_exp_year' => '',
    'card_holder_name' => '',
];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid CSRF token';
    }

    $first_name = clean($_POST['first_name'] ?? '');
    $last_name  = clean($_POST['last_name'] ?? '');
    $email_raw  = clean($_POST['email'] ?? '');
    $email = filter_var($email_raw, FILTER_VALIDATE_EMAIL) ? $email_raw : null;
    $phone = clean($_POST['phone'] ?? '');
    $address1 = clean($_POST['address_line1'] ?? '');
    $address2 = clean($_POST['address_line2'] ?? '');
    $city = clean($_POST['city'] ?? '');
    $state = clean($_POST['state'] ?? '');
    $postal = clean($_POST['postal_code'] ?? '');
    $country = clean($_POST['country'] ?? '');

    $card_holder = clean($_POST['card_holder_name'] ?? '');
    $card_number = $_POST['card_number'] ?? '';
    $expiry_month = clean($_POST['expiry_month'] ?? '');
    $expiry_year  = clean($_POST['expiry_year'] ?? '');

    $card_last4 = null;
    $card_brand = null;

    if (!$first_name) $errors[] = 'First name is required';
    if (!$last_name) $errors[] = 'Last name is required';
    if (!$email) $errors[] = 'A valid email is required';
    if (!$address1) $errors[] = 'Address line 1 is required';
    if (!$city) $errors[] = 'City is required';
    if (!$postal) $errors[] = 'Postal code is required';
    if (!$country) $errors[] = 'Country is required';

    if ($card_number !== '') {
        $num = preg_replace('/\D/', '', $card_number);
        if (strlen($num) < 12 || strlen($num) > 19) {
            $errors[] = 'Card number must be between 12 and 19 digits';
        } else {
            $card_last4 = substr($num, -4);
            $card_brand = getCardBrand($num);
            if ($card_brand === 'Unknown') {
                $errors[] = 'Unsupported card type';
            }
        }
        $exp_m = intval($expiry_month);
        $exp_y = intval($expiry_year);
        if ($exp_m < 1 || $exp_m > 12) {
            $errors[] = 'Expiry month must be between 1 and 12';
        }
        $currentYear = intval(date('Y'));
        $currentMonth = intval(date('m'));
        if ($exp_y < $currentYear) {
            $errors[] = 'Expiry year cannot be in the past';
        } elseif ($exp_y == $currentYear && $exp_m < $currentMonth) {
            $errors[] = 'Card has expired';
        }
    }

    if (empty($errors)) {
        try {
            $pdo = getPDO();

            $updateSql = "UPDATE users SET first_name = :first, last_name = :last, email = :email, phone = :phone, address_line1 = :addr1, address_line2 = :addr2, city = :city, state = :state, postal_code = :postal, country = :country WHERE id = :id";
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute([
                ':first' => $first_name,
                ':last'  => $last_name,
                ':email' => $email,
                ':phone' => $phone,
                ':addr1' => $address1,
                ':addr2' => $address2,
                ':city'  => $city,
                ':state' => $state,
                ':postal'=> $postal,
                ':country'=> $country,
                ':id'    => $user_id
            ]);

            $checkBilling = $pdo->prepare("SELECT user_id FROM billing WHERE user_id = :id");
            $checkBilling->execute([':id' => $user_id]);
            if ($checkBilling->fetch()) {
                if ($card_last4 !== null) {
                    $upSql = "UPDATE billing SET card_holder_name = :holder, card_brand = :brand, card_last4 = :last4, card_exp_month = :expm, card_exp_year = :expy WHERE user_id = :id";
                    $stmt2 = $pdo->prepare($upSql);
                    $stmt2->execute([
                        ':holder' => $card_holder,
                        ':brand'  => $card_brand,
                        ':last4'  => $card_last4,
                        ':expm'   => $expiry_month,
                        ':expy'   => $expiry_year,
                        ':id'     => $user_id
                    ]);
                } else {
                    $upSql = "UPDATE billing SET card_holder_name = :holder WHERE user_id = :id";
                    $stmt2 = $pdo->prepare($upSql);
                    $stmt2->execute([':holder' => $card_holder, ':id' => $user_id]);
                }
            } else {
                $insSql = "INSERT INTO billing (user_id, card_brand, card_last4, card_exp_month, card_exp_year, card_holder_name) VALUES (:id, :brand, :last4, :expm, :expy, :holder)";
                $stmt2 = $pdo->prepare($insSql);
                $stmt2->execute([
                    ':id' => $user_id,
                    ':brand' => $card_brand,
                    ':last4' => $card_last4,
                    ':expm' => $expiry_month,
                    ':expy' => $expiry_year,
                    ':holder' => $card_holder
                ]);
            }

            $success = 'Profile updated successfully';
        } catch (Exception $e) {
            $errors[] = 'Server error: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $row['first_name'] = $first_name;
        $row['last_name']  = $last_name;
        $row['email']      = $email;
        $row['phone']      = $phone;
        $row['address_line1'] = $address1;
        $row['address_line2'] = $address2;
        $row['city']       = $city;
        $row['state']      = $state;
        $row['postal_code']= $postal;
        $row['country']      = $country;
        $row['card_holder_name'] = $card_holder;
        // card_brand and card_last4 remain as previously loaded
        if (!empty($row['card_last4'])) {
            // keep existing
        }
    }
}

// Load current user data
try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("
        SELECT u.first_name, u.last_name, u.email, u.phone, u.address_line1, u.address_line2, u.city, u.state, u.postal_code, u.country,
               b.card_brand, b.card_last4, b.card_exp_month, b.card_exp_year, b.card_holder_name
        FROM users u
        LEFT JOIN billing b ON u.id = b.user_id
        WHERE u.id = :id
    ");
    $stmt->execute([':id' => $user_id]);
    $dbRow = $stmt->fetch();
    if ($dbRow) {
        $row['first_name'] = $dbRow['first_name'];
        $row['last_name'] = $dbRow['last_name'];
        $row['email'] = $dbRow['email'];
        $row['phone'] = $dbRow['phone'];
        $row['address_line1'] = $dbRow['address_line1'];
        $row['address_line2'] = $dbRow['address_line2'];
        $row['city'] = $dbRow['city'];
        $row['state'] = $dbRow['state'];
        $row['postal_code'] = $dbRow['postal_code'];
        $row['country'] = $dbRow['country'];
        $row['card_brand'] = $dbRow['card_brand'] ?? '';
        $row['card_last4'] = $dbRow['card_last4'] ?? '';
        $row['card_exp_month'] = $dbRow['card_exp_month'] ?? '';
        $row['card_exp_year'] = $dbRow['card_exp_year'] ?? '';
        $row['card_holder_name'] = $dbRow['card_holder_name'] ?? '';
    }
} catch (Exception $e) {
    // If loading fails, keep defaults
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
</head>
<body>
<?php if (!empty($errors)): ?>
    <div role="alert" style="color:red;">
        <?php foreach ($errors as $err): ?>
            <div><?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div role="status" style="color:green;">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <h2>Personal Information</h2>
    <label>First name:
        <input type="text" name="first_name" value="<?php echo htmlspecialchars($row['first_name']); ?>" required>
    </label><br>
    <label>Last name:
        <input type="text" name="last_name" value="<?php echo htmlspecialchars($row['last_name']); ?>" required>
    </label><br>
    <label>Email:
        <input type="email" name="email" value="<?php echo htmlspecialchars($row['email']); ?>" required>
    </label><br>
    <label>Phone:
        <input type="text" name="phone" value="<?php echo htmlspecialchars($row['phone']); ?>">
    </label><br>

    <h2>Billing Details</h2>
    <?php if (!empty($row['card_last4'])): ?>
        <div>Current card on file: <?php echo htmlspecialchars($row['card_brand'] ?? 'Card'); ?> ending <?php echo htmlspecialchars($row['card_last4']); ?></div>
    <?php else: ?>
        <div>No card on file</div>
    <?php endif; ?>
    <label>Card holder name:
        <input type="text" name="card_holder_name" value="<?php echo htmlspecialchars($row['card_holder_name']); ?>">
    </label><br>
    <label>Card number:
        <input type="text" name="card_number" autocomplete="new-password" placeholder="Enter to update card">
    </label><br>
    <label>Expiry month:
        <input type="text" name="expiry_month" value="<?php echo htmlspecialchars($row['card_exp_month']); ?>">
    </label><br>
    <label>Expiry year:
        <input type="text" name="expiry_year" value="<?php echo htmlspecialchars($row['card_exp_year']); ?>">
    </label><br>

    <h3>Billing Address</h3>
    <label>Address line 1:
        <input type="text" name="address_line1" value="<?php echo htmlspecialchars($row['address_line1']); ?>" required>
    </label><br>
    <label>Address line 2:
        <input type="text" name="address_line2" value="<?php echo htmlspecialchars($row['address_line2']); ?>">
    </label><br>
    <label>City:
        <input type="text" name="city" value="<?php echo htmlspecialchars($row['city']); ?>" required>
    </label><br>
    <label>State:
        <input type="text" name="state" value="<?php echo htmlspecialchars($row['state']); ?>">
    </label><br>
    <label>Postal code:
        <input type="text" name="postal_code" value="<?php echo htmlspecialchars($row['postal_code']); ?>" required>
    </label><br>
    <label>Country:
        <input type="text" name="country" value="<?php echo htmlspecialchars($row['country']); ?>" required>
    </label><br>

    <button type="submit">Update Profile</button>
</form>
</body>
</html>
?>