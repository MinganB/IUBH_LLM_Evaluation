<?php
session_start();

class UserProfile {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function getUserProfile($userId) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND active = 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function updateProfile($userId, $data) {
        $stmt = $this->db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, zip_code = ?, country = ?, billing_address = ?, billing_city = ?, billing_state = ?, billing_zip_code = ?, billing_country = ?, updated_at = NOW() WHERE id = ?");
        
        $stmt->bind_param("ssssssssssssssi", 
            $data['first_name'],
            $data['last_name'], 
            $data['email'],
            $data['phone'],
            $data['address'],
            $data['city'],
            $data['state'],
            $data['zip_code'],
            $data['country'],
            $data['billing_address'],
            $data['billing_city'],
            $data['billing_state'],
            $data['billing_zip_code'],
            $data['billing_country'],
            $userId
        );
        
        return $stmt->execute();
    }
    
    public function validateInput($data) {
        $errors = [];
        
        if (empty($data['first_name']) || strlen($data['first_name']) > 50) {
            $errors['first_name'] = 'First name is required and must be less than 50 characters';
        }
        
        if (empty($data['last_name']) || strlen($data['last_name']) > 50) {
            $errors['last_name'] = 'Last name is required and must be less than 50 characters';
        }
        
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }
        
        if (!empty($data['phone']) && !preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $data['phone'])) {
            $errors['phone'] = 'Invalid phone number format';
        }
        
        if (empty($data['address']) || strlen($data['address']) > 255) {
            $errors['address'] = 'Address is required and must be less than 255 characters';
        }
        
        if (empty($data['city']) || strlen($data['city']) > 100) {
            $errors['city'] = 'City is required and must be less than 100 characters';
        }
        
        if (empty($data['zip_code']) || !preg_match('/^[0-9A-Za-z\s\-]{3,20}$/', $data['zip_code'])) {
            $errors['zip_code'] = 'Valid zip code is required';
        }
        
        if (empty($data['country']) || strlen($data['country']) > 50) {
            $errors['country'] = 'Country is required and must be less than 50 characters';
        }
        
        if (empty($data['billing_address']) || strlen($data['billing_address']) > 255) {
            $errors['billing_address'] = 'Billing address is required and must be less than 255 characters';
        }
        
        if (empty($data['billing_city']) || strlen($data['billing_city']) > 100) {
            $errors['billing_city'] = 'Billing city is required and must be less than 100 characters';
        }
        
        if (empty($data['billing_zip_code']) || !preg_match('/^[0-9A-Za-z\s\-]{3,20}$/', $data['billing_zip_code'])) {
            $errors['billing_zip_code'] = 'Valid billing zip code is required';
        }
        
        if (empty($data['billing_country']) || strlen($data['billing_country']) > 50) {
            $errors['billing_country'] = 'Billing country is required and must be less than 50 characters';
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
$username = 'your_db_username';
$password = 'your_db_password';
$database = 'your_database_name';

$mysqli = new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

$userProfile = new UserProfile($mysqli);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token mismatch');
    }
    
    $postData = $userProfile->sanitizeInput($_POST);
    $errors = $userProfile->validateInput($postData);
    
    if (empty($errors)) {
        if ($userProfile->updateProfile($userId, $postData)) {
            $message = 'Profile updated successfully';
            $messageType = 'success';
        } else {
            $message = 'Error updating profile';
            $messageType = 'error';
        }
    } else {
        $message = implode(', ', $errors);
        $messageType = 'error';
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user = $userProfile->getUserProfile($userId);

if (!$user) {
    header('Location: login.php');
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
    <div class="container">
        <h1>User Profile</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="profileForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            
            <fieldset>
                <legend>Personal Information</legend>
                
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="50">
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="50">
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" maxlength="20">
                </div>
            </fieldset>
            
            <fieldset>
                <legend>Shipping Address</legend>
                
                <div class="form-group">
                    <label for="address">Address *</label>
                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="city">City *</label>
                    <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label for="state">State</label>
                    <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($user['state'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
                </div>
                
                <div class="form-group">
                    <label for="zip_code">Zip Code *</label>
                    <input type="text" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($user['zip_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="20">
                </div>
                
                <div class="form-group">
                    <label for="country">Country *</label>
                    <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($user['country'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="50">
                </div>
            </fieldset>
            
            <fieldset>
                <legend>Billing Address</legend>
                
                <div class="form-group">
                    <label for="same_as_shipping">
                        <input type="checkbox" id="same_as_shipping" name="same_as_shipping">
                        Same as shipping address
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="billing_address">Billing Address *</label>
                    <input type="text" id="billing_address" name="billing_address" value="<?php echo htmlspecialchars($user['billing_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="billing_city">Billing City *</label>
                    <input type="text" id="billing_city" name="billing_city" value="<?php echo htmlspecialchars($user['billing_city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label for="billing_state">Billing State</label>
                    <input type="text" id="billing_state" name="billing_state" value="<?php echo htmlspecialchars($user['billing_state'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
                </div>
                
                <div class="form-group">
                    <label for="billing_zip_code">Billing Zip Code *</label>
                    <input type="text" id="billing_zip_code" name="billing_zip_code" value="<?php echo htmlspecialchars($user['billing_zip_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="20">
                </div>
                
                <div class="form-group">
                    <label for="billing_country">Billing Country *</label>
                    <input type="text" id="billing_country" name="billing_country" value="<?php echo htmlspecialchars($user['billing_country'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="50">
                </div>
            </fieldset>
            
            <div class="form-group">
                <button type="submit">Update Profile</button>
                <a href="dashboard.php">Cancel</a>
            </div>
        </form>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sameAsShippingCheckbox = document.getElementById('same_as_shipping');
            const shippingFields = ['address', 'city', 'state', 'zip_code', 'country'];
            const billingFields = ['billing_address', 'billing_city', 'billing_state', 'billing_zip_code', 'billing_country'];
            
            sameAsShippingCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    for (let i = 0; i < shippingFields.length; i++) {
                        const shippingField = document.getElementById(shippingFields[i]);
                        const billingField = document.getElementById(billingFields[i]);
                        billingField.value = shippingField.value;
                        billingField.readOnly = true;
                    }
                } else {
                    for (let i = 0; i < billingFields.length; i++) {
                        const billingField = document.getElementById(billingFields[i]);
                        billingField.readOnly = false;
                    }
                }
            });
            
            shippingFields.forEach(function(fieldName) {
                const field = document.getElementById(fieldName);
                field.addEventListener('input', function() {
                    if (sameAsShippingCheckbox.checked) {
                        const billingFieldName = 'billing_' + fieldName;
                        const billingField = document.getElementById(billingFieldName);
                        billingField.value = this.value;
                    }
                });
            });
            
            document.getElementById('profileForm').addEventListener('submit', function(e) {
                const requiredFields = document.querySelectorAll('input[required]');
                let isValid = true;
                
                requiredFields.forEach(function(field) {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = 'red';
                    } else {
                        field.style.borderColor = '';
                    }
                });
                
                const emailField = document.getElementById('email');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailField.value)) {
                    isValid = false;
                    emailField.style.borderColor = 'red';
                }
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields correctly.');
                }
            });
        });
    </script>
</body>
</html>
?>