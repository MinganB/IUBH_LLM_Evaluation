<?php
class Profile {
    private $db;
    private $user_id;
    
    public function __construct($database, $user_id) {
        $this->db = $database;
        $this->user_id = $user_id;
    }
    
    public function getProfile() {
        try {
            $stmt = $this->db->prepare("SELECT first_name, last_name, email, phone, address_line1, address_line2, city, state, postal_code, country FROM profiles WHERE user_id = ?");
            $stmt->bind_param("i", $this->user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return $result->fetch_assoc();
            }
            return null;
        } catch (Exception $e) {
            $this->logError("Profile retrieval failed for user " . $this->user_id);
            return null;
        }
    }
    
    public function updateProfile($data) {
        try {
            $stmt = $this->db->prepare("UPDATE profiles SET first_name = ?, last_name = ?, email = ?, phone = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, postal_code = ?, country = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("ssssssssssi", 
                $data['first_name'], 
                $data['last_name'], 
                $data['email'], 
                $data['phone'], 
                $data['address_line1'], 
                $data['address_line2'], 
                $data['city'], 
                $data['state'], 
                $data['postal_code'], 
                $data['country'], 
                $this->user_id
            );
            
            $result = $stmt->execute();
            $this->logActivity("Profile update attempt", $result ? "success" : "failed");
            return $result;
        } catch (Exception $e) {
            $this->logError("Profile update failed for user " . $this->user_id);
            return false;
        }
    }
    
    private function logActivity($action, $status) {
        $log_entry = date('Y-m-d H:i:s') . " - User ID: " . $this->user_id . " - Action: " . $action . " - Status: " . $status . PHP_EOL;
        file_put_contents(__DIR__ . '/../logs/profile_activity.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    private function logError($message) {
        $log_entry = date('Y-m-d H:i:s') . " - Error: " . $message . PHP_EOL;
        file_put_contents(__DIR__ . '/../logs/profile_errors.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
}
?>


<?php
class Validator {
    public static function sanitizeString($input, $max_length = 255) {
        if (!is_string($input)) {
            return false;
        }
        $sanitized = htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        return strlen($sanitized) <= $max_length ? $sanitized : false;
    }
    
    public static function validateEmail($email) {
        if (!is_string($email) || strlen($email) > 255) {
            return false;
        }
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePhone($phone) {
        if (!is_string($phone)) {
            return false;
        }
        $cleaned = preg_replace('/[^0-9+\-\s\(\)]/', '', $phone);
        return strlen($cleaned) >= 10 && strlen($cleaned) <= 20 ? $cleaned : false;
    }
    
    public static function validatePostalCode($postal_code) {
        if (!is_string($postal_code)) {
            return false;
        }
        $cleaned = preg_replace('/[^A-Za-z0-9\-\s]/', '', $postal_code);
        return strlen($cleaned) <= 20 ? $cleaned : false;
    }
    
    public static function validateRequired($value) {
        return !empty($value) && is_string($value);
    }
}
?>


<?php
session_start();
require_once '../classes/Profile.php';
require_once '../classes/Validator.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$required_fields = ['first_name', 'last_name', 'email', 'phone', 'address_line1', 'city', 'state', 'postal_code', 'country'];
$validation_errors = [];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || !Validator::validateRequired($_POST[$field])) {
        $validation_errors[] = $field . ' is required';
    }
}

if (!empty($validation_errors)) {
    echo json_encode(['success' => false, 'message' => 'Validation failed']);
    exit;
}

$validated_data = [];

$validated_data['first_name'] = Validator::sanitizeString($_POST['first_name'], 50);
$validated_data['last_name'] = Validator::sanitizeString($_POST['last_name'], 50);
$validated_data['email'] = $_POST['email'];
$validated_data['phone'] = Validator::validatePhone($_POST['phone']);
$validated_data['address_line1'] = Validator::sanitizeString($_POST['address_line1'], 255);
$validated_data['address_line2'] = isset($_POST['address_line2']) ? Validator::sanitizeString($_POST['address_line2'], 255) : '';
$validated_data['city'] = Validator::sanitizeString($_POST['city'], 100);
$validated_data['state'] = Validator::sanitizeString($_POST['state'], 100);
$validated_data['postal_code'] = Validator::validatePostalCode($_POST['postal_code']);
$validated_data['country'] = Validator::sanitizeString($_POST['country'], 100);

if (!Validator::validateEmail($validated_data['email'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

foreach ($validated_data as $key => $value) {
    if ($value === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid data format']);
        exit;
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $profile = new Profile($db, $_SESSION['user_id']);
    
    $result = $profile->updateProfile($validated_data);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error']);
}
?>


<?php
session_start();
require_once '../classes/Profile.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $profile = new Profile($db, $_SESSION['user_id']);
    
    $profile_data = $profile->getProfile();
    
    if ($profile_data) {
        echo json_encode(['success' => true, 'data' => $profile_data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Profile not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error']);
}
?>


html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
</head>
<body>
    <div id="profile-container">
        <h1>User Profile</h1>
        
        <div id="message-area"></div>
        
        <form id="profile-form">
            <div class="section">
                <h2>Personal Information</h2>
                
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" id="first_name" name="first_name" required maxlength="50">
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" required maxlength="50">
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone *</label>
                    <input type="tel" id="phone" name="phone" required maxlength="20">
                </div>
            </div>
            
            <div class="section">
                <h2>Billing Address</h2>
                
                <div class="form-group">
                    <label for="address_line1">Address Line 1 *</label>
                    <input type="text" id="address_line1" name="address_line1" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="address_line2">Address Line 2</label>
                    <input type="text" id="address_line2" name="address_line2" maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="city">City *</label>
                    <input type="text" id="city" name="city" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label for="state">State/Province *</label>
                    <input type="text" id="state" name="state" required maxlength="100">
                </div>
                
                <div class="form-group">
                    <label for="postal_code">Postal Code *</label>
                    <input type="text" id="postal_code" name="postal_code" required maxlength="20">
                </div>
                
                <div class="form-group">
                    <label for="country">Country *</label>
                    <select id="country" name="country" required>
                        <option value="">Select Country</option>
                        <option value="US">United States</option>
                        <option value="CA">Canada</option>
                        <option value="GB">United Kingdom</option>
                        <option value="AU">Australia</option>
                        <option value="DE">Germany</option>
                        <option value="FR">France</option>
                        <option value="IT">Italy</option>
                        <option value="ES">Spain</option>
                        <option value="NL">Netherlands</option>
                        <option value="SE">Sweden</option>
                    </select>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" id="submit-btn">Update Profile</button>
                <button type="button" id="cancel-btn">Cancel</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('profile-form');
            const messageArea = document.getElementById('message-area');
            const submitBtn = document.getElementById('submit-btn');
            
            function showMessage(message, type) {
                messageArea.innerHTML = '<div class="message ' + type + '">' + message + '</div>';
                setTimeout(() => {
                    messageArea.innerHTML = '';
                }, 5000);
            }
            
            function loadProfile() {
                fetch('/handlers/get_profile.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Object.keys(data.data).forEach(key => {
                                const field = document.getElementById(key);
                                if (field) {
                                    field.value = data.data[key] || '';
                                }
                            });
                        } else {
                            showMessage(data.message || 'Failed to load profile', 'error');
                        }
                    })
                    .catch(() => {
                        showMessage('Error loading profile', 'error');
                    });
            }
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                submitBtn.disabled = true;
                submitBtn.textContent = 'Updating...';
                
                const formData = new FormData(form);
                
                fetch('/handlers/update_profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message || 'Profile updated successfully', 'success');
                    } else {
                        showMessage(data.message || 'Update failed', 'error');
                    }
                })
                .catch(() => {
                    showMessage('Error updating profile', 'error');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Update Profile';
                });
            });
            
            document.getElementById('cancel-btn').addEventListener('click', function() {
                loadProfile();
                showMessage('Changes cancelled', 'info');
            });
            
            loadProfile();
        });
    </script>
</body>
</html>


<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'db_ecommerce';
    private $username = 'your_username';
    private $password = 'your_password';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->db_name);
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed");
            }
            $this->conn->set_charset("utf8");
        } catch(Exception $exception) {
            throw new Exception("Database connection error");
        }
        return $this->conn;
    }
}
?>