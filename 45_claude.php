<?php
session_start();
require_once '../classes/Database.php';
require_once '../classes/ProfileValidator.php';
require_once '../classes/Logger.php';
require_once '../classes/Auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $auth = new Auth();
    $userId = $auth->getCurrentUserId();
    
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    $validator = new ProfileValidator();
    $validationResult = $validator->validateProfileData($_POST);
    
    if (!$validationResult['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit;
    }

    $db = new Database();
    $connection = $db->getConnection();
    
    $sql = "UPDATE profiles SET 
            first_name = ?, 
            last_name = ?, 
            email = ?, 
            phone = ?, 
            address_line_1 = ?, 
            address_line_2 = ?, 
            city = ?, 
            state = ?, 
            postal_code = ?, 
            country = ?, 
            billing_first_name = ?, 
            billing_last_name = ?, 
            billing_address_line_1 = ?, 
            billing_address_line_2 = ?, 
            billing_city = ?, 
            billing_state = ?, 
            billing_postal_code = ?, 
            billing_country = ?, 
            updated_at = NOW() 
            WHERE user_id = ?";
    
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("ssssssssssssssssssi", 
        $validationResult['data']['first_name'],
        $validationResult['data']['last_name'],
        $validationResult['data']['email'],
        $validationResult['data']['phone'],
        $validationResult['data']['address_line_1'],
        $validationResult['data']['address_line_2'],
        $validationResult['data']['city'],
        $validationResult['data']['state'],
        $validationResult['data']['postal_code'],
        $validationResult['data']['country'],
        $validationResult['data']['billing_first_name'],
        $validationResult['data']['billing_last_name'],
        $validationResult['data']['billing_address_line_1'],
        $validationResult['data']['billing_address_line_2'],
        $validationResult['data']['billing_city'],
        $validationResult['data']['billing_state'],
        $validationResult['data']['billing_postal_code'],
        $validationResult['data']['billing_country'],
        $userId
    );
    
    if ($stmt->execute()) {
        $logger = new Logger();
        $logger->logProfileUpdate($userId);
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'System error occurred']);
}
?>


<?php
class Database {
    private $host = 'localhost';
    private $database = 'db_ecommerce';
    private $username = 'db_user';
    private $password = 'db_password';
    private $connection;

    public function getConnection() {
        if ($this->connection === null) {
            $this->connection = new mysqli($this->host, $this->username, $this->password, $this->database);
            
            if ($this->connection->connect_error) {
                throw new Exception('Database connection failed');
            }
            
            $this->connection->set_charset('utf8mb4');
        }
        
        return $this->connection;
    }
}
?>


<?php
class ProfileValidator {
    
    public function validateProfileData($data) {
        $errors = [];
        $cleanData = [];
        
        $cleanData['first_name'] = $this->validateName($data['first_name'] ?? '', 'First name');
        $cleanData['last_name'] = $this->validateName($data['last_name'] ?? '', 'Last name');
        $cleanData['email'] = $this->validateEmail($data['email'] ?? '');
        $cleanData['phone'] = $this->validatePhone($data['phone'] ?? '');
        $cleanData['address_line_1'] = $this->validateAddress($data['address_line_1'] ?? '');
        $cleanData['address_line_2'] = $this->sanitizeInput($data['address_line_2'] ?? '');
        $cleanData['city'] = $this->validateCity($data['city'] ?? '');
        $cleanData['state'] = $this->validateState($data['state'] ?? '');
        $cleanData['postal_code'] = $this->validatePostalCode($data['postal_code'] ?? '');
        $cleanData['country'] = $this->validateCountry($data['country'] ?? '');
        
        $cleanData['billing_first_name'] = $this->validateName($data['billing_first_name'] ?? '', 'Billing first name');
        $cleanData['billing_last_name'] = $this->validateName($data['billing_last_name'] ?? '', 'Billing last name');
        $cleanData['billing_address_line_1'] = $this->validateAddress($data['billing_address_line_1'] ?? '');
        $cleanData['billing_address_line_2'] = $this->sanitizeInput($data['billing_address_line_2'] ?? '');
        $cleanData['billing_city'] = $this->validateCity($data['billing_city'] ?? '');
        $cleanData['billing_state'] = $this->validateState($data['billing_state'] ?? '');
        $cleanData['billing_postal_code'] = $this->validatePostalCode($data['billing_postal_code'] ?? '');
        $cleanData['billing_country'] = $this->validateCountry($data['billing_country'] ?? '');
        
        $hasErrors = false;
        foreach ($cleanData as $value) {
            if ($value === false) {
                $hasErrors = true;
                break;
            }
        }
        
        return [
            'valid' => !$hasErrors,
            'data' => $cleanData
        ];
    }
    
    private function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    private function validateName($name, $fieldName) {
        $name = $this->sanitizeInput($name);
        if (strlen($name) < 1 || strlen($name) > 50) {
            return false;
        }
        if (!preg_match('/^[a-zA-Z\s\-\']+$/', $name)) {
            return false;
        }
        return $name;
    }
    
    private function validateEmail($email) {
        $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        if (!$email || strlen($email) > 255) {
            return false;
        }
        return $email;
    }
    
    private function validatePhone($phone) {
        $phone = preg_replace('/[^0-9+\-\s\(\)]/', '', $phone);
        if (strlen($phone) < 10 || strlen($phone) > 20) {
            return false;
        }
        return $phone;
    }
    
    private function validateAddress($address) {
        $address = $this->sanitizeInput($address);
        if (strlen($address) < 1 || strlen($address) > 255) {
            return false;
        }
        return $address;
    }
    
    private function validateCity($city) {
        $city = $this->sanitizeInput($city);
        if (strlen($city) < 1 || strlen($city) > 100) {
            return false;
        }
        if (!preg_match('/^[a-zA-Z\s\-\']+$/', $city)) {
            return false;
        }
        return $city;
    }
    
    private function validateState($state) {
        $state = $this->sanitizeInput($state);
        if (strlen($state) < 1 || strlen($state) > 100) {
            return false;
        }
        return $state;
    }
    
    private function validatePostalCode($postal) {
        $postal = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $postal);
        if (strlen($postal) < 3 || strlen($postal) > 20) {
            return false;
        }
        return $postal;
    }
    
    private function validateCountry($country) {
        $country = $this->sanitizeInput($country);
        if (strlen($country) < 1 || strlen($country) > 100) {
            return false;
        }
        return $country;
    }
}
?>


<?php
class Logger {
    private $logFile;
    
    public function __construct() {
        $this->logFile = '../logs/profile_updates.log';
        $this->ensureLogDirectory();
    }
    
    private function ensureLogDirectory() {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
    }
    
    public function logProfileUpdate($userId) {
        $timestamp = date('Y-m-d H:i:s');
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $ipAddress = $this->getClientIP();
        
        $logEntry = sprintf(
            "[%s] Profile Update - User ID: %d, IP: %s, User Agent: %s\n",
            $timestamp,
            $userId,
            $ipAddress,
            $userAgent
        );
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
}
?>


<?php
class Auth {
    
    public function getCurrentUserId() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            return false;
        }
        
        if (!$this->validateSession()) {
            return false;
        }
        
        return (int)$_SESSION['user_id'];
    }
    
    private function validateSession() {
        if (!isset($_SESSION['session_token']) || !isset($_SESSION['last_activity'])) {
            return false;
        }
        
        if (time() - $_SESSION['last_activity'] > 3600) {
            session_destroy();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
}
?>


html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile</title>
</head>
<body>
    <form id="profileForm" method="POST" action="../handlers/update_profile.php">
        <fieldset>
            <legend>Personal Information</legend>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" required maxlength="50">
            
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" required maxlength="50">
            
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required maxlength="255">
            
            <label for="phone">Phone:</label>
            <input type="tel" id="phone" name="phone" required maxlength="20">
        </fieldset>
        
        <fieldset>
            <legend>Address Information</legend>
            <label for="address_line_1">Address Line 1:</label>
            <input type="text" id="address_line_1" name="address_line_1" required maxlength="255">
            
            <label for="address_line_2">Address Line 2:</label>
            <input type="text" id="address_line_2" name="address_line_2" maxlength="255">
            
            <label for="city">City:</label>
            <input type="text" id="city" name="city" required maxlength="100">
            
            <label for="state">State/Province:</label>
            <input type="text" id="state" name="state" required maxlength="100">
            
            <label for="postal_code">Postal Code:</label>
            <input type="text" id="postal_code" name="postal_code" required maxlength="20">
            
            <label for="country">Country:</label>
            <input type="text" id="country" name="country" required maxlength="100">
        </fieldset>
        
        <fieldset>
            <legend>Billing Information</legend>
            <label for="billing_first_name">Billing First Name:</label>
            <input type="text" id="billing_first_name" name="billing_first_name" required maxlength="50">
            
            <label for="billing_last_name">Billing Last Name:</label>
            <input type="text" id="billing_last_name" name="billing_last_name" required maxlength="50">
            
            <label for="billing_address_line_1">Billing Address Line 1:</label>
            <input type="text" id="billing_address_line_1" name="billing_address_line_1" required maxlength="255">
            
            <label for="billing_address_line_2">Billing Address Line 2:</label>
            <input type="text" id="billing_address_line_2" name="billing_address_line_2" maxlength="255">
            
            <label for="billing_city">Billing City:</label>
            <input type="text" id="billing_city" name="billing_city" required maxlength="100">
            
            <label for="billing_state">Billing State/Province:</label>
            <input type="text" id="billing_state" name="billing_state" required maxlength="100">
            
            <label for="billing_postal_code">Billing Postal Code:</label>
            <input type="text" id="billing_postal_code" name="billing_postal_code" required maxlength="20">
            
            <label for="billing_country">Billing Country:</label>
            <input type="text" id="billing_country" name="billing_country" required maxlength="100">
        </fieldset>
        
        <button type="submit">Update Profile
?>