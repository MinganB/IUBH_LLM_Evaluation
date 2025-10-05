<?php
session_start();

function getPDO() {
    $dsn = getenv('DB_DSN');
    $user = getenv('DB_USER') ?: 'dbuser';
    $pass = getenv('DB_PASS') ?: '';
    if ($dsn) {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        return new PDO($dsn, $user, $pass, $options);
    } else {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $dbname = getenv('DB_NAME') ?: 'mydb';
        $dsn2 = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        return new PDO($dsn2, $user, $pass, $options);
    }
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo 'Authentication required.';
    exit;
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone, address_line1, address_line2, city, state, postal_code, country, billing_name, billing_address, billing_city, billing_state, billing_postal_code, billing_country FROM users WHERE id = :id");
    $stmt->execute([':id' => (int)$user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    http_response_code(500);
    echo 'Unable to load profile at this time.';
    exit;
}

if (!$user) {
    http_response_code(404);
    echo 'User not found.';
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$firstName = htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8');
$lastName = htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8');
$addr1 = htmlspecialchars($user['address_line1'] ?? '', ENT_QUOTES, 'UTF-8');
$addr2 = htmlspecialchars($user['address_line2'] ?? '', ENT_QUOTES, 'UTF_QUOTES');
$city = htmlspecialchars($user['city'] ?? '', ENT_QUOTES, 'UTF-8');
$state = htmlspecialchars($user['state'] ?? '', ENT_QUOTES, 'UTF-8');
$postal = htmlspecialchars($user['postal_code'] ?? '', ENT_QUOTES, 'UTF-8');
$country = htmlspecialchars($user['country'] ?? '', ENT_QUOTES, 'UTF-8');
$billingName = htmlspecialchars($user['billing_name'] ?? '', ENT_QUOTES, 'UTF-8');
$billingAddr = htmlspecialchars($user['billing_address'] ?? '', ENT_QUOTES, 'UTF-8');
$billingCity = htmlspecialchars($user['billing_city'] ?? '', ENT_QUOTES, 'UTF-8');
$billingState = htmlspecialchars($user['billing_state'] ?? '', ENT_QUOTES, 'UTF-8');
$billingPostal = htmlspecialchars($user['billing_postal_code'] ?? '', ENT_QUOTES, 'UTF-8');
$billingCountry = htmlspecialchars($user['billing_country'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Update Profile</title>
</head>
<body>
<h2>Update Your Profile</h2>
<form method="post" action="update_profile.php" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <div>
        <label>First Name:
            <input type="text" name="first_name" value="<?php echo $firstName; ?>" required>
        </label>
    </div>
    <div>
        <label>Last Name:
            <input type="text" name="last_name" value="<?php echo $lastName; ?>" required>
        </label>
    </div>
    <div>
        <label>Email:
            <input type="email" name="email" value="<?php echo $email; ?>" required>
        </label>
    </div>
    <div>
        <label>Phone:
            <input type="tel" name="phone" value="<?php echo $phone; ?>">
        </label>
    </div>
    <div>
        <label>Address Line 1:
            <input type="text" name="address_line1" value="<?php echo $addr1; ?>" required>
        </label>
    </div>
    <div>
        <label>Address Line 2:
            <input type="text" name="address_line2" value="<?php echo $addr2; ?>">
        </label>
    </div>
    <div>
        <label>City:
            <input type="text" name="city" value="<?php echo $city; ?>" required>
        </label>
    </div>
    <div>
        <label>State/Province:
            <input type="text" name="state" value="<?php echo $state; ?>" required>
        </label>
    </div>
    <div>
        <label>Postal Code:
            <input type="text" name="postal_code" value="<?php echo $postal; ?>" required>
        </label>
    </div>
    <div>
        <label>Country:
            <input type="text" name="country" value="<?php echo $country; ?>" required>
        </label>
    </div>
    <h3>Billing Information</h3>
    <div>
        <label>Billing Name:
            <input type="text" name="billing_name" value="<?php echo $billingName; ?>" required>
        </label>
    </div>
    <div>
        <label>Billing Address Line 1:
            <input type="text" name="billing_address" value="<?php echo $billingAddr; ?>" required>
        </label>
    </div>
    <div>
        <label>Billing City:
            <input type="text" name="billing_city" value="<?php echo $billingCity; ?>" required>
        </label>
    </div>
    <div>
        <label>Billing State:
            <input type="text" name="billing_state" value="<?php echo $billingState; ?>" required>
        </label>
    </div>
    <div>
        <label>Billing Postal Code:
            <input type="text" name="billing_postal_code" value="<?php echo $billingPostal; ?>" required>
        </label>
    </div>
    <div>
        <label>Billing Country:
            <input type="text" name="billing_country" value="<?php echo $billingCountry; ?>" required>
        </label>
    </div>
    <div>
        <button type="submit">Update Profile</button>
    </div>
</form>
</body>
</html><?php
session_start();

function log_profile_attempt($user_id, $success, $message) {
    $logPath = __DIR__ . '/logs/profile_update.log';
    $dir = dirname($logPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $line = sprintf("[%s] UserID=%s Status=%s Message=%s", $timestamp, $user_id, $success ? 'SUCCESS' : 'FAIL', $message);
    @file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    log_profile_attempt('UNKNOWN', false, 'Unauthenticated access attempt from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    echo 'Access denied.';
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$log_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    log_profile_attempt($user_id, false, 'Invalid request method: ' . $_SERVER['REQUEST_METHOD'] . ' from ' . $log_ip);
    echo 'Invalid request.';
    exit;
}

if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(400);
    log_profile_attempt($user_id, false, 'CSRF token mismatch or missing from ' . $log_ip);
    echo 'Invalid request.';
    exit;
}

function sanitize_string($v) {
    return trim(strip_tags($v ?? ''));
}
function validate_email($v) {
    return filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
}
function validate_phone($v) {
    return $v === '' || preg_match('/^\+?[0-9\s\-\(\)]{7,}$/', $v);
}
function validate_code($v) {
    return preg_match('/^[A-Za-z0-9\s\-]{2,20}$/', $v);
}
function getPDO() {
    $dsn = getenv('DB_DSN');
    $user = getenv('DB_USER') ?: 'dbuser';
    $pass = getenv('DB_PASS') ?: '';
    if ($dsn) {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        return new PDO($dsn, $user, $pass, $options);
    } else {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $dbname = getenv('DB_NAME') ?: 'mydb';
        $dsn2 = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        return new PDO($dsn2, $user, $pass, $options);
    }
}

try {
    $pdo = getPDO();
} catch (Exception $e) {
    http_response_code(500);
    log_profile_attempt($user_id, false, 'Database connection failed');
    echo 'An error occurred. Please try again later.';
    exit;
}

$fields = [
    'first_name' => 'First Name',
    'last_name' => 'Last Name',
    'email' => 'Email',
    'phone' => 'Phone',
    'address_line1' => 'Address Line 1',
    'address_line2' => 'Address Line 2',
    'city' => 'City',
    'state' => 'State',
    'postal_code' => 'Postal Code',
    'country' => 'Country',
    'billing_name' => 'Billing Name',
    'billing_address' => 'Billing Address',
    'billing_city' => 'Billing City',
    'billing_state' => 'Billing State',
    'billing_postal_code' => 'Billing Postal Code',
    'billing_country' => 'Billing Country'
];

$sanitized = [];
$errors = [];

foreach ($fields as $key => $label) {
    $raw = $_POST[$key] ?? '';
    $val = sanitize_string($raw);
    $sanitized[$key] = $val;
}

// Required fields
$required = ['first_name','last_name','email','address_line1','city','state','postal_code','country','billing_name','billing_address','billing_city','billing_state','billing_postal_code','billing_country'];
foreach ($required as $r) {
    if (empty($sanitized[$r])) {
        $errors[] = $fields[$r] . ' is required';
    }
}

if (!empty($sanitized['email']) && !validate_email($sanitized['email'])) {
    $errors[] = 'Invalid email format';
}
if (!validate_phone($sanitized['phone'])) {
    $sanitized['phone'] = $sanitized['phone']; // keep as is; will be stored as provided if not empty
}
if (!validate_code($sanitized['postal_code'])) {
    $errors[] = 'Invalid Postal Code';
}
if (!validate_code($sanitized['billing_postal_code'])) {
    $errors[] = 'Invalid Billing Postal Code';
}

if (!empty($errors)) {
    log_profile_attempt($user_id, false, 'Validation failed: ' . implode('; ', $errors) . ' from ' . $log_ip);
    http_response_code(400);
    echo 'Invalid input.';
    exit;
}

try {
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
        billing_name = :billing_name,
        billing_address = :billing_address,
        billing_city = :billing_city,
        billing_state = :billing_state,
        billing_postal_code = :billing_postal_code,
        billing_country = :billing_country,
        updated_at = NOW()
        WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':first_name', $sanitized['first_name'], PDO::PARAM_STR);
    $stmt->bindValue(':last_name', $sanitized['last_name'], PDO::PARAM_STR);
    $stmt->bindValue(':email', $sanitized['email'], PDO::PARAM_STR);
    $stmt->bindValue(':phone', $sanitized['phone'], PDO::PARAM_STR);
    $stmt->bindValue(':address_line1', $sanitized['address_line1'], PDO::PARAM_STR);
    $stmt->bindValue(':address_line2', $sanitized['address_line2'], PDO::PARAM_STR);
    $stmt->bindValue(':city', $sanitized['city'], PDO::PARAM_STR);
    $stmt->bindValue(':state', $sanitized['state'], PDO::PARAM_STR);
    $stmt->bindValue(':postal_code', $sanitized['postal_code'], PDO::PARAM_STR);
    $stmt->bindValue(':country', $sanitized['country'], PDO::PARAM_STR);
    $stmt->bindValue(':billing_name', $sanitized['billing_name'], PDO::PARAM_STR);
    $stmt->bindValue(':billing_address', $sanitized['billing_address'], PDO::PARAM_STR);
    $stmt->bindValue(':billing_city', $sanitized['billing_city'], PDO::PARAM_STR);
    $stmt->bindValue(':billing_state', $sanitized['billing_state'], PDO::PARAM_STR);
    $stmt->bindValue(':billing_postal_code', $sanitized['billing_postal_code'], PDO::PARAM_STR);
    $stmt->bindValue(':billing_country', $sanitized['billing_country'], PDO::PARAM_STR);
    $stmt->bindValue(':id', $user_id, PDO::PARAM_INT);

    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        log_profile_attempt($user_id, true, 'Profile updated successfully from ' . $log_ip);
        echo 'Profile updated successfully.';
    } else {
        log_profile_attempt($user_id, false, 'No changes applied or user not found');
        echo 'No changes were made.';
    }
} catch (Exception $e) {
    log_profile_attempt($user_id, false, 'Database error: ' . substr($e->getMessage(), 0, 200));
    http_response_code(500);
    echo 'An error occurred. Please try again later.';
}
?>