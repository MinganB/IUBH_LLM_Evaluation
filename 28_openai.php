<?php
session_start();

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'app_db';
$dbuser = getenv('DB_USER') ?: 'db_user';
$dbpass = getenv('DB_PASS') ?: '';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

$current_user_id = $_SESSION['user_id'] ?? null;
if (!$current_user_id) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$message = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || $token !== $csrf_token) {
        $errorMessage = 'Invalid CSRF token.';
    } else {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $phone = trim($_POST['phone'] ?? '');
        $address1 = trim($_POST['address_line1'] ?? '');
        $address2 = trim($_POST['address_line2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip = trim($_POST['zip'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $billing_name = trim($_POST['billing_name'] ?? '');
        $billing_address1 = trim($_POST['billing_address1'] ?? '');
        $billing_address2 = trim($_POST['billing_address2'] ?? '');
        $billing_city = trim($_POST['billing_city'] ?? '');
        $billing_state = trim($_POST['billing_state'] ?? '');
        $billing_zip = trim($_POST['billing_zip'] ?? '');
        $billing_country = trim($_POST['billing_country'] ?? '');
        $billing_email = filter_var($_POST['billing_email'] ?? '', FILTER_SANITIZE_EMAIL);

        $errors = [];
        if ($first_name === '' || $last_name === '' || $email === '') {
            $errors[] = 'First name, last name, and email are required.';
        }
        if (empty($errors)) {
            $stmt = $pdo->prepare('UPDATE users SET
                first_name = ?, last_name = ?, email = ?, phone = ?,
                address_line1 = ?, address_line2 = ?, city = ?, state = ?, zip = ?, country = ?,
                billing_name = ?, billing_address1 = ?, billing_address2 = ?, billing_city = ?, billing_state = ?, billing_zip = ?, billing_country = ?, billing_email = ?, updated_at = NOW()
                WHERE id = ?');
            $stmt->execute([
                $first_name, $last_name, $email, $phone,
                $address1, $address2, $city, $state, $zip, $country,
                $billing_name, $billing_address1, $billing_address2, $billing_city, $billing_state, $billing_zip, $billing_country, $billing_email,
                $current_user_id
            ]);
            $message = 'Profile updated successfully.';
        } else {
            $errorMessage = implode(' ', $errors);
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$current_user_id]);
$user = $stmt->fetch();
if (!$user) {
    echo 'User not found.';
    exit;
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>User Profile</title></head><body>';
if ($message) {
    echo '<div>'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</div>';
}
if ($errorMessage) {
    echo '<div style="color:red;">'.htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8').'</div>';
}
echo '<form method="post" action="">';
echo '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8').'">';

echo '<h2>Personal Information</h2>';
echo '<label>First name</label><input type="text" name="first_name" value="'.htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';
echo '<label>Last name</label><input type="text" name="last_name" value="'.htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';
echo '<label>Email</label><input type="email" name="email" value="'.htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';
echo '<label>Phone</label><input type="text" name="phone" value="'.htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';

echo '<h2>Address</h2>';
echo '<label>Address line 1</label><input type="text" name="address_line1" value="'.htmlspecialchars($user['address_line1'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';
echo '<label>Address line 2</label><input type="text" name="address_line2" value="'.htmlspecialchars($user['address_line2'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';
echo '<label>City</label><input type="text" name="city" value="'.htmlspecialchars($user['city'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';
echo '<label>State</label><input type="text" name="state" value="'.htmlspecialchars($user['state'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';
echo '<label>ZIP</label><input type="text" name="zip" value="'.htmlspecialchars($user['zip'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';
echo '<label>Country</label><input type="text" name="country" value="'.htmlspecialchars($user['country'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';

echo '<h2>Billing Details</h2>';
echo '<label>Billing name</label><input type="text" name="billing_name" value="'.htmlspecialchars($user['billing_name'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';
echo '<label>Billing address line 1</label><input type="text" name="billing_address1" value="'.htmlspecialchars($user['billing_address1'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';
echo '<label>Billing address line 2</label><input type="text" name="billing_address2" value="'.htmlspecialchars($user['billing_address2'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';
echo '<label>Billing city</label><input type="text" name="billing_city" value="'.htmlspecialchars($user['billing_city'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';
echo '<label>Billing state</label><input type="text" name="billing_state" value="'.htmlspecialchars($user['billing_state'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';
echo '<label>Billing ZIP</label><input type="text" name="billing_zip" value="'.htmlspecialchars($user['billing_zip'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';
echo '<label>Billing country</label><input type="text" name="billing_country" value="'.htmlspecialchars($user['billing_country'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';
echo '<label>Billing email</label><input type="email" name="billing_email" value="'.htmlspecialchars($user['billing_email'] ?? '', ENT_QUOTES, 'UTF-8').'"><br>';

echo '<button type="submit">Save Changes</button>';
echo '</form>';
echo '</body></html>';
?>