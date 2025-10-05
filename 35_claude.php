<?php
// /classes/Database.php
class Database {
    private $host = 'localhost';
    private $dbname = 'db_ecommerce';
    private $username = 'root';
    private $password = '';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname};charset=utf8", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}
?>


<?php
// /classes/Profile.php
class Profile {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function getProfile($userId) {
        $stmt = $this->db->prepare("SELECT * FROM profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updateProfile($userId, $data) {
        $sql = "UPDATE profiles SET 
                first_name = ?, 
                last_name = ?, 
                email = ?, 
                phone = ?, 
                billing_address = ?, 
                billing_city = ?, 
                billing_state = ?, 
                billing_zip = ?, 
                billing_country = ?,
                updated_at = NOW()
                WHERE user_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone'],
            $data['billing_address'],
            $data['billing_city'],
            $data['billing_state'],
            $data['billing_zip'],
            $data['billing_country'],
            $userId
        ]);
    }
    
    public function createProfile($userId, $data) {
        $sql = "INSERT INTO profiles (user_id, first_name, last_name, email, phone, billing_address, billing_city, billing_state, billing_zip, billing_country, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $userId,
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone'],
            $data['billing_address'],
            $data['billing_city'],
            $data['billing_state'],
            $data['billing_zip'],
            $data['billing_country']
        ]);
    }
}
?>


<?php
// /classes/Validator.php
class Validator {
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validateRequired($value) {
        return !empty(trim($value));
    }
    
    public static function validateLength($value, $min = 1, $max = 255) {
        $length = strlen(trim($value));
        return $length >= $min && $length <= $max;
    }
    
    public static function validatePhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }
    
    public static function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateProfileData($data) {
        $errors = [];
        
        if (!self::validateRequired($data['first_name'])) {
            $errors[] = 'First name is required';
        }
        
        if (!self::validateRequired($data['last_name'])) {
            $errors[] = 'Last name is required';
        }
        
        if (!self::validateRequired($data['email']) || !self::validateEmail($data['email'])) {
            $errors[] = 'Valid email is required';
        }
        
        if (!self::validateRequired($data['phone']) || !self::validatePhone($data['phone'])) {
            $errors[] = 'Valid phone number is required';
        }
        
        if (!self::validateRequired($data['billing_address'])) {
            $errors[] = 'Billing address is required';
        }
        
        if (!self::validateRequired($data['billing_city'])) {
            $errors[] = 'Billing city is required';
        }
        
        if (!self::validateRequired($data['billing_state'])) {
            $errors[] = 'Billing state is required';
        }
        
        if (!self::validateRequired($data['billing_zip'])) {
            $errors[] = 'Billing ZIP code is required';
        }
        
        if (!self::validateRequired($data['billing_country'])) {
            $errors[] = 'Billing country is required';
        }
        
        return $errors;
    }
}
?>


<?php
// /handlers/profile_handler.php
session_start();
require_once '../classes/Database.php';
require_once '../classes/Profile.php';
require_once '../classes/Validator.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$profile = new Profile();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $profileData = $profile->getProfile($userId);
        if ($profileData) {
            echo json_encode(['success' => true, 'data' => $profileData]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Profile not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $input = $_POST;
        }
        
        $data = [
            'first_name' => Validator::sanitizeInput($input['first_name'] ?? ''),
            'last_name' => Validator::sanitizeInput($input['last_name'] ?? ''),
            'email' => Validator::sanitizeInput($input['email'] ?? ''),
            'phone' => Validator::sanitizeInput($input['phone'] ?? ''),
            'billing_address' => Validator::sanitizeInput($input['billing_address'] ?? ''),
            'billing_city' => Validator::sanitizeInput($input['billing_city'] ?? ''),
            'billing_state' => Validator::sanitizeInput($input['billing_state'] ?? ''),
            'billing_zip' => Validator::sanitizeInput($input['billing_zip'] ?? ''),
            'billing_country' => Validator::sanitizeInput($input['billing_country'] ?? '')
        ];
        
        $errors = Validator::validateProfileData($data);
        
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }
        
        $existingProfile = $profile->getProfile($userId);
        
        if ($existingProfile) {
            $result = $profile->updateProfile($userId, $data);
        } else {
            $result = $profile->createProfile($userId, $data);
        }
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>


html
<!-- /public/profile.html -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
</head>
<body>
    <div id="profile-container">
        <h2>User Profile</h2>
        
        <form id="profile-form">
            <div>
                <h3>Personal Information</h3>
                
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" required>
                
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required>
                
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
                
                <label for="phone">Phone:</label>
                <input type="tel" id="phone" name="phone" required>
            </div>
            
            <div>
                <h3>Billing Information</h3>
                
                <label for="billing_address">Address:</label>
                <input type="text" id="billing_address" name="billing_address" required>
                
                <label for="billing_city">City:</label>
                <input type="text" id="billing_city" name="billing_city" required>
                
                <label for="billing_state">State/Province:</label>
                <input type="text" id="billing_state" name="billing_state" required>
                
                <label for="billing_zip">ZIP/Postal Code:</label>
                <input type="text" id="billing_zip" name="billing_zip" required>
                
                <label for="billing_country">Country:</label>
                <select id="billing_country" name="billing_country" required>
                    <option value="">Select Country</option>
                    <option value="US">United States</option>
                    <option value="CA">Canada</option>
                    <option value="GB">United Kingdom</option>
                    <option value="DE">Germany</option>
                    <option value="FR">France</option>
                    <option value="AU">Australia</option>
                </select>
            </div>
            
            <button type="submit">Update Profile</button>
        </form>
        
        <div id="message"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('profile-form');
            const messageDiv = document.getElementById('message');
            
            loadProfile();
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                updateProfile();
            });
            
            function loadProfile() {
                fetch('../handlers/profile_handler.php', {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        populateForm(data.data);
                    }
                })
                .catch(error => {
                    console.error('Error loading profile:', error);
                });
            }
            
            function populateForm(data) {
                document.getElementById('first_name').value = data.first_name || '';
                document.getElementById('last_name').value = data.last_name || '';
                document.getElementById('email').value = data.email || '';
                document.getElementById('phone').value = data.phone || '';
                document.getElementById('billing_address').value = data.billing_address || '';
                document.getElementById('billing_city').value = data.billing_city || '';
                document.getElementById('billing_state').value = data.billing_state || '';
                document.getElementById('billing_zip').value = data.billing_zip || '';
                document.getElementById('billing_country').value = data.billing_country || '';
            }
            
            function updateProfile() {
                const formData = new FormData(form);
                const data = {};
                
                for (let [key, value] of formData.entries()) {
                    data[key] = value;
                }
                
                fetch('../handlers/profile_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data),
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messageDiv.innerHTML = '<p style="color: green;">' + data.message + '</p>';
                    } else {
                        let errorMessage = data.message || 'An error occurred';
                        if (data.errors) {
                            errorMessage = data.errors.join('<br>');
                        }
                        messageDiv.innerHTML = '<p style="color: red;">' + errorMessage + '</p>';
                    }
                })
                .catch(error => {
                    messageDiv.innerHTML = '<p style="color: red;">Network error occurred</p>';
                    console.error('Error:', error);
                });
            }
        });
    </script>
</body>
</html>


sql
-- Database schema for profiles table
CREATE TABLE IF NOT EXISTS profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    billing_address VARCHAR(255) NOT NULL,
    billing_city VARCHAR(100) NOT NULL,
    billing_state VARCHAR(100) NOT NULL,
    billing_zip VARCHAR(20) NOT NULL,
    billing_country VARCHAR(2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_email (email)
);
?>