<?php
class UserProfile {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function getUserProfile($userId) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function updateProfile($userId, $data) {
        $stmt = $this->db->prepare("UPDATE users SET 
            first_name = ?, 
            last_name = ?, 
            email = ?, 
            phone = ?, 
            address = ?, 
            city = ?, 
            state = ?, 
            zip_code = ?, 
            country = ?,
            billing_address = ?,
            billing_city = ?,
            billing_state = ?,
            billing_zip_code = ?,
            billing_country = ?
            WHERE id = ?");
        
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
    
    public function validateProfileData($data) {
        $errors = [];
        
        if (empty($data['first_name'])) {
            $errors[] = "First name is required";
        }
        
        if (empty($data['last_name'])) {
            $errors[] = "Last name is required";
        }
        
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid email is required";
        }
        
        if (empty($data['phone'])) {
            $errors[] = "Phone number is required";
        }
        
        if (empty($data['address'])) {
            $errors[] = "Address is required";
        }
        
        if (empty($data['city'])) {
            $errors[] = "City is required";
        }
        
        if (empty($data['state'])) {
            $errors[] = "State is required";
        }
        
        if (empty($data['zip_code'])) {
            $errors[] = "ZIP code is required";
        }
        
        if (empty($data['country'])) {
            $errors[] = "Country is required";
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

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$username = "your_username";
$password = "your_password";
$database = "your_database";

$mysqli = new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$userProfile = new UserProfile($mysqli);
$userId = $_SESSION['user_id'];
$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $postData = $userProfile->sanitizeInput($_POST);
    $errors = $userProfile->validateProfileData($postData);
    
    if (empty($errors)) {
        if ($userProfile->updateProfile($userId, $postData)) {
            $message = "Profile updated successfully!";
            $messageType = "success";
        } else {
            $message = "Error updating profile. Please try again.";
            $messageType = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $messageType = "error";
    }
}

$userData = $userProfile->getUserProfile($userId);
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
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="profileForm">
            <div class="section">
                <h2>Personal Information</h2>
                
                <div class="form-group">
                    <label for="first_name">First Name:</label>
                    <input type="text" id="first_name" name="first_name" 
                           value="<?php echo htmlspecialchars($userData['first_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name:</label>
                    <input type="text" id="last_name" name="last_name" 
                           value="<?php echo htmlspecialchars($userData['last_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone:</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="section">
                <h2>Shipping Address</h2>
                
                <div class="form-group">
                    <label for="address">Address:</label>
                    <input type="text" id="address" name="address" 
                           value="<?php echo htmlspecialchars($userData['address'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="city">City:</label>
                    <input type="text" id="city" name="city" 
                           value="<?php echo htmlspecialchars($userData['city'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="state">State:</label>
                    <input type="text" id="state" name="state" 
                           value="<?php echo htmlspecialchars($userData['state'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="zip_code">ZIP Code:</label>
                    <input type="text" id="zip_code" name="zip_code" 
                           value="<?php echo htmlspecialchars($userData['zip_code'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="country">Country:</label>
                    <select id="country" name="country" required>
                        <option value="">Select Country</option>
                        <option value="US" <?php echo ($userData['country'] ?? '') == 'US' ? 'selected' : ''; ?>>United States</option>
                        <option value="CA" <?php echo ($userData['country'] ?? '') == 'CA' ? 'selected' : ''; ?>>Canada</option>
                        <option value="UK" <?php echo ($userData['country'] ?? '') == 'UK' ? 'selected' : ''; ?>>United Kingdom</option>
                        <option value="AU" <?php echo ($userData['country'] ?? '') == 'AU' ? 'selected' : ''; ?>>Australia</option>
                        <option value="DE" <?php echo ($userData['country'] ?? '') == 'DE' ? 'selected' : ''; ?>>Germany</option>
                        <option value="FR" <?php echo ($userData['country'] ?? '') == 'FR' ? 'selected' : ''; ?>>France</option>
                        <option value="JP" <?php echo ($userData['country'] ?? '') == 'JP' ? 'selected' : ''; ?>>Japan</option>
                    </select>
                </div>
            </div>
            
            <div class="section">
                <h2>Billing Address</h2>
                
                <div class="form-group">
                    <input type="checkbox" id="same_as_shipping" name="same_as_shipping">
                    <label for="same_as_shipping">Same as shipping address</label>
                </div>
                
                <div class="form-group">
                    <label for="billing_address">Billing Address:</label>
                    <input type="text" id="billing_address" name="billing_address" 
                           value="<?php echo htmlspecialchars($userData['billing_address'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="billing_city">Billing City:</label>
                    <input type="text" id="billing_city" name="billing_city" 
                           value="<?php echo htmlspecialchars($userData['billing_city'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="billing_state">Billing State:</label>
                    <input type="text" id="billing_state" name="billing_state" 
                           value="<?php echo htmlspecialchars($userData['billing_state'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="billing_zip_code">Billing ZIP Code:</label>
                    <input type="text" id="billing_zip_code" name="billing_zip_code" 
                           value="<?php echo htmlspecialchars($userData['billing_zip_code'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="billing_country">Billing Country:</label>
                    <select id="billing_country" name="billing_country">
                        <option value="">Select Country</option>
                        <option value="US" <?php echo ($userData['billing_country'] ?? '') == 'US' ? 'selected' : ''; ?>>United States</option>
                        <option value="CA" <?php echo ($userData['billing_country'] ?? '') == 'CA' ? 'selected' : ''; ?>>Canada</option>
                        <option value="UK" <?php echo ($userData['billing_country'] ?? '') == 'UK' ? 'selected' : ''; ?>>United Kingdom</option>
                        <option value="AU" <?php echo ($userData['billing_country'] ?? '') == 'AU' ? 'selected' : ''; ?>>Australia</option>
                        <option value="DE" <?php echo ($userData['billing_country'] ?? '') == 'DE' ? 'selected' : ''; ?>>Germany</option>
                        <option value="FR" <?php echo ($userData['billing_country'] ?? '') == 'FR' ? 'selected' : ''; ?>>France</option>
                        <option value="JP" <?php echo ($userData['billing_country'] ?? '') == 'JP' ? 'selected' : ''; ?>>Japan</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit">Update Profile</button>
                <button type="button" onclick="window.location.href='dashboard.php'">Cancel</button>
            </div>
        </form>
    </div>
    
    <script>
        document.getElementById('same_as_shipping').addEventListener('change', function() {
            const isChecked = this.checked;
            const shippingFields = ['address', 'city', 'state', 'zip_code', 'country'];
            const billingFields = ['billing_address', 'billing_city', 'billing_state', 'billing_zip_code', 'billing_country'];
            
            if (isChecked) {
                for (let i = 0; i < shippingFields.length; i++) {
                    const shippingValue = document.getElementById(shippingFields[i]).value;
                    document.getElementById(billingFields[i]).value = shippingValue;
                    document.getElementById(billingFields[i]).readOnly = true;
                }
            } else {
                for (let i = 0; i < billingFields.length; i++) {
                    document.getElementById(billingFields[i]).readOnly = false;
                }
            }
        });
        
        ['address', 'city', 'state', 'zip_code', 'country'].forEach(function(fieldId) {
            document.getElementById(fieldId).addEventListener('input', function() {
                if (document.getElementById('same_as_shipping').checked) {
                    const billingFieldId = 'billing_' + fieldId;
                    document.getElementById(billingFieldId).value = this.value;
                }
            });
        });
        
        document.getElementById('country').addEventListener('change', function() {
            if (document.getElementById('same_as_shipping').checked) {
                document.getElementById('billing_country').value = this.value;
            }
        });
        
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const requiredFields = ['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'state', 'zip_code', 'country'];
            let isValid = true;
            
            requiredFields.forEach(function(fieldId) {
                const field = document.getElementById(fieldId);
                if (!field.value.trim()) {
                    field.style.border = '2px solid red';
                    isValid = false;
                } else {
                    field.style.border = '';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
</body>
</html>

<?php
$mysqli->close();
?>