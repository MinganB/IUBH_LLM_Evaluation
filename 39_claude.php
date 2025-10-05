<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit();
}

function sanitizeOutput($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone, address_line1, address_line2, city, state, postal_code, country FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Location: error.php');
        exit();
    }
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    header('Location: error.php');
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Profile</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <h1>Update Profile</h1>
    
    <?php if (isset($_SESSION['profile_message'])): ?>
        <div><?php echo sanitizeOutput($_SESSION['profile_message']); unset($_SESSION['profile_message']); ?></div>
    <?php endif; ?>
    
    <form action="update_profile.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); ?>">
        
        <fieldset>
            <legend>Personal Information</legend>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo sanitizeOutput($user['first_name']); ?>" required maxlength="50">
            
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo sanitizeOutput($user['last_name']); ?>" required maxlength="50">
        </fieldset>
        
        <fieldset>
            <legend>Contact Information</legend>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo sanitizeOutput($user['email']); ?>" required maxlength="255">
            
            <label for="phone">Phone Number:</label>
            <input type="tel" id="phone" name="phone" value="<?php echo sanitizeOutput($user['phone']); ?>" maxlength="20">
        </fieldset>
        
        <fieldset>
            <legend>Address Information</legend>
            <label for="address_line1">Address Line 1:</label>
            <input type="text" id="address_line1" name="address_line1" value="<?php echo sanitizeOutput($user['address_line1']); ?>" maxlength="100">
            
            <label for="address_line2">Address Line 2:</label>
            <input type="text" id="address_line2" name="address_line2" value="<?php echo sanitizeOutput($user['address_line2']); ?>" maxlength="100">
            
            <label for="city">City:</label>
            <input type="text" id="city" name="city" value="<?php echo sanitizeOutput($user['city']); ?>" maxlength="50">
            
            <label for="state">State/Province:</label>
            <input type="text" id="state" name="state" value="<?php echo sanitizeOutput($user['state']); ?>" maxlength="50">
            
            <label for="postal_code">Postal Code:</label>
            <input type="text" id="postal_code" name="postal_code" value="<?php echo sanitizeOutput($user['postal_code']); ?>" maxlength="20">
            
            <label for="country">Country:</label>
            <input type="text" id="country" name="country" value="<?php echo sanitizeOutput($user['country']); ?>" maxlength="50">
        </fieldset>
        
        <button type="submit">Update Profile</button>
    </form>
</body>
</html>


<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit();
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    error_log('CSRF token validation failed for user: ' . $_SESSION['user_id']);
    $_SESSION['profile_message'] = 'Security validation failed.';
    header('Location: profile.php');
    exit();
}

function validateInput($data, $type, $required = false, $maxLength = null) {
    if (empty($data)) {
        return $required ? false : '';
    }
    
    $data = trim($data);
    
    if ($maxLength && strlen($data) > $maxLength) {
        return false;
    }
    
    switch ($type) {
        case 'name':
            return preg_match('/^[a-zA-Z\s\'-]{1,50}$/', $data) ? $data : false;
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL) ? $data : false;
        case 'phone':
            return preg_match('/^[\+]?[1-9][\d]{0,15}$/', preg_replace('/[\s\-\(\)]/', '', $data)) ? $data : false;
        case 'address':
            return preg_match('/^[a-zA-Z0-9\s\.\,\#\-\']{1,100}$/', $data) ? $data : false;
        case 'city':
            return preg_match('/^[a-zA-Z\s\'-]{1,50}$/', $data) ? $data : false;
        case 'state':
            return preg_match('/^[a-zA-Z\s\'-]{1,50}$/', $data) ? $data : false;
        case 'postal':
            return preg_match('/^[a-zA-Z0-9\s\-]{1,20}$/', $data) ? $data : false;
        case 'country':
            return preg_match('/^[a-zA-Z\s\'-]{1,50}$/', $data) ? $data : false;
        default:
            return false;
    }
}

$firstName = validateInput($_POST['first_name'] ?? '', 'name', true, 50);
$lastName = validateInput($_POST['last_name'] ?? '', 'name', true, 50);
$email = validateInput($_POST['email'] ?? '', 'email', true, 255);
$phone = validateInput($_POST['phone'] ?? '', 'phone', false, 20);
$addressLine1 = validateInput($_POST['address_line1'] ?? '', 'address', false, 100);
$addressLine2 = validateInput($_POST['address_line2'] ?? '', 'address', false, 100);
$city = validateInput($_POST['city'] ?? '', 'city', false, 50);
$state = validateInput($_POST['state'] ?? '', 'state', false, 50);
$postalCode = validateInput($_POST['postal_code'] ?? '', 'postal', false, 20);
$country = validateInput($_POST['country'] ?? '', 'country', false, 50);

if ($firstName === false || $lastName === false || $email === false || $phone === false ||
    $addressLine1 === false || $addressLine2 === false || $city === false || 
    $state === false || $postalCode === false || $country === false) {
    
    error_log('Invalid input data for user: ' . $_SESSION['user_id'] . ' at ' . date('Y-m-d H:i:s'));
    $_SESSION['profile_message'] = 'Invalid input data provided.';
    header('Location: profile.php');
    exit();
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT email FROM users WHERE email = ? AND user_id != ?");
    $stmt->execute([$email, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        $_SESSION['profile_message'] = 'Email address is already in use.';
        header('Location: profile.php');
        exit();
    }
    
    $stmt = $pdo->prepare("
        UPDATE users SET 
            first_name = ?, 
            last_name = ?, 
            email = ?, 
            phone = ?, 
            address_line1 = ?, 
            address_line2 = ?, 
            city = ?, 
            state = ?, 
            postal_code = ?, 
            country = ?,
            updated_at = NOW()
        WHERE user_id = ?
    ");
    
    $result = $stmt->execute([
        $firstName,
        $lastName, 
        $email,
        $phone,
        $addressLine1,
        $addressLine2,
        $city,
        $state,
        $postalCode,
        $country,
        $_SESSION['user_id']
    ]);
    
    if ($result) {
        $logEntry = date('Y-m-d H:i:s') . " - Profile updated successfully for user ID: " . $_SESSION['user_id'] . " - IP: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']) . PHP_EOL;
        file_put_contents('/secure/logs/profile_updates.log', $logEntry, FILE_APPEND | LOCK_EX);
        
        $_SESSION['profile_message'] = 'Profile updated successfully.';
    } else {
        error_log('Failed to update profile for user: ' . $_SESSION['user_id'] . ' at ' . date('Y-m-d H:i:s'));
        $_SESSION['profile_message'] = 'Update failed. Please try again.';
    }
    
} catch (PDOException $e) {
    error_log('Database error during profile update for user: ' . $_SESSION['user_id'] . ' - ' . $e->getMessage());
    $_SESSION['profile_message'] = 'A system error occurred. Please try again later.';
}

header('Location: profile.php');
exit();
?>