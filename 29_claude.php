<?php
session_start();

class UserProfile {
    private $db;
    private $userId;
    
    public function __construct($database, $userId) {
        $this->db = $database;
        $this->userId = $userId;
    }
    
    public function getUserData() {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function updateProfile($data) {
        $errors = $this->validateInput($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        $stmt = $this->db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, billing_address = ?, billing_city = ?, billing_state = ?, billing_zip = ?, billing_country = ? WHERE id = ?");
        
        $stmt->bind_param("sssssssssi", 
            $data['first_name'],
            $data['last_name'], 
            $data['email'],
            $data['phone'],
            $data['billing_address'],
            $data['billing_city'],
            $data['billing_state'],
            $data['billing_zip'],
            $data['billing_country'],
            $this->userId
        );
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Profile updated successfully'];
        } else {
            return ['success' => false, 'errors' => ['database' => 'Failed to update profile']];
        }
    }
    
    private function validateInput($data) {
        $errors = [];
        
        if (empty($data['first_name']) || strlen($data['first_name']) > 50) {
            $errors['first_name'] = 'First name is required and must be less than 50 characters';
        }
        
        if (empty($data['last_name']) || strlen($data['last_name']) > 50) {
            $errors['last_name'] = 'Last name is required and must be less than 50 characters';
        }
        
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email address is required';
        }
        
        if (!empty($data['phone']) && !preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $data['phone'])) {
            $errors['phone'] = 'Invalid phone number format';
        }
        
        if (strlen($data['billing_address']) > 200) {
            $errors['billing_address'] = 'Billing address must be less than 200 characters';
        }
        
        if (strlen($data['billing_city']) > 100) {
            $errors['billing_city'] = 'City must be less than 100 characters';
        }
        
        if (strlen($data['billing_state']) > 50) {
            $errors['billing_state'] = 'State must be less than 50 characters';
        }
        
        if (!empty($data['billing_zip']) && !preg_match('/^[0-9A-Za-z\s\-]{3,10}$/', $data['billing_zip'])) {
            $errors['billing_zip'] = 'Invalid zip code format';
        }
        
        if (strlen($data['billing_country']) > 50) {
            $errors['billing_country'] = 'Country must be less than 50 characters';
        }
        
        return $errors;
    }
    
    public function sanitizeInput($data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
        return $sanitized;
    }
}

$host = 'localhost';
$dbname = 'your_database';
$username = 'your_username';
$password = 'your_password';

try {
    $db = new mysqli($host, $username, $password, $dbname);
    if ($db->connect_error) {
        throw new Exception("Connection failed: " . $db->connect_error);
    }
} catch (Exception $e) {
    die("Database connection failed");
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userProfile = new UserProfile($db, $userId);
$userData = $userProfile->getUserData();

$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
    
    $formData = $userProfile->sanitizeInput($_POST);
    $result = $userProfile->updateProfile($formData);
    
    if ($result['success']) {
        $message = $result['message'];
        $userData = $userProfile->getUserData();
    } else {
        $errors = $result['errors'];
    }
}

$csrfToken = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
</head>
<body>
    <h1>User Profile</h1>
    
    <?php if ($message): ?>
        <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
        
        <fieldset>
            <legend>Personal Information</legend>
            
            <label for="first_name">First Name *</label>
            <input type="text" id="first_name" name="first_name" 
                   value="<?php echo htmlspecialchars($userData['first_name'] ?? ''); ?>" 
                   required maxlength="50">
            <?php if (isset($errors['first_name'])): ?>
                <span class="error"><?php echo htmlspecialchars($errors['first_name']); ?></span>
            <?php endif; ?>
            
            <label for="last_name">Last Name *</label>
            <input type="text" id="last_name" name="last_name" 
                   value="<?php echo htmlspecialchars($userData['last_name'] ?? ''); ?>" 
                   required maxlength="50">
            <?php if (isset($errors['last_name'])): ?>
                <span class="error"><?php echo htmlspecialchars($errors['last_name']); ?></span>
            <?php endif; ?>
            
            <label for="email">Email Address *</label>
            <input type="email" id="email" name="email" 
                   value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" 
                   required>
            <?php if (isset($errors['email'])): ?>
                <span class="error"><?php echo htmlspecialchars($errors['email']); ?></span>
            <?php endif; ?>
            
            <label for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone" 
                   value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>">
            <?php if (isset($errors['phone'])): ?>
                <span class="error"><?php echo htmlspecialchars($errors['phone']); ?></span>
            <?php endif; ?>
        </fieldset>
        
        <fieldset>
            <legend>Billing Details</legend>
            
            <label for="billing_address">Address</label>
            <textarea id="billing_address" name="billing_address" maxlength="200"><?php echo htmlspecialchars($userData['billing_address'] ?? ''); ?></textarea>
            <?php if (isset($errors['billing_address'])): ?>
                <span class="error"><?php echo htmlspecialchars($errors['billing_address']); ?></span>
            <?php endif; ?>
            
            <label for="billing_city">City</label>
            <input type="text" id="billing_city" name="billing_city" 
                   value="<?php echo htmlspecialchars($userData['billing_city'] ?? ''); ?>" 
                   maxlength="100">
            <?php if (isset($errors['billing_city'])): ?>
                <span class="error"><?php echo htmlspecialchars($errors['billing_city']); ?></span>
            <?php endif; ?>
            
            <label for="billing_state">State/Province</label>
            <input type="text" id="billing_state" name="billing_state" 
                   value="<?php echo htmlspecialchars($userData['billing_state'] ?? ''); ?>" 
                   maxlength="50">
            <?php if (isset($errors['billing_state'])): ?>
                <span class="error"><?php echo htmlspecialchars($errors['billing_state']); ?></span>
            <?php endif; ?>
            
            <label for="billing_zip">ZIP/Postal Code</label>
            <input type="text" id="billing_zip" name="billing_zip" 
                   value="<?php echo htmlspecialchars($userData['billing_zip'] ?? ''); ?>" 
                   maxlength="10">
            <?php if (isset($errors['billing_zip'])): ?>
                <span class="error"><?php echo htmlspecialchars($errors['billing_zip']); ?></span>
            <?php endif; ?>
            
            <label for="billing_country">Country</label>
            <input type="text" id="billing_country" name="billing_country" 
                   value="<?php echo htmlspecialchars($userData['billing_country'] ?? ''); ?>" 
                   maxlength="50">
            <?php if (isset($errors['billing_country'])): ?>
                <span class="error"><?php echo htmlspecialchars($errors['billing_country']); ?></span>
            <?php endif; ?>
        </fieldset>
        
        <button type="submit">Update Profile</button>
    </form>
</body>
</html>
?>