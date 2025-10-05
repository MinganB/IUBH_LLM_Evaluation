<?php
session_start();

class UserProfile {
    private $db;
    private $logFile;
    
    public function __construct($database, $logFile = 'profile_updates.log') {
        $this->db = $database;
        $this->logFile = $logFile;
    }
    
    private function isAuthenticated() {
        return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }
    
    private function getUserId() {
        return $this->isAuthenticated() ? (int)$_SESSION['user_id'] : null;
    }
    
    private function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    private function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    private function validatePhone($phone) {
        return preg_match('/^[\+]?[1-9][\d]{0,15}$/', $phone);
    }
    
    private function validateName($name) {
        return preg_match('/^[a-zA-Z\s\-\']{1,50}$/', $name);
    }
    
    private function validateAddress($address) {
        return preg_match('/^[a-zA-Z0-9\s\-\.,#]{1,100}$/', $address);
    }
    
    private function validateCity($city) {
        return preg_match('/^[a-zA-Z\s\-\']{1,50}$/', $city);
    }
    
    private function validatePostalCode($code) {
        return preg_match('/^[a-zA-Z0-9\s\-]{1,20}$/', $code);
    }
    
    private function hashSensitiveData($data) {
        return hash('sha256', $data);
    }
    
    private function logProfileUpdate($userId, $success, $fields = []) {
        $timestamp = date('Y-m-d H:i:s');
        $status = $success ? 'SUCCESS' : 'FAILED';
        $fieldsStr = implode(',', $fields);
        $logEntry = "[{$timestamp}] USER_ID: {$userId} | STATUS: {$status} | FIELDS: {$fieldsStr}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function getUserProfile() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        $userId = $this->getUserId();
        $stmt = $this->db->prepare("SELECT first_name, last_name, email, phone, address, city, state, postal_code, country FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    public function updateProfile($data) {
        if (!$this->isAuthenticated()) {
            $this->logProfileUpdate(0, false, ['authentication_failed']);
            return ['success' => false, 'message' => 'Authentication required'];
        }
        
        $userId = $this->getUserId();
        $errors = [];
        $updatedFields = [];
        
        $firstName = $this->sanitizeInput($data['first_name'] ?? '');
        $lastName = $this->sanitizeInput($data['last_name'] ?? '');
        $email = $this->sanitizeInput($data['email'] ?? '');
        $phone = $this->sanitizeInput($data['phone'] ?? '');
        $address = $this->sanitizeInput($data['address'] ?? '');
        $city = $this->sanitizeInput($data['city'] ?? '');
        $state = $this->sanitizeInput($data['state'] ?? '');
        $postalCode = $this->sanitizeInput($data['postal_code'] ?? '');
        $country = $this->sanitizeInput($data['country'] ?? '');
        
        if (empty($firstName) || !$this->validateName($firstName)) {
            $errors[] = 'Invalid first name';
        } else {
            $updatedFields[] = 'first_name';
        }
        
        if (empty($lastName) || !$this->validateName($lastName)) {
            $errors[] = 'Invalid last name';
        } else {
            $updatedFields[] = 'last_name';
        }
        
        if (empty($email) || !$this->validateEmail($email)) {
            $errors[] = 'Invalid email address';
        } else {
            $updatedFields[] = 'email';
        }
        
        if (!empty($phone) && !$this->validatePhone($phone)) {
            $errors[] = 'Invalid phone number';
        } elseif (!empty($phone)) {
            $updatedFields[] = 'phone';
        }
        
        if (!empty($address) && !$this->validateAddress($address)) {
            $errors[] = 'Invalid address';
        } elseif (!empty($address)) {
            $updatedFields[] = 'address';
        }
        
        if (!empty($city) && !$this->validateCity($city)) {
            $errors[] = 'Invalid city';
        } elseif (!empty($city)) {
            $updatedFields[] = 'city';
        }
        
        if (!empty($postalCode) && !$this->validatePostalCode($postalCode)) {
            $errors[] = 'Invalid postal code';
        } elseif (!empty($postalCode)) {
            $updatedFields[] = 'postal_code';
        }
        
        if (!empty($errors)) {
            $this->logProfileUpdate($userId, false, $updatedFields);
            return ['success' => false, 'message' => 'Validation failed'];
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, postal_code = ?, country = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sssssssssi", $firstName, $lastName, $email, $phone, $address, $city, $state, $postalCode, $country, $userId);
            
            if ($stmt->execute()) {
                $this->logProfileUpdate($userId, true, $updatedFields);
                return ['success' => true, 'message' => 'Profile updated successfully'];
            } else {
                $this->logProfileUpdate($userId, false, $updatedFields);
                return ['success' => false, 'message' => 'Update failed'];
            }
        } catch (Exception $e) {
            $this->logProfileUpdate($userId, false, $updatedFields);
            return ['success' => false, 'message' => 'An error occurred'];
        }
    }
    
    public function renderForm() {
        if (!$this->isAuthenticated()) {
            return '<p>Please log in to access your profile.</p>';
        }
        
        $profile = $this->getUserProfile();
        if (!$profile) {
            return '<p>Unable to load profile information.</p>';
        }
        
        $csrfToken = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $csrfToken;
        
        return '
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">
            
            <h3>Personal Information</h3>
            
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" value="' . htmlspecialchars($profile['first_name'] ?? '') . '" required maxlength="50">
            
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" value="' . htmlspecialchars($profile['last_name'] ?? '') . '" required maxlength="50">
            
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="' . htmlspecialchars($profile['email'] ?? '') . '" required maxlength="100">
            
            <label for="phone">Phone:</label>
            <input type="tel" id="phone" name="phone" value="' . htmlspecialchars($profile['phone'] ?? '') . '" maxlength="20">
            
            <h3>Billing Address</h3>
            
            <label for="address">Address:</label>
            <input type="text" id="address" name="address" value="' . htmlspecialchars($profile['address'] ?? '') . '" maxlength="100">
            
            <label for="city">City:</label>
            <input type="text" id="city" name="city" value="' . htmlspecialchars($profile['city'] ?? '') . '" maxlength="50">
            
            <label for="state">State/Province:</label>
            <input type="text" id="state" name="state" value="' . htmlspecialchars($profile['state'] ?? '') . '" maxlength="50">
            
            <label for="postal_code">Postal Code:</label>
            <input type="text" id="postal_code" name="postal_code" value="' . htmlspecialchars($profile['postal_code'] ?? '') . '" maxlength="20">
            
            <label for="country">Country:</label>
            <input type="text" id="country" name="country" value="' . htmlspecialchars($profile['country'] ?? '') . '" maxlength="50">
            
            <button type="submit" name="update_profile">Update Profile</button>
        </form>';
    }
}

$dbHost = 'localhost';
$dbUser = 'username';
$dbPass = 'password';
$dbName = 'database_name';

try {
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    $mysqli->set_charset("utf8");
} catch (Exception $e) {
    die('Database connection failed');
}

$userProfile = new UserProfile($mysqli);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $message = '<div>Security validation failed</div>';
    } else {
        $result = $userProfile->updateProfile($_POST);
        $message = '<div>' . htmlspecialchars($result['message']) . '</div>';
    }
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
    <h2>User Profile</h2>
    
    <?php echo $message; ?>
    
    <?php echo $userProfile->renderForm(); ?>
    
</body>
</html>
?>