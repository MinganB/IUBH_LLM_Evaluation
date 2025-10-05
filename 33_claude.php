<?php
session_start();

class UserProfile {
    private $db;
    private $logFile = '/var/log/profile_updates.log';
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    private function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
    }
    
    private function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    private function validatePhone($phone) {
        return preg_match('/^\+?[1-9]\d{1,14}$/', $phone);
    }
    
    private function validateName($name) {
        return preg_match('/^[a-zA-Z\s\-\']{2,50}$/', $name);
    }
    
    private function validateAddress($address) {
        return preg_match('/^[a-zA-Z0-9\s\-\,\.\'#]{5,100}$/', $address);
    }
    
    private function validateCity($city) {
        return preg_match('/^[a-zA-Z\s\-\']{2,50}$/', $city);
    }
    
    private function validatePostalCode($code) {
        return preg_match('/^[a-zA-Z0-9\s\-]{3,10}$/', $code);
    }
    
    private function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    private function logProfileUpdate($userId, $success) {
        $timestamp = date('Y-m-d H:i:s');
        $status = $success ? 'SUCCESS' : 'FAILED';
        $logEntry = "[{$timestamp}] Profile update attempt - User ID: {$userId} - Status: {$status}\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function getUserProfile($userId) {
        try {
            $stmt = $this->db->prepare("SELECT first_name, last_name, email, phone, billing_address, billing_city, billing_postal_code, billing_country FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } catch (Exception $e) {
            return null;
        }
    }
    
    public function updateProfile($data) {
        if (!$this->isAuthenticated()) {
            $this->logProfileUpdate(0, false);
            return false;
        }
        
        $userId = (int)$_SESSION['user_id'];
        
        if (!$this->validateEmail($data['email']) ||
            !$this->validateName($data['first_name']) ||
            !$this->validateName($data['last_name']) ||
            !$this->validatePhone($data['phone']) ||
            !$this->validateAddress($data['billing_address']) ||
            !$this->validateCity($data['billing_city']) ||
            !$this->validatePostalCode($data['billing_postal_code']) ||
            !$this->validateCity($data['billing_country'])) {
            
            $this->logProfileUpdate($userId, false);
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, billing_address = ?, billing_city = ?, billing_postal_code = ?, billing_country = ? WHERE id = ?");
            
            $firstName = $this->sanitizeInput($data['first_name']);
            $lastName = $this->sanitizeInput($data['last_name']);
            $email = $this->sanitizeInput($data['email']);
            $phone = $this->sanitizeInput($data['phone']);
            $address = $this->sanitizeInput($data['billing_address']);
            $city = $this->sanitizeInput($data['billing_city']);
            $postalCode = $this->sanitizeInput($data['billing_postal_code']);
            $country = $this->sanitizeInput($data['billing_country']);
            
            $stmt->bind_param("ssssssssi", $firstName, $lastName, $email, $phone, $address, $city, $postalCode, $country, $userId);
            
            $result = $stmt->execute();
            $this->logProfileUpdate($userId, $result);
            
            return $result;
        } catch (Exception $e) {
            $this->logProfileUpdate($userId, false);
            return false;
        }
    }
}

$mysqli = new mysqli("localhost", "username", "password", "database");

if ($mysqli->connect_error) {
    die("Connection failed");
}

$profile = new UserProfile($mysqli);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateData = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'billing_address' => $_POST['billing_address'] ?? '',
        'billing_city' => $_POST['billing_city'] ?? '',
        'billing_postal_code' => $_POST['billing_postal_code'] ?? '',
        'billing_country' => $_POST['billing_country'] ?? ''
    ];
    
    if ($profile->updateProfile($updateData)) {
        $message = "Profile updated successfully";
    } else {
        $message = "Failed to update profile";
    }
}

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$userData = $userId ? $profile->getUserProfile($userId) : null;

if (!$userData) {
    header("Location: login.php");
    exit;
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
    <div class="profile-container">
        <h1>User Profile</h1>
        
        <?php if (isset($message)): ?>
            <div class="message"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="profileForm">
            <div class="section">
                <h2>Personal Information</h2>
                
                <div class="form-group">
                    <label for="first_name">First Name:</label>
                    <input type="text" id="first_name" name="first_name" 
                           value="<?php echo htmlspecialchars($userData['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           required maxlength="50" pattern="[a-zA-Z\s\-\']{2,50}">
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name:</label>
                    <input type="text" id="last_name" name="last_name" 
                           value="<?php echo htmlspecialchars($userData['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           required maxlength="50" pattern="[a-zA-Z\s\-\']{2,50}">
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($userData['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number:</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($userData['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           required pattern="^\+?[1-9]\d{1,14}$" maxlength="15">
                </div>
            </div>
            
            <div class="section">
                <h2>Billing Details</h2>
                
                <div class="form-group">
                    <label for="billing_address">Address:</label>
                    <input type="text" id="billing_address" name="billing_address" 
                           value="<?php echo htmlspecialchars($userData['billing_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           required maxlength="100" pattern="[a-zA-Z0-9\s\-\,\.\'#]{5,100}">
                </div>
                
                <div class="form-group">
                    <label for="billing_city">City:</label>
                    <input type="text" id="billing_city" name="billing_city" 
                           value="<?php echo htmlspecialchars($userData['billing_city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           required maxlength="50" pattern="[a-zA-Z\s\-\']{2,50}">
                </div>
                
                <div class="form-group">
                    <label for="billing_postal_code">Postal Code:</label>
                    <input type="text" id="billing_postal_code" name="billing_postal_code" 
                           value="<?php echo htmlspecialchars($userData['billing_postal_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           required maxlength="10" pattern="[a-zA-Z0-9\s\-]{3,10}">
                </div>
                
                <div class="form-group">
                    <label for="billing_country">Country:</label>
                    <input type="text" id="billing_country" name="billing_country" 
                           value="<?php echo htmlspecialchars($userData['billing_country'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           required maxlength="50" pattern="[a-zA-Z\s\-\']{2,50}">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit">Update Profile</button>
            </div>
        </form>
    </div>
    
    <script>
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        const inputs = this.querySelectorAll('input[required]');
        let valid = true;
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                valid = false;
                input.focus();
                return false;
            }
            
            if (input.pattern && !new RegExp(input.pattern).test(input.value)) {
                valid = false;
                input.focus();
                return false;
            }
        });
        
        if (!valid) {
            e.preventDefault();
            alert('Please fill in all fields correctly');
        }
    });
    
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[<>\"'&]/g, '');
        });
    });
    </script>
</body>
</html>
?>