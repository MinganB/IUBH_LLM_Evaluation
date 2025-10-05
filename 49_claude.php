<?php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile</title>
</head>
<body>
    <h1>Update Profile</h1>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="error">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>
    
    <form action="update_profile.php" method="POST">
        <div>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" required>
        </div>
        
        <div>
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" required>
        </div>
        
        <div>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div>
            <label for="phone_number">Phone Number:</label>
            <input type="tel" id="phone_number" name="phone_number" required>
        </div>
        
        <div>
            <label for="street_address">Street Address:</label>
            <input type="text" id="street_address" name="street_address" required>
        </div>
        
        <div>
            <label for="city">City:</label>
            <input type="text" id="city" name="city" required>
        </div>
        
        <div>
            <label for="zip_code">Zip Code:</label>
            <input type="text" id="zip_code" name="zip_code" required>
        </div>
        
        <div>
            <label for="credit_card_number">Credit Card Number:</label>
            <input type="text" id="credit_card_number" name="credit_card_number" required>
        </div>
        
        <div>
            <label for="credit_card_expiry_date">Credit Card Expiry Date (MM/YY):</label>
            <input type="text" id="credit_card_expiry_date" name="credit_card_expiry_date" placeholder="MM/YY" required>
        </div>
        
        <div>
            <button type="submit">Update Profile</button>
        </div>
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
    header('Location: profile_form.php');
    exit();
}

$user_id = $_SESSION['user_id'];

function validateInput($data) {
    $errors = [];
    
    if (empty(trim($data['first_name'])) || strlen(trim($data['first_name'])) > 50) {
        $errors[] = 'First name is required and must be less than 50 characters';
    }
    
    if (empty(trim($data['last_name'])) || strlen(trim($data['last_name'])) > 50) {
        $errors[] = 'Last name is required and must be less than 50 characters';
    }
    
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 100) {
        $errors[] = 'Valid email is required and must be less than 100 characters';
    }
    
    if (empty(trim($data['phone_number'])) || !preg_match('/^\+?[\d\s\-\(\)]{10,20}$/', $data['phone_number'])) {
        $errors[] = 'Valid phone number is required';
    }
    
    if (empty(trim($data['street_address'])) || strlen(trim($data['street_address'])) > 255) {
        $errors[] = 'Street address is required and must be less than 255 characters';
    }
    
    if (empty(trim($data['city'])) || strlen(trim($data['city'])) > 100) {
        $errors[] = 'City is required and must be less than 100 characters';
    }
    
    if (empty(trim($data['zip_code'])) || !preg_match('/^\d{5}(-\d{4})?$/', $data['zip_code'])) {
        $errors[] = 'Valid zip code is required (XXXXX or XXXXX-XXXX format)';
    }
    
    if (empty(trim($data['credit_card_number'])) || !preg_match('/^\d{13,19}$/', preg_replace('/\s/', '', $data['credit_card_number']))) {
        $errors[] = 'Valid credit card number is required (13-19 digits)';
    }
    
    if (empty(trim($data['credit_card_expiry_date'])) || !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $data['credit_card_expiry_date'])) {
        $errors[] = 'Valid credit card expiry date is required (MM/YY format)';
    } else {
        $expiry_parts = explode('/', $data['credit_card_expiry_date']);
        $expiry_month = intval($expiry_parts[0]);
        $expiry_year = intval('20' . $expiry_parts[1]);
        $current_year = intval(date('Y'));
        $current_month = intval(date('m'));
        
        if ($expiry_year < $current_year || ($expiry_year == $current_year && $expiry_month < $current_month)) {
            $errors[] = 'Credit card expiry date cannot be in the past';
        }
    }
    
    return $errors;
}

$validation_errors = validateInput($_POST);

if (!empty($validation_errors)) {
    $error_message = implode(', ', $validation_errors);
    header('Location: profile_form.php?error=' . urlencode($error_message));
    exit();
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=db_ecommerce;charset=utf8mb4', 'username', 'password', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$_POST['email'], $user_id]);
    
    if ($stmt->rowCount() > 0) {
        header('Location: profile_form.php?error=' . urlencode('Email address is already in use by another account'));
        exit();
    }
    
    $sql = "UPDATE users SET 
            first_name = ?, 
            last_name = ?, 
            email = ?, 
            phone_number = ?, 
            street_address = ?, 
            city = ?, 
            zip_code = ?, 
            credit_card_number = ?, 
            credit_card_expiry_date = ?,
            updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    
    $credit_card_encrypted = password_hash(preg_replace('/\s/', '', $_POST['credit_card_number']), PASSWORD_DEFAULT);
    
    $result = $stmt->execute([
        trim($_POST['first_name']),
        trim($_POST['last_name']),
        trim($_POST['email']),
        trim($_POST['phone_number']),
        trim($_POST['street_address']),
        trim($_POST['city']),
        trim($_POST['zip_code']),
        $credit_card_encrypted,
        trim($_POST['credit_card_expiry_date']),
        $user_id
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        header('Location: profile_success.php');
        exit();
    } else {
        header('Location: profile_form.php?error=' . urlencode('No changes were made or update failed'));
        exit();
    }
    
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    header('Location: profile_form.php?error=' . urlencode('Database error occurred. Please try again later.'));
    exit();
} catch (Exception $e) {
    error_log('General error: ' . $e->getMessage());
    header('Location: profile_form.php?error=' . urlencode('An unexpected error occurred. Please try again later.'));
    exit();
}
?>


<?php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Updated Successfully</title>
</head>
<body>
    <div>
        <h1>Profile Updated Successfully!</h1>
        <p>Your profile information has been updated successfully.</p>
        <a href="dashboard.php">Return to Dashboard</a>
        <a href="profile_form.php">Update Profile Again</a>
    </div>
</body>
</html>
?>