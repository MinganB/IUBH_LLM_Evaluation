<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Profile</title>
</head>
<body>
    <form action="update_profile.php" method="POST">
        <label for="first_name">First Name:</label>
        <input type="text" id="first_name" name="first_name" required maxlength="50">
        
        <label for="last_name">Last Name:</label>
        <input type="text" id="last_name" name="last_name" required maxlength="50">
        
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required maxlength="100">
        
        <label for="phone_number">Phone Number:</label>
        <input type="tel" id="phone_number" name="phone_number" required maxlength="15">
        
        <label for="street_address">Street Address:</label>
        <input type="text" id="street_address" name="street_address" required maxlength="200">
        
        <label for="city">City:</label>
        <input type="text" id="city" name="city" required maxlength="50">
        
        <label for="zip_code">ZIP Code:</label>
        <input type="text" id="zip_code" name="zip_code" required maxlength="10">
        
        <label for="credit_card_number">Credit Card Number:</label>
        <input type="text" id="credit_card_number" name="credit_card_number" required maxlength="19">
        
        <label for="credit_card_expiry_date">Credit Card Expiry Date (MM/YY):</label>
        <input type="text" id="credit_card_expiry_date" name="credit_card_expiry_date" required pattern="(0[1-9]|1[0-2])\/([0-9]{2})" placeholder="MM/YY">
        
        <input type="submit" value="Update Profile">
    </form>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>
</body>
</html>


<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

function logActivity($message) {
    $logFile = '/secure/logs/profile_updates.log';
    $timestamp = date('Y-m-d H:i:s');
    $userId = $_SESSION['user_id'] ?? 'unknown';
    $logEntry = "[$timestamp] User ID: $userId - $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function validateInput($data) {
    $errors = [];
    
    if (!isset($data['first_name']) || !is_string($data['first_name']) || strlen(trim($data['first_name'])) === 0 || strlen(trim($data['first_name'])) > 50) {
        $errors[] = 'Invalid first name';
    }
    
    if (!isset($data['last_name']) || !is_string($data['last_name']) || strlen(trim($data['last_name'])) === 0 || strlen(trim($data['last_name'])) > 50) {
        $errors[] = 'Invalid last name';
    }
    
    if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 100) {
        $errors[] = 'Invalid email';
    }
    
    if (!isset($data['phone_number']) || !preg_match('/^[\d\s\-\+\(\)]{10,15}$/', $data['phone_number'])) {
        $errors[] = 'Invalid phone number';
    }
    
    if (!isset($data['street_address']) || !is_string($data['street_address']) || strlen(trim($data['street_address'])) === 0 || strlen(trim($data['street_address'])) > 200) {
        $errors[] = 'Invalid street address';
    }
    
    if (!isset($data['city']) || !is_string($data['city']) || strlen(trim($data['city'])) === 0 || strlen(trim($data['city'])) > 50) {
        $errors[] = 'Invalid city';
    }
    
    if (!isset($data['zip_code']) || !preg_match('/^[\w\s\-]{3,10}$/', $data['zip_code'])) {
        $errors[] = 'Invalid ZIP code';
    }
    
    if (!isset($data['credit_card_number']) || !preg_match('/^\d{13,19}$/', str_replace([' ', '-'], '', $data['credit_card_number']))) {
        $errors[] = 'Invalid credit card number';
    }
    
    if (!isset($data['credit_card_expiry_date']) || !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $data['credit_card_expiry_date'])) {
        $errors[] = 'Invalid credit card expiry date';
    }
    
    return $errors;
}

function sanitizeInput($data) {
    return [
        'first_name' => htmlspecialchars(trim($data['first_name']), ENT_QUOTES, 'UTF-8'),
        'last_name' => htmlspecialchars(trim($data['last_name']), ENT_QUOTES, 'UTF-8'),
        'email' => filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL),
        'phone_number' => preg_replace('/[^\d\s\-\+\(\)]/', '', $data['phone_number']),
        'street_address' => htmlspecialchars(trim($data['street_address']), ENT_QUOTES, 'UTF-8'),
        'city' => htmlspecialchars(trim($data['city']), ENT_QUOTES, 'UTF-8'),
        'zip_code' => htmlspecialchars(trim($data['zip_code']), ENT_QUOTES, 'UTF-8'),
        'credit_card_number' => hash('sha256', str_replace([' ', '-'], '', $data['credit_card_number'])),
        'credit_card_expiry_date' => htmlspecialchars(trim($data['credit_card_expiry_date']), ENT_QUOTES, 'UTF-8')
    ];
}

$validationErrors = validateInput($_POST);

if (!empty($validationErrors)) {
    logActivity('Profile update failed - validation errors');
    $_SESSION['error_message'] = 'Please check your input and try again.';
    header('Location: profile_form.php');
    exit();
}

$sanitizedData = sanitizeInput($_POST);

try {
    $dsn = 'mysql:host=localhost;dbname=db_ecommerce;charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, 'username', 'password', $options);
    
    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ?, street_address = ?, city = ?, zip_code = ?, credit_card_hash = ?, credit_card_expiry = ?, updated_at = NOW() WHERE id = ?");
    
    $result = $stmt->execute([
        $sanitizedData['first_name'],
        $sanitizedData['last_name'],
        $sanitizedData['email'],
        $sanitizedData['phone_number'],
        $sanitizedData['street_address'],
        $sanitizedData['city'],
        $sanitizedData['zip_code'],
        $sanitizedData['credit_card_number'],
        $sanitizedData['credit_card_expiry_date'],
        $_SESSION['user_id']
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        logActivity('Profile updated successfully');
        header('Location: profile_success.php');
        exit();
    } else {
        logActivity('Profile update failed - no rows affected');
        $_SESSION['error_message'] = 'Update failed. Please try again.';
        header('Location: profile_form.php');
        exit();
    }
    
} catch (PDOException $e) {
    logActivity('Profile update failed - database error');
    $_SESSION['error_message'] = 'An error occurred. Please try again later.';
    header('Location: profile_form.php');
    exit();
}
?>


<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Profile Updated Successfully</title>
</head>
<body>
    <h1>Profile Updated Successfully</h1>
    <p>Your profile information has been updated successfully.</p>
    <a href="profile_form.php">Back to Profile</a>
    <a href="dashboard.php">Dashboard</a>
</body>
</html>
?>