<?php
class UserProfile {
    private $db;
    private $userId;
    
    public function __construct($database, $userId) {
        $this->db = $database;
        $this->userId = $userId;
    }
    
    public function getUserData() {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    public function updateProfile($data) {
        $stmt = $this->db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, zip_code = ?, country = ?, billing_address = ?, billing_city = ?, billing_state = ?, billing_zip_code = ?, billing_country = ? WHERE id = ?");
        
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
            $this->userId
        );
        
        return $stmt->execute();
    }
    
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    public function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

session_start();
$userId = $_SESSION['user_id'] ?? 1;

$mysqli = new mysqli("localhost", "username", "password", "database");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$userProfile = new UserProfile($mysqli, $userId);
$userData = $userProfile->getUserData();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = array();
    $requiredFields = ['first_name', 'last_name', 'email'];
    $allFields = ['first_name', 'last_name', 'email', 'phone', 'address', 'city', 'state', 'zip_code', 'country', 'billing_address', 'billing_city', 'billing_state', 'billing_zip_code', 'billing_country'];
    
    $isValid = true;
    
    foreach ($allFields as $field) {
        $formData[$field] = $userProfile->sanitizeInput($_POST[$field] ?? '');
    }
    
    foreach ($requiredFields as $field) {
        if (empty($formData[$field])) {
            $isValid = false;
            $message = "Please fill in all required fields.";
            break;
        }
    }
    
    if ($isValid && !$userProfile->validateEmail($formData['email'])) {
        $isValid = false;
        $message = "Please enter a valid email address.";
    }
    
    if ($isValid) {
        if ($userProfile->updateProfile($formData)) {
            $message = "Profile updated successfully!";
            $userData = $userProfile->getUserData();
        } else {
            $message = "Error updating profile. Please try again.";
        }
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
    <div class="container">
        <h1>User Profile</h1>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <fieldset>
                <legend>Personal Information</legend>
                
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($userData['first_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($userData['last_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($userData['address'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($userData['city'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="state">State</label>
                    <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($userData['state'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="zip_code">Zip Code</label>
                    <input type="text" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($userData['zip_code'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="country">Country</label>
                    <select id="country" name="country">
                        <option value="">Select Country</option>
                        <option value="US" <?php echo ($userData['country'] ?? '') === 'US' ? 'selected' : ''; ?>>United States</option>
                        <option value="CA" <?php echo ($userData['country'] ?? '') === 'CA' ? 'selected' : ''; ?>>Canada</option>
                        <option value="GB" <?php echo ($userData['country'] ?? '') === 'GB' ? 'selected' : ''; ?>>United Kingdom</option>
                        <option value="AU" <?php echo ($userData['country'] ?? '') === 'AU' ? 'selected' : ''; ?>>Australia</option>
                        <option value="DE" <?php echo ($userData['country'] ?? '') === 'DE' ? 'selected' : ''; ?>>Germany</option>
                        <option value="FR" <?php echo ($userData['country'] ?? '') === 'FR' ? 'selected' : ''; ?>>France</option>
                    </select>
                </div>
            </fieldset>
            
            <fieldset>
                <legend>Billing Information</legend>
                
                <div class="form-group">
                    <input type="checkbox" id="same_as_personal" onchange="copyPersonalToBilling()">
                    <label for="same_as_personal">Same as personal information</label>
                </div>
                
                <div class="form-group">
                    <label for="billing_address">Billing Address</label>
                    <input type="text" id="billing_address" name="billing_address" value="<?php echo htmlspecialchars($userData['billing_address'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="billing_city">Billing City</label>
                    <input type="text" id="billing_city" name="billing_city" value="<?php echo htmlspecialchars($userData['billing_city'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="billing_state">Billing State</label>
                    <input type="text" id="billing_state" name="billing_state" value="<?php echo htmlspecialchars($userData['billing_state'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="billing_zip_code">Billing Zip Code</label>
                    <input type="text" id="billing_zip_code" name="billing_zip_code" value="<?php echo htmlspecialchars($userData['billing_zip_code'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="billing_country">Billing Country</label>
                    <select id="billing_country" name="billing_country">
                        <option value="">Select Country</option>
                        <option value="US" <?php echo ($userData['billing_country'] ?? '') === 'US' ? 'selected' : ''; ?>>United States</option>
                        <option value="CA" <?php echo ($userData['billing_country'] ?? '') === 'CA' ? 'selected' : ''; ?>>Canada</option>
                        <option value="GB" <?php echo ($userData['billing_country'] ?? '') === 'GB' ? 'selected' : ''; ?>>United Kingdom</option>
                        <option value="AU" <?php echo ($userData['billing_country'] ?? '') === 'AU' ? 'selected' : ''; ?>>Australia</option>
                        <option value="DE" <?php echo ($userData['billing_country'] ?? '') === 'DE' ? 'selected' : ''; ?>>Germany</option>
                        <option value="FR" <?php echo ($userData['billing_country'] ?? '') === 'FR' ? 'selected' : ''; ?>>France</option>
                    </select>
                </div>
            </fieldset>
            
            <div class="form-group">
                <button type="submit">Update Profile</button>
                <button type="reset">Reset</button>
            </div>
        </form>
    </div>

    <script>
        function copyPersonalToBilling() {
            const checkbox = document.getElementById('same_as_personal');
            if (checkbox.checked) {
                document.getElementById('billing_address').value = document.getElementById('address').value;
                document.getElementById('billing_city').value = document.getElementById('city').value;
                document.getElementById('billing_state').value = document.getElementById('state').value;
                document.getElementById('billing_zip_code').value = document.getElementById('zip_code').value;
                document.getElementById('billing_country').value = document.getElementById('country').value;
            } else {
                document.getElementById('billing_address').value = '';
                document.getElementById('billing_city').value = '';
                document.getElementById('billing_state').value = '';
                document.getElementById('billing_zip_code').value = '';
                document.getElementById('billing_country').value = '';
            }
        }
    </script>
</body>
</html>

<?php
$mysqli->close();
?>