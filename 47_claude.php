<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['error_message']);

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
    $error_message = 'Database connection failed';
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
    <h1>Update Profile</h1>
    
    <?php if ($error_message): ?>
        <div><?php echo htmlspecialchars($error_message); ?></div>
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
            <input type="text" id="credit_card_number" name="credit_card_number" value="" placeholder="Enter new card number">
        </div>
        
        <div>
            <label for="credit_card_expiry_date">Credit Card Expiry Date:</label>
            <input type="text" id="credit_card_expiry_date" name="credit_card_expiry_date" value="" placeholder="MM/YY">
        </div>
        
        <button type="submit">Update Profile</button>
    </form>
</body>
</html>


<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile_form.php');
    exit();
}

function validateInput($data) {
    $errors = [];
    
    if (empty(trim($data['first_name'])) || strlen(trim($data['first_name'])) > 50) {
        $errors[] = 'Invalid first name';
    }
    
    if (empty(trim($data['last_name'])) || strlen(trim($data['last_name'])) > 50) {
        $errors[] = 'Invalid last name';
    }
    
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 100) {
        $errors[] = 'Invalid email address';
    }
    
    if (empty(trim($data['phone_number'])) || !preg_match('/^[\+]?[0-9\s\-\(\)]{10,15}$/', $data['phone_number'])) {
        $errors[] = 'Invalid phone number';
    }
    
    if (empty(trim($data['street_address'])) || strlen(trim($data['street_address'])) > 255) {
        $errors[] = 'Invalid street address';
    }
    
    if (empty(trim($data['city'])) || strlen(trim($data['city'])) > 100) {
        $errors[] = 'Invalid city';
    }
    
    if (empty(trim($data['zip_code'])) || !preg_match('/^[0-9A-Za-z\s\-]{3,10}$/', $data['zip_code'])) {
        $errors[] = 'Invalid ZIP code';
    }
    
    if (!empty($data['credit_card_number'])) {
        $cc_number = preg_replace('/\s+/', '', $data['credit_card_number']);
        if (!preg_match('/^[0-9]{13,19}$/', $cc_number)) {
            $errors[] = 'Invalid credit card number';
        }
    }
    
    if (!empty($data['credit_card_expiry_date'])) {
        if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $data['credit_card_expiry_date'])) {
            $errors[] = 'Invalid expiry date format (MM/YY)';
        } else {
            list($month, $year) = explode('/', $data['credit_card_expiry_date']);
            $current_year = date('y');
            $current_month = date('m');
            
            if ($year < $current_year || ($year == $current_year && $month < $current_month)) {
                $errors[] = 'Credit card has expired';
            }
        }
    }
    
    return $errors;
}

$errors = validateInput($_POST);

if (!empty($errors)) {
    $_SESSION['error_message'] = implode(', ', $errors);
    header('Location: profile_form.php');
    exit();
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=db_ecommerce;charset=utf8mb4', 'username', 'password', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    $stmt = $pdo->prepare("SELECT email FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$_POST['email'], $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        $_SESSION['error_message'] = 'Email address already exists';
        header('Location: profile_form.php');
        exit();
    }
    
    $pdo->beginTransaction();
    
    $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ?, street_address = ?, city = ?, zip_code = ?";
    $params = [
        trim($_POST['first_name']),
        trim($_POST['last_name']),
        $_POST['email'],
        $_POST['phone_number'],
        trim($_POST['street_address']),
        trim($_POST['city']),
        $_POST['zip_code']
    ];
    
    if (!empty($_POST['credit_card_number'])) {
        $encrypted_cc = password_hash($_POST['credit_card_number'], PASSWORD_DEFAULT);
        $sql .= ", credit_card_number = ?, credit_card_expiry_date = ?";
        $params[] = $encrypted_cc;
        $params[] = $_POST['credit_card_expiry_date'];
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result && $stmt->rowCount() >= 0) {
        $pdo->commit();
        header('Location: profile_success.php');
        exit();
    } else {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Failed to update profile';
        header('Location: profile_form.php');
        exit();
    }
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = 'Database error occurred';
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Updated Successfully</title>
</head>
<body>
    <h1>Success!</h1>
    <p>Your profile has been updated successfully.</p>
    <a href="profile_form.php">Back to Profile</a>
</body>
</html>
?>