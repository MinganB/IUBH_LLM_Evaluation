<?php
session_start();

if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$servername = "localhost";
$username = "db_user";
$password = "db_password";
$dbname = "ecommerce_db";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("Database connection failed");
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile</title>
</head>
<body>
    <form action="update_profile.php" method="POST">
        <h2>Personal Information</h2>
        <label for="first_name">First Name:</label>
        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="50">
        
        <label for="last_name">Last Name:</label>
        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="50">
        
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
        
        <label for="phone">Phone:</label>
        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="20">
        
        <h2>Address Information</h2>
        <label for="street_address">Street Address:</label>
        <input type="text" id="street_address" name="street_address" value="<?php echo htmlspecialchars($user['street_address'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="255">
        
        <label for="city">City:</label>
        <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user['city'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
        
        <label for="state">State/Province:</label>
        <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($user['state'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
        
        <label for="postal_code">Postal Code:</label>
        <input type="text" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($user['postal_code'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="20">
        
        <label for="country">Country:</label>
        <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($user['country'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
        
        <h2>Billing Information</h2>
        <label for="billing_first_name">Billing First Name:</label>
        <input type="text" id="billing_first_name" name="billing_first_name" value="<?php echo htmlspecialchars($user['billing_first_name'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="50">
        
        <label for="billing_last_name">Billing Last Name:</label>
        <input type="text" id="billing_last_name" name="billing_last_name" value="<?php echo htmlspecialchars($user['billing_last_name'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="50">
        
        <label for="billing_street_address">Billing Street Address:</label>
        <input type="text" id="billing_street_address" name="billing_street_address" value="<?php echo htmlspecialchars($user['billing_street_address'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="255">
        
        <label for="billing_city">Billing City:</label>
        <input type="text" id="billing_city" name="billing_city" value="<?php echo htmlspecialchars($user['billing_city'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
        
        <label for="billing_state">Billing State/Province:</label>
        <input type="text" id="billing_state" name="billing_state" value="<?php echo htmlspecialchars($user['billing_state'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
        
        <label for="billing_postal_code">Billing Postal Code:</label>
        <input type="text" id="billing_postal_code" name="billing_postal_code" value="<?php echo htmlspecialchars($user['billing_postal_code'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="20">
        
        <label for="billing_country">Billing Country:</label>
        <input type="text" id="billing_country" name="billing_country" value="<?php echo htmlspecialchars($user['billing_country'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
        
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); ?>">
        
        <button type="submit">Update Profile</button>
    </form>
</body>
</html>


<?php
session_start();

function logActivity($message) {
    $log_file = '/var/log/profile_updates.log';
    $timestamp = date('Y-m-d H:i:s');
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $log_entry = "[$timestamp] User ID: $user_id | IP: $ip | $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function validateInput($data, $type) {
    $data = trim($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL) ? $data : false;
        case 'phone':
            return preg_match('/^[\+]?[0-9\-\(\)\s]{10,20}$/', $data) ? $data : false;
        case 'name':
            return preg_match('/^[a-zA-Z\s\-\']{1,50}$/', $data) ? $data : false;
        case 'address':
            return preg_match('/^[a-zA-Z0-9\s\-\.,#]{0,255}$/', $data) ? $data : false;
        case 'city':
            return preg_match('/^[a-zA-Z\s\-\']{0,100}$/', $data) ? $data : false;
        case 'state':
            return preg_match('/^[a-zA-Z\s\-\']{0,100}$/', $data) ? $data : false;
        case 'postal':
            return preg_match('/^[a-zA-Z0-9\s\-]{0,20}$/', $data) ? $data : false;
        case 'country':
            return preg_match('/^[a-zA-Z\s\-\']{0,100}$/', $data) ? $data : false;
        default:
            return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logActivity("Invalid request method attempted");
    header('Location: profile.php');
    exit;
}

if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    logActivity("Unauthorized profile update attempt - no valid session");
    header('Location: login.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    logActivity("CSRF token validation failed");
    header('Location: profile.php?error=invalid_token');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$first_name = validateInput($_POST['first_name'] ?? '', 'name');
$last_name = validateInput($_POST['last_name'] ?? '', 'name');
$email = validateInput($_POST['email'] ?? '', 'email');
$phone = validateInput($_POST['phone'] ?? '', 'phone');
$street_address = validateInput($_POST['street_address'] ?? '', 'address');
$city = validateInput($_POST['city'] ?? '', 'city');
$state = validateInput($_POST['state'] ?? '', 'state');
$postal_code = validateInput($_POST['postal_code'] ?? '', 'postal');
$country = validateInput($_POST['country'] ?? '', 'country');
$billing_first_name = validateInput($_POST['billing_first_name'] ?? '', 'name');
$billing_last_name = validateInput($_POST['billing_last_name'] ?? '', 'name');
$billing_street_address = validateInput($_POST['billing_street_address'] ?? '', 'address');
$billing_city = validateInput($_POST['billing_city'] ?? '', 'city');
$billing_state = validateInput($_POST['billing_state'] ?? '', 'state');
$billing_postal_code = validateInput($_POST['billing_postal_code'] ?? '', 'postal');
$billing_country = validateInput($_POST['billing_country'] ?? '', 'country');

if ($first_name === false || $last_name === false || $email === false) {
    logActivity("Validation failed for required fields");
    header('Location: profile.php?error=validation_failed');
    exit;
}

if (!empty($_POST['phone']) && $phone === false) {
    logActivity("Phone number validation failed");
    header('Location: profile.php?error=invalid_phone');
    exit;
}

$servername = "localhost";
$username = "db_user";
$password = "db_password";
$dbname = "ecommerce_db";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    logActivity("Database connection failed");
    header('Location: profile.php?error=system_error');
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->execute([$email, $user_id]);
if ($stmt->fetch()) {
    logActivity("Attempted to use duplicate email address");
    header('Location: profile.php?error=email_exists');
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE users SET 
        first_name = ?, 
        last_name = ?, 
        email = ?, 
        phone = ?, 
        street_address = ?, 
        city = ?, 
        state = ?, 
        postal_code = ?, 
        country = ?, 
        billing_first_name = ?, 
        billing_last_name = ?, 
        billing_street_address = ?, 
        billing_city = ?, 
        billing_state = ?, 
        billing_postal_code = ?, 
        billing_country = ?, 
        updated_at = NOW() 
        WHERE id = ?");
    
    $result = $stmt->execute([
        $first_name,
        $last_name,
        $email,
        $phone,
        $street_address,
        $city,
        $state,
        $postal_code,
        $country,
        $billing_first_name,
        $billing_last_name,
        $billing_street_address,
        $billing_city,
        $billing_state,
        $billing_postal_code,
        $billing_country,
        $user_id
    ]);
    
    if ($result) {
        logActivity("Profile updated successfully");
        header('Location: profile.php?success=profile_updated');
    } else {
        logActivity("Profile update failed - database error");
        header('Location: profile.php?error=update_failed');
    }
} catch (PDOException $e) {
    logActivity("Database error during profile update: " . $e->getCode());
    header('Location: profile.php?error=system_error');
}

exit;
?>