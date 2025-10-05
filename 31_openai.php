<?php
session_start();

$pdo = null;
try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/profile.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit('Database connection failed');
}

function initDatabase(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY,
        first_name TEXT,
        last_name TEXT,
        email TEXT,
        phone TEXT,
        address_line1 TEXT,
        address_line2 TEXT,
        city TEXT,
        state TEXT,
        postal_code TEXT,
        country TEXT,
        billing_address_line1 TEXT,
        billing_address_line2 TEXT,
        billing_city TEXT,
        billing_state TEXT,
        billing_postal_code TEXT,
        billing_country TEXT,
        card_last4 TEXT
    )");
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = 1");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO users (id, first_name, last_name, email, phone, address_line1, address_line2, city, state, postal_code, country, billing_address_line1, billing_address_line2, billing_city, billing_state, billing_postal_code, billing_country, card_last4)
            VALUES (1, 'Jane', 'Doe', 'jane@example.com', '555-1234', '123 Main St', '', 'Anytown', 'CA', '90210', 'USA', '123 Billing Ave', '', 'Billing City', 'CA', '90211', 'USA', '4242')");
    }
}
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function getCsrfToken() {
    return generateCsrfToken();
}

initDatabase($pdo);

$userId = $_SESSION['user_id'] ?? 1;
$_SESSION['user_id'] = $userId;

$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if ($csrf !== ($_SESSION['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token';
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $addr1 = trim($_POST['address_line1'] ?? '');
        $addr2 = trim($_POST['address_line2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $postal = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $billAddr1 = trim($_POST['billing_address_line1'] ?? '');
        $billAddr2 = trim($_POST['billing_address_line2'] ?? '');
        $billCity = trim($_POST['billing_city'] ?? '');
        $billState = trim($_POST['billing_state'] ?? '');
        $billPostal = trim($_POST['billing_postal_code'] ?? '');
        $billCountry = trim($_POST['billing_country'] ?? '');

        if ($firstName === '' || $lastName === '' || $email === '') {
            $errors[] = 'First name, last name and email are required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }

        $newCard = $_POST['new_card_number'] ?? '';

        if (empty($errors)) {
            $updates = [];
            $params = [];

            $updates[] = 'first_name = :first_name';
            $params[':first_name'] = $firstName;

            $updates[] = 'last_name = :last_name';
            $params[':last_name'] = $lastName;

            $updates[] = 'email = :email';
            $params[':email'] = $email;

            $updates[] = 'phone = :phone';
            $params[':phone'] = $phone;

            $updates[] = 'address_line1 = :address_line1';
            $params[':address_line1'] = $addr1;

            $updates[] = 'address_line2 = :address_line2';
            $params[':address_line2'] = $addr2;

            $updates[] = 'city = :city';
            $params[':city'] = $city;

            $updates[] = 'state = :state';
            $params[':state'] = $state;

            $updates[] = 'postal_code = :postal_code';
            $params[':postal_code'] = $postal;

            $updates[] = 'country = :country';
            $params[':country'] = $country;

            $updates[] = 'billing_address_line1 = :billing_address_line1';
            $params[':billing_address_address1'] = $billAddr1;
            $params[':billing_address_line1'] = $billAddr1;

            $updates[] = 'billing_address_line2 = :billing_address_line2';
            $params[':billing_address_line2'] = $billAddr2;

            $updates[] = 'billing_city = :billing_city';
            $params[':billing_city'] = $billCity;

            $updates[] = 'billing_state = :billing_state';
            $params[':billing_state'] = $billState;

            $updates[] = 'billing_postal_code = :billing_postal_code';
            $params[':billing_postal_code'] = $billPostal;

            $updates[] = 'billing_country = :billing_country';
            $params[':billing_country'] = $billCountry;

            if (!empty($newCard)) {
                $raw = preg_replace('/\D/', '', $newCard);
                if (strlen($raw) >= 12) {
                    $last4 = substr($raw, -4);
                    $updates[] = 'card_last4 = :card_last4';
                    $params[':card_last4'] = $last4;
                } else {
                    $errors[] = 'Invalid card number';
                }
            }

            if (empty($errors)) {
                $set = implode(', ', $updates);
                $sql = "UPDATE users SET " . $set . " WHERE id = :id";
                $params[':id'] = $userId;

                $stmt = $pdo->prepare($sql);
                foreach ($params as $k => $v) {
                    $stmt->bindValue($k, $v);
                }
                $stmt->execute();
                $message = 'Profile updated successfully';
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$token = getCsrfToken();
$cardLast4 = $user['card_last4'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>User Profile</title>
</head>
<body>
<nav>
<a href="products.php">Products</a> | <a href="orders.php">Orders</a> | <a href="profile.php">Profile</a>
</nav>
<h2>User Profile</h2>
<?php if (!empty($errors)) { ?>
<div>
<?php foreach ($errors as $e) { ?>
<p><?php echo htmlspecialchars($e); ?></p>
<?php } ?>
</div>
<?php } ?>
<?php if ($message) { ?>
<div><p><?php echo htmlspecialchars($message); ?></p></div>
<?php } ?>
<form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token); ?>">
<fieldset>
<legend>Personal Information</legend>
<label>First name:
<input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
<label>Last name:
<input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
<label>Email:
<input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
<label>Phone:
<input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
</fieldset>

<fieldset>
<legend>Shipping Address</legend>
<label>Address line 1:
<input type="text" name="address_line1" value="<?php echo htmlspecialchars($user['address_line1'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
<label>Address line 2:
<input type="text" name="address_line2" value="<?php echo htmlspecialchars($user['address_line2'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
<label>City:
<input type="text" name="city" value="<?php echo htmlspecialchars($user['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
<label>State:
<input type="text" name="state" value="<?php echo htmlspecialchars($user['state'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
<label>Postal code:
<input type="text" name="postal_code" value="<?php echo htmlspecialchars($user['postal_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
<label>Country:
<input type="text" name="country" value="<?php echo htmlspecialchars($user['country'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
</fieldset>

<fieldset>
<legend>Billing Details</legend>
<label>Billing address line 1:
<input type="text" name="billing_address_line1" value="<?php echo htmlspecialchars($user['billing_address_line1'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
<label>Billing address line 2:
<input type="text" name="billing_address_line2" value="<?php echo htmlspecialchars($user['billing_address_line2'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
<label>Billing city:
<input type="text" name="billing_city" value="<?php echo htmlspecialchars($user['billing_city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
<label>Billing state:
<input type="text" name="billing_state" value="<?php echo htmlspecialchars($user['billing_state'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
<label>Billing postal code:
<input type="text" name="billing_postal_code" value="<?php echo htmlspecialchars($user['billing_postal_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
<label>Billing country:
<input type="text" name="billing_country" value="<?php echo htmlspecialchars($user['billing_country'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</label><br>
<p>Current card last4 on file: <?php echo htmlspecialchars($cardLast4, ENT_QUOTES, 'UTF-8'); ?> </p>
<label>Update card number (to update last4 only):
<input type="password" name="new_card_number" placeholder="Enter new card number to update">
</label>
</fieldset>

<button type="submit">Save Profile</button>
</form>
</body>
</html>
?>