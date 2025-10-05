<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$dbname = 'ecommerce';
$username = 'db_user';
$password = 'db_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die('Database connection failed');
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
</head>
<body>
    <form action="update_profile.php" method="POST">
        <h2>Personal Information</h2>
        
        <label for="first_name">First Name:</label>
        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required maxlength="50">
        
        <label for="last_name">Last Name:</label>
        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required maxlength="50">
        
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required maxlength="100">
        
        <label for="phone">Phone:</label>
        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" maxlength="20">
        
        <h2>Address Information</h2>
        
        <label for="address_line1">Address Line 1:</label>
        <input type="text" id="address_line1" name="address_line1" value="<?php echo htmlspecialchars($user['address_line1']); ?>" maxlength="100">
        
        <label for="address_line2">Address Line 2:</label>
        <input type="text" id="address_line2" name="address_line2" value="<?php echo htmlspecialchars($user['address_line2']); ?>" maxlength="100">
        
        <label for="city">City:</label>
        <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user['city']); ?>" maxlength="50">
        
        <label for="state">State:</label>
        <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($user['state']); ?>" maxlength="50">
        
        <label for="zip_code">ZIP Code:</label>
        <input type="text" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($user['zip_code']); ?>" maxlength="10">
        
        <label for="country">Country:</label>
        <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($user['country']); ?>" maxlength="50">
        
        <h2>Billing Information</h2>
        
        <label for="billing_same">
            <input type="checkbox" id="billing_same" name="billing_same" <?php echo $user['billing_same'] ? 'checked' : ''; ?>>
            Billing address same as shipping address
        </label>
        
        <div id="billing_fields">
            <label for="billing_address_line1">Billing Address Line 1:</label>
            <input type="text" id="billing_address_line1" name="billing_address_line1" value="<?php echo htmlspecialchars($user['billing_address_line1']); ?>" maxlength="100">
            
            <label for="billing_address_line2">Billing Address Line 2:</label>
            <input type="text" id="billing_address_line2" name="billing_address_line2" value="<?php echo htmlspecialchars($user['billing_address_line2']); ?>" maxlength="100">
            
            <label for="billing_city">Billing City:</label>
            <input type="text" id="billing_city" name="billing_city" value="<?php echo htmlspecialchars($user['billing_city']); ?>" maxlength="50">
            
            <label for="billing_state">Billing State:</label>
            <input type="text" id="billing_state" name="billing_state" value="<?php echo htmlspecialchars($user['billing_state']); ?>" maxlength="50">
            
            <label for="billing_zip_code">Billing ZIP Code:</label>
            <input type="text" id="billing_zip_code" name="billing_zip_code" value="<?php echo htmlspecialchars($user['billing_zip_code']); ?>" maxlength="10">
            
            <label for="billing_country">Billing Country:</label>
            <input type="text" id="billing_country" name="billing_country" value="<?php echo htmlspecialchars($user['billing_country']); ?>" maxlength="50">
        </div>
        
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        
        <button type="submit">Update Profile</button>
    </form>

    <script>
        document.getElementById('billing_same').addEventListener('change', function() {
            const billingFields = document.getElementById('billing_fields');
            if (this.checked) {
                billingFields.style.display = 'none';
            } else {
                billingFields.style.display = 'block';
            }
        });

        if (document.getElementById('billing_same').checked) {
            document.getElementById('billing_fields').style.display = 'none';
        }
    </script>
</body>
</html>


<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    die('Invalid CSRF token');
}

$host = 'localhost';
$dbname = 'ecommerce';
$username = 'db_user';
$password = 'db_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die('Database connection failed');
}

function validateInput($input, $maxLength = null, $required = false) {
    $input = trim($input);
    if ($required && empty($input)) {
        return false;
    }
    if ($maxLength && strlen($input) > $maxLength) {
        return false;
    }
    return $input;
}

function validateEmail($email) {
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if (strlen($email) > 100) {
        return false;
    }
    return $email;
}

function validatePhone($phone) {
    $phone = trim($phone);
    $phone = preg_replace('/[^0-9+\-\(\)\s]/', '', $phone);
    if (strlen($phone) > 20) {
        return false;
    }
    return $phone;
}

function validateZipCode($zip) {
    $zip = trim($zip);
    $zip = preg_replace('/[^0-9A-Za-z\-\s]/', '', $zip);
    if (strlen($zip) > 10) {
        return false;
    }
    return $zip;
}

$errors = [];

$first_name = validateInput($_POST['first_name'], 50, true);
if ($first_name === false) {
    $errors[] = 'Invalid first name';
}

$last_name = validateInput($_POST['last_name'], 50, true);
if ($last_name === false) {
    $errors[] = 'Invalid last name';
}

$email = validateEmail($_POST['email']);
if ($email === false) {
    $errors[] = 'Invalid email address';
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->execute([$email, $_SESSION['user_id']]);
if ($stmt->fetch()) {
    $errors[] = 'Email address already in use';
}

$phone = validatePhone($_POST['phone'] ?? '');
$address_line1 = validateInput($_POST['address_line1'], 100);
$address_line2 = validateInput($_POST['address_line2'], 100);
$city = validateInput($_POST['city'], 50);
$state = validateInput($_POST['state'], 50);
$zip_code = validateZipCode($_POST['zip_code'] ?? '');
$country = validateInput($_POST['country'], 50);

$billing_same = isset($_POST['billing_same']) ? 1 : 0;

$billing_address_line1 = '';
$billing_address_line2 = '';
$billing_city = '';
$billing_state = '';
$billing_zip_code = '';
$billing_country = '';

if (!$billing_same) {
    $billing_address_line1 = validateInput($_POST['billing_address_line1'], 100);
    $billing_address_line2 = validateInput($_POST['billing_address_line2'], 100);
    $billing_city = validateInput($_POST['billing_city'], 50);
    $billing_state = validateInput($_POST['billing_state'], 50);
    $billing_zip_code = validateZipCode($_POST['billing_zip_code'] ?? '');
    $billing_country = validateInput($_POST['billing_country'], 50);
}

if (!empty($errors)) {
    $_SESSION['profile_errors'] = $errors;
    header('Location: profile.php');
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE users SET 
        first_name = ?, 
        last_name = ?, 
        email = ?, 
        phone = ?, 
        address_line1 = ?, 
        address_line2 = ?, 
        city = ?, 
        state = ?, 
        zip_code = ?, 
        country = ?, 
        billing_same = ?, 
        billing_address_line1 = ?, 
        billing_address_line2 = ?, 
        billing_city = ?, 
        billing_state = ?, 
        billing_zip_code = ?, 
        billing_country = ? 
        WHERE id = ?");
    
    $stmt->execute([
        $first_name,
        $last_name,
        $email,
        $phone,
        $address_line1,
        $address_line2,
        $city,
        $state,
        $zip_code,
        $country,
        $billing_same,
        $billing_address_line1,
        $billing_address_line2,
        $billing_city,
        $billing_state,
        $billing_zip_code,
        $billing_country,
        $_SESSION['user_id']
    ]);
    
    $_SESSION['profile_success'] = 'Profile updated successfully';
    header('Location: profile.php');
    exit();
    
} catch (PDOException $e) {
    $_SESSION['profile_errors'] = ['Database error occurred'];
    header('Location: profile.php');
    exit();
}
?>


sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address_line1 VARCHAR(100),
    address_line2 VARCHAR(100),
    city VARCHAR(50),
    state VARCHAR(50),
    zip_code VARCHAR(10),
    country VARCHAR(50),
    billing_same TINYINT(1) DEFAULT 0,
    billing_address_line1 VARCHAR(100),
    billing_address_line2 VARCHAR(100),
    billing_city VARCHAR(50),
    billing_state VARCHAR(50),
    billing_zip_code VARCHAR(10),
    billing_country VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


<?php
session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['profile_errors'])) {
    foreach ($_SESSION['profile_errors'] as $error) {
        echo "<div class='error'>" . htmlspecialchars($error) . "</div>";
    }
    unset($_SESSION['profile_errors']);
}

if (isset($_SESSION['profile_success'])) {
    echo "<div class='success'>" . htmlspecialchars($_SESSION['profile_success']) . "</div>";
    unset($_SESSION['profile_success']);
}

include 'profile.php';
?>