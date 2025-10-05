<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=db_ecommerce;charset=utf8mb4', 'username', 'password', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
} catch (PDOException $e) {
    die('Database connection failed');
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
    <h1>Update Profile</h1>
    
    <?php if ($error_message): ?>
        <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <form action="update_profile.php" method="POST">
        <div>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
        </div>
        
        <div>
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
        </div>
        
        <div>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
        </div>
        
        <div>
            <label for="phone_number">Phone Number:</label>
            <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" required>
        </div>
        
        <div>
            <label for="street_address">Street Address:</label>
            <input type="text" id="street_address" name="street_address" value="<?php echo htmlspecialchars($user['street_address'] ?? ''); ?>" required>
        </div>
        
        <div>
            <label for="city">City:</label>
            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" required>
        </div>
        
        <div>
            <label for="zip_code">ZIP Code:</label>
            <input type="text" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>" required>
        </div>
        
        <div>
            <label for="credit_card_number">Credit Card Number:</label>
            <input type="text" id="credit_card_number" name="credit_card_number" value="" placeholder="Enter credit card number" required>
        </div>
        
        <div>
            <label for="credit_card_expiry_date">Credit Card Expiry Date:</label>
            <input type="month" id="credit_card_expiry_date" name="credit_card_expiry_date" value="<?php echo htmlspecialchars($user['credit_card_expiry_date'] ?? ''); ?>" required>
        </div>
        
        <button type="submit">Update Profile</button>
    </form>
</body>
</html>


<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit();
}

function validateInput($data) {
    $errors = [];
    
    if (empty($data['first_name']) || strlen($data['first_name']) > 50) {
        $errors[] = 'First name is required and must be less than 50 characters';
    }
    
    if (empty($data['last_name']) || strlen($data['last_name']) > 50) {
        $errors[] = 'Last name is required and must be less than 50 characters';
    }
    
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 100) {
        $errors[] = 'Valid email is required and must be less than 100 characters';
    }
    
    if (empty($data['phone_number']) || !preg_match('/^\+?[\d\s\-\(\)]{10,20}$/', $data['phone_number'])) {
        $errors[] = 'Valid phone number is required';
    }
    
    if (empty($data['street_address']) || strlen($data['street_address']) > 255) {
        $errors[] = 'Street address is required and must be less than 255 characters';
    }
    
    if (empty($data['city']) || strlen($data['city']) > 100) {
        $errors[] = 'City is required and must be less than 100 characters';
    }
    
    if (empty($data['zip_code']) || !preg_match('/^\d{5}(-\d{4})?$/', $data['zip_code'])) {
        $errors[] = 'Valid ZIP code is required (12345 or 12345-6789)';
    }
    
    if (empty($data['credit_card_number']) || !preg_match('/^\d{13,19}$/', preg_replace('/\s/', '', $data['credit_card_number']))) {
        $errors[] = 'Valid credit card number is required';
    }
    
    if (empty($data['credit_card_expiry_date']) || !preg_match('/^\d{4}-\d{2}$/', $data['credit_card_expiry_date'])) {
        $errors[] = 'Valid expiry date is required';
    } else {
        $expiry = DateTime::createFromFormat('Y-m', $data['credit_card_expiry_date']);
        $now = new DateTime();
        $now->setDate($now->format('Y'), $now->format('m'), 1);
        if ($expiry < $now) {
            $errors[] = 'Credit card expiry date must be in the future';
        }
    }
    
    return $errors;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=db_ecommerce;charset=utf8mb4', 'username', 'password', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    $input_data = [
        'first_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name']),
        'email' => trim($_POST['email']),
        'phone_number' => trim($_POST['phone_number']),
        'street_address' => trim($_POST['street_address']),
        'city' => trim($_POST['city']),
        'zip_code' => trim($_POST['zip_code']),
        'credit_card_number' => preg_replace('/\s/', '', $_POST['credit_card_number']),
        'credit_card_expiry_date' => trim($_POST['credit_card_expiry_date'])
    ];
    
    $validation_errors = validateInput($input_data);
    
    if (!empty($validation_errors)) {
        $_SESSION['error_message'] = implode('; ', $validation_errors);
        header('Location: profile.php');
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$input_data['email'], $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        $_SESSION['error_message'] = 'Email address is already in use by another account';
        header('Location: profile.php');
        exit();
    }
    
    $encrypted_cc = openssl_encrypt($input_data['credit_card_number'], 'AES-256-CBC', 'your-encryption-key', 0, '1234567890123456');
    
    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ?, street_address = ?, city = ?, zip_code = ?, credit_card_number = ?, credit_card_expiry_date = ?, updated_at = NOW() WHERE id = ?");
    
    $result = $stmt->execute([
        $input_data['first_name'],
        $input_data['last_name'],
        $input_data['email'],
        $input_data['phone_number'],
        $input_data['street_address'],
        $input_data['city'],
        $input_data['zip_code'],
        $encrypted_cc,
        $input_data['credit_card_expiry_date'],
        $_SESSION['user_id']
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        header('Location: profile_success.php');
        exit();
    } else {
        $_SESSION['error_message'] = 'No changes were made to your profile';
        header('Location: profile.php');
        exit();
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database error occurred. Please try again later.';
    header('Location: profile.php');
    exit();
} catch (Exception $e) {
    $_SESSION['error_message'] = 'An error occurred. Please try again later.';
    header('Location: profile.php');
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Updated Successfully</title>
</head>
<body>
    <h1>Profile Updated Successfully</h1>
    
    <div>
        <p>Your profile has been updated successfully!</p>
        <p><a href="profile.php">View Profile</a></p>
        <p><a href="dashboard.php">Return to Dashboard</a></p>
    </div>
</body>
</html>
?>