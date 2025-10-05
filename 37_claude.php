<?php
<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
</head>
<body>
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
            <label for="phone">Phone:</label>
            <input type="tel" id="phone" name="phone" required>
        </div>
        
        <div>
            <label for="address">Address:</label>
            <input type="text" id="address" name="address" required>
        </div>
        
        <div>
            <label for="city">City:</label>
            <input type="text" id="city" name="city" required>
        </div>
        
        <div>
            <label for="state">State:</label>
            <input type="text" id="state" name="state" required>
        </div>
        
        <div>
            <label for="zip_code">Zip Code:</label>
            <input type="text" id="zip_code" name="zip_code" required>
        </div>
        
        <div>
            <label for="country">Country:</label>
            <input type="text" id="country" name="country" required>
        </div>
        
        <div>
            <label for="billing_address">Billing Address:</label>
            <input type="text" id="billing_address" name="billing_address" required>
        </div>
        
        <div>
            <label for="billing_city">Billing City:</label>
            <input type="text" id="billing_city" name="billing_city" required>
        </div>
        
        <div>
            <label for="billing_state">Billing State:</label>
            <input type="text" id="billing_state" name="billing_state" required>
        </div>
        
        <div>
            <label for="billing_zip_code">Billing Zip Code:</label>
            <input type="text" id="billing_zip_code" name="billing_zip_code" required>
        </div>
        
        <div>
            <label for="billing_country">Billing Country:</label>
            <input type="text" id="billing_country" name="billing_country" required>
        </div>
        
        <div>
            <input type="hidden" name="user_id" value="<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : ''; ?>">
            <input type="submit" value="Update Profile">
        </div>
    </form>
</body>
</html>


<?php
session_start();

function validateInput($data) {
    $errors = [];
    
    if (empty($data['first_name']) || strlen($data['first_name']) < 2 || strlen($data['first_name']) > 50) {
        $errors[] = "First name must be between 2 and 50 characters";
    }
    
    if (empty($data['last_name']) || strlen($data['last_name']) < 2 || strlen($data['last_name']) > 50) {
        $errors[] = "Last name must be between 2 and 50 characters";
    }
    
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email address is required";
    }
    
    if (empty($data['phone']) || !preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $data['phone'])) {
        $errors[] = "Valid phone number is required";
    }
    
    if (empty($data['address']) || strlen($data['address']) < 5 || strlen($data['address']) > 255) {
        $errors[] = "Address must be between 5 and 255 characters";
    }
    
    if (empty($data['city']) || strlen($data['city']) < 2 || strlen($data['city']) > 100) {
        $errors[] = "City must be between 2 and 100 characters";
    }
    
    if (empty($data['state']) || strlen($data['state']) < 2 || strlen($data['state']) > 100) {
        $errors[] = "State must be between 2 and 100 characters";
    }
    
    if (empty($data['zip_code']) || !preg_match('/^[0-9A-Za-z\s\-]{3,10}$/', $data['zip_code'])) {
        $errors[] = "Valid zip code is required";
    }
    
    if (empty($data['country']) || strlen($data['country']) < 2 || strlen($data['country']) > 100) {
        $errors[] = "Country must be between 2 and 100 characters";
    }
    
    if (empty($data['billing_address']) || strlen($data['billing_address']) < 5 || strlen($data['billing_address']) > 255) {
        $errors[] = "Billing address must be between 5 and 255 characters";
    }
    
    if (empty($data['billing_city']) || strlen($data['billing_city']) < 2 || strlen($data['billing_city']) > 100) {
        $errors[] = "Billing city must be between 2 and 100 characters";
    }
    
    if (empty($data['billing_state']) || strlen($data['billing_state']) < 2 || strlen($data['billing_state']) > 100) {
        $errors[] = "Billing state must be between 2 and 100 characters";
    }
    
    if (empty($data['billing_zip_code']) || !preg_match('/^[0-9A-Za-z\s\-]{3,10}$/', $data['billing_zip_code'])) {
        $errors[] = "Valid billing zip code is required";
    }
    
    if (empty($data['billing_country']) || strlen($data['billing_country']) < 2 || strlen($data['billing_country']) > 100) {
        $errors[] = "Billing country must be between 2 and 100 characters";
    }
    
    return $errors;
}

function sanitizeInput($data) {
    $sanitized = [];
    foreach ($data as $key => $value) {
        $sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
    return $sanitized;
}

function updateUserProfile($data) {
    $host = 'localhost';
    $dbname = 'your_database';
    $username = 'your_username';
    $password = 'your_password';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "UPDATE users SET 
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone = :phone,
                address = :address,
                city = :city,
                state = :state,
                zip_code = :zip_code,
                country = :country,
                billing_address = :billing_address,
                billing_city = :billing_city,
                billing_state = :billing_state,
                billing_zip_code = :billing_zip_code,
                billing_country = :billing_country,
                updated_at = NOW()
                WHERE id = :user_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':address' => $data['address'],
            ':city' => $data['city'],
            ':state' => $data['state'],
            ':zip_code' => $data['zip_code'],
            ':country' => $data['country'],
            ':billing_address' => $data['billing_address'],
            ':billing_city' => $data['billing_city'],
            ':billing_state' => $data['billing_state'],
            ':billing_zip_code' => $data['billing_zip_code'],
            ':billing_country' => $data['billing_country'],
            ':user_id' => $data['user_id']
        ]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id']) || empty($_POST['user_id'])) {
        http_response_code(403);
        die('Unauthorized access');
    }
    
    $input_data = sanitizeInput($_POST);
    $validation_errors = validateInput($input_data);
    
    if (!empty($validation_errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $validation_errors]);
        exit;
    }
    
    if (updateUserProfile($input_data)) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
} else {
    http_response_code(405);
    die('Method not allowed');
}
?>


sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    zip_code VARCHAR(10) NOT NULL,
    country VARCHAR(100) NOT NULL,
    billing_address VARCHAR(255) NOT NULL,
    billing_city VARCHAR(100) NOT NULL,
    billing_state VARCHAR(100) NOT NULL,
    billing_zip_code VARCHAR(10) NOT NULL,
    billing_country VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
?>