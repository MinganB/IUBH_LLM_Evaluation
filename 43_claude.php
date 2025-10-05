<?php
class Database {
    private $host = 'localhost';
    private $dbname = 'db_ecommerce';
    private $username = 'root';
    private $password = '';
    private $pdo;
    
    public function connect() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $this->pdo;
        } catch(PDOException $e) {
            throw new Exception('Connection failed: ' . $e->getMessage());
        }
    }
}
?>


<?php
class ProfileValidator {
    public function validate($data) {
        $errors = [];
        
        if (empty($data['first_name']) || strlen($data['first_name']) < 2) {
            $errors[] = 'First name must be at least 2 characters';
        }
        
        if (empty($data['last_name']) || strlen($data['last_name']) < 2) {
            $errors[] = 'Last name must be at least 2 characters';
        }
        
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }
        
        if (empty($data['phone']) || !preg_match('/^[\d\-\+\(\)\s]+$/', $data['phone'])) {
            $errors[] = 'Valid phone number is required';
        }
        
        if (empty($data['address'])) {
            $errors[] = 'Address is required';
        }
        
        if (empty($data['city'])) {
            $errors[] = 'City is required';
        }
        
        if (empty($data['postal_code']) || !preg_match('/^[A-Za-z0-9\s\-]+$/', $data['postal_code'])) {
            $errors[] = 'Valid postal code is required';
        }
        
        if (empty($data['country'])) {
            $errors[] = 'Country is required';
        }
        
        if (empty($data['billing_address'])) {
            $errors[] = 'Billing address is required';
        }
        
        if (empty($data['billing_city'])) {
            $errors[] = 'Billing city is required';
        }
        
        if (empty($data['billing_postal_code']) || !preg_match('/^[A-Za-z0-9\s\-]+$/', $data['billing_postal_code'])) {
            $errors[] = 'Valid billing postal code is required';
        }
        
        if (empty($data['billing_country'])) {
            $errors[] = 'Billing country is required';
        }
        
        return $errors;
    }
}
?>


<?php
require_once '../classes/Database.php';

class Profile {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }
    
    public function getProfile($userId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM profiles WHERE user_id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            throw new Exception('Error fetching profile: ' . $e->getMessage());
        }
    }
    
    public function updateProfile($userId, $data) {
        try {
            $sql = "UPDATE profiles SET 
                    first_name = ?, last_name = ?, email = ?, phone = ?, 
                    address = ?, city = ?, state = ?, postal_code = ?, country = ?,
                    billing_address = ?, billing_city = ?, billing_state = ?, 
                    billing_postal_code = ?, billing_country = ?, updated_at = NOW()
                    WHERE user_id = ?";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $data['first_name'], $data['last_name'], $data['email'], $data['phone'],
                $data['address'], $data['city'], $data['state'], $data['postal_code'], $data['country'],
                $data['billing_address'], $data['billing_city'], $data['billing_state'],
                $data['billing_postal_code'], $data['billing_country'], $userId
            ]);
        } catch(PDOException $e) {
            throw new Exception('Error updating profile: ' . $e->getMessage());
        }
    }
    
    public function createProfile($userId, $data) {
        try {
            $sql = "INSERT INTO profiles (user_id, first_name, last_name, email, phone, 
                    address, city, state, postal_code, country, billing_address, 
                    billing_city, billing_state, billing_postal_code, billing_country, 
                    created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $userId, $data['first_name'], $data['last_name'], $data['email'], $data['phone'],
                $data['address'], $data['city'], $data['state'], $data['postal_code'], $data['country'],
                $data['billing_address'], $data['billing_city'], $data['billing_state'],
                $data['billing_postal_code'], $data['billing_country']
            ]);
        } catch(PDOException $e) {
            throw new Exception('Error creating profile: ' . $e->getMessage());
        }
    }
}
?>


<?php
session_start();
require_once '../classes/Profile.php';
require_once '../classes/ProfileValidator.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    $data = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'state' => trim($_POST['state'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'country' => trim($_POST['country'] ?? ''),
        'billing_address' => trim($_POST['billing_address'] ?? ''),
        'billing_city' => trim($_POST['billing_city'] ?? ''),
        'billing_state' => trim($_POST['billing_state'] ?? ''),
        'billing_postal_code' => trim($_POST['billing_postal_code'] ?? ''),
        'billing_country' => trim($_POST['billing_country'] ?? '')
    ];
    
    $validator = new ProfileValidator();
    $errors = $validator->validate($data);
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
        exit;
    }
    
    $profile = new Profile();
    $userId = $_SESSION['user_id'];
    
    $existingProfile = $profile->getProfile($userId);
    
    if ($existingProfile) {
        $result = $profile->updateProfile($userId, $data);
    } else {
        $result = $profile->createProfile($userId, $data);
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
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
        <form id="profile-form" method="POST" action="../handlers/update_profile.php">
            <fieldset>
                <legend>Personal Information</legend>
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
            </fieldset>
            
            <fieldset>
                <legend>Address Information</legend>
                <div>
                    <label for="address">Address:</label>
                    <textarea id="address" name="address" required></textarea>
                </div>
                <div>
                    <label for="city">City:</label>
                    <input type="text" id="city" name="city" required>
                </div>
                <div>
                    <label for="state">State/Province:</label>
                    <input type="text" id="state" name="state">
                </div>
                <div>
                    <label for="postal_code">Postal Code:</label>
                    <input type="text" id="postal_code" name="postal_code" required>
                </div>
                <div>
                    <label for="country">Country:</label>
                    <input type="text" id="country" name="country" required>
                </div>
            </fieldset>
            
            <fieldset>
                <legend>Billing Information</legend>
                <div>
                    <input type="checkbox" id="same_as_shipping" name="same_as_shipping">
                    <label for="same_as_shipping">Same as shipping address</label>
                </div>
                <div>
                    <label for="billing_address">Billing Address:</label>
                    <textarea id="billing_address" name="billing_address" required></textarea>
                </div>
                <div>
                    <label for="billing_city">Billing City:</label>
                    <input type="text" id="billing_city" name="billing_city" required>
                </div>
                <div>
                    <label for="billing_state">Billing State/Province:</label>
                    <input type="text" id="billing_state" name="billing_state">
                </div>
                <div>
                    <label for="billing_postal_code">Billing Postal Code:</label>
                    <input type="text" id="billing_postal_code" name="billing_postal_code" required>
                </div>
                <div>
                    <label for="billing_country">Billing Country:</label>
                    <input type="text" id="billing_country" name="billing_country" required>
                </div>
            </fieldset>
            
            <div>
                <button type="submit">Update Profile</button>
                <button type="button" id="cancel-btn">Cancel</button>
            </div>
        </form>
        
        <div id="message-container"></div>
    </div>
    
    <script>
        document.getElementById('same_as_shipping').addEventListener('change', function() {
            const billingFields = ['billing_address', 'billing_city', 'billing_state', 'billing_postal_code', 'billing_country'];
            const shippingFields = ['address', 'city', 'state', 'postal_code', 'country'];
            
            if (this.checked) {
                billingFields.forEach((field, index) => {
                    document.getElementById(field).value = document.getElementById(shippingFields[index]).value;
                });
            }
        });
        
        document.getElementById('profile-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const messageContainer = document.getElementById('message-container');
            
            fetch('../handlers/update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageContainer.innerHTML = '<div class="success">' + data.message + '</div>';
                } else {
                    let errorMessage = data.message;
                    if (data.errors) {
                        errorMessage += '<ul>';
                        data.errors.forEach(error => {
                            errorMessage += '<li>' + error + '</li>';
                        });
                        errorMessage += '</ul>';
                    }
                    messageContainer.innerHTML = '<div class="error">' + errorMessage + '</div>';
                }
            })
            .catch(error => {
                messageContainer.innerHTML = '<div class="error">An error occurred: ' + error.message + '</div>';
            });
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            fetch('../handlers/get_profile.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.profile) {
                    const profile = data.profile;
                    Object.keys(profile).forEach(key => {
                        const field = document.getElementById(key);
                        if (field) {
                            field.value = profile[key] || '';
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>


<?php
session_start();
require_once '../classes/Profile.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    $profile = new Profile();
    $profileData = $profile->getProfile($_SESSION['user_id']);
    
    if ($profileData) {
        echo json_encode(['success' => true, 'profile' => $profileData]);
    } else {
        echo json_encode(['success' => true, 'profile' => null]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>