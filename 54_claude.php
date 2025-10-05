<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile</title>
</head>
<body>
    <form action="update_profile.php" method="POST">
        <label for="first_name">First Name:</label>
        <input type="text" id="first_name" name="first_name" required>
        
        <label for="last_name">Last Name:</label>
        <input type="text" id="last_name" name="last_name" required>
        
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        
        <label for="phone_number">Phone Number:</label>
        <input type="tel" id="phone_number" name="phone_number" required>
        
        <label for="street_address">Street Address:</label>
        <input type="text" id="street_address" name="street_address" required>
        
        <label for="city">City:</label>
        <input type="text" id="city" name="city" required>
        
        <label for="zip_code">Zip Code:</label>
        <input type="text" id="zip_code" name="zip_code" required>
        
        <label for="credit_card_number">Credit Card Number:</label>
        <input type="text" id="credit_card_number" name="credit_card_number" required>
        
        <label for="credit_card_expiry_date">Credit Card Expiry Date:</label>
        <input type="month" id="credit_card_expiry_date" name="credit_card_expiry_date" required>
        
        <button type="submit">Update Profile</button>
    </form>
    
    <?php if (isset($_GET['error'])): ?>
        <div id="error-message"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>
</body>
</html>


<?php
session_start();
require_once '../classes/DatabaseConnection.php';
require_once '../classes/ProfileValidator.php';
require_once '../classes/ProfileUpdater.php';
require_once '../classes/Logger.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$logger = new Logger();

try {
    $validator = new ProfileValidator();
    $validation_result = $validator->validate($_POST);
    
    if (!$validation_result['valid']) {
        $logger->log("Profile update failed - validation error for user ID: $user_id");
        header('Location: profile_form.php?error=' . urlencode($validation_result['error']));
        exit;
    }
    
    $db = new DatabaseConnection();
    $updater = new ProfileUpdater($db->getConnection());
    
    $update_result = $updater->updateProfile($user_id, $validation_result['data']);
    
    if ($update_result['success']) {
        $logger->log("Profile updated successfully for user ID: $user_id");
        header('Location: profile_success.php');
        exit;
    } else {
        $logger->log("Profile update failed for user ID: $user_id - Database error");
        header('Location: profile_form.php?error=Update failed');
        exit;
    }
    
} catch (Exception $e) {
    $logger->log("Profile update exception for user ID: $user_id - " . $e->getMessage());
    header('Location: profile_form.php?error=An error occurred');
    exit;
}
?>


<?php
class DatabaseConnection {
    private $host = 'localhost';
    private $dbname = 'db_ecommerce';
    private $username = 'db_user';
    private $password = 'db_password';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            throw new Exception('Database connection failed');
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}
?>


<?php
class ProfileValidator {
    public function validate($data) {
        $errors = [];
        $clean_data = [];
        
        $clean_data['first_name'] = $this->validateName($data['first_name'] ?? '', 'First name');
        if (!$clean_data['first_name']) $errors[] = 'Invalid first name';
        
        $clean_data['last_name'] = $this->validateName($data['last_name'] ?? '', 'Last name');
        if (!$clean_data['last_name']) $errors[] = 'Invalid last name';
        
        $clean_data['email'] = $this->validateEmail($data['email'] ?? '');
        if (!$clean_data['email']) $errors[] = 'Invalid email address';
        
        $clean_data['phone_number'] = $this->validatePhone($data['phone_number'] ?? '');
        if (!$clean_data['phone_number']) $errors[] = 'Invalid phone number';
        
        $clean_data['street_address'] = $this->validateAddress($data['street_address'] ?? '');
        if (!$clean_data['street_address']) $errors[] = 'Invalid street address';
        
        $clean_data['city'] = $this->validateName($data['city'] ?? '', 'City');
        if (!$clean_data['city']) $errors[] = 'Invalid city';
        
        $clean_data['zip_code'] = $this->validateZipCode($data['zip_code'] ?? '');
        if (!$clean_data['zip_code']) $errors[] = 'Invalid zip code';
        
        $clean_data['credit_card_number'] = $this->validateCreditCard($data['credit_card_number'] ?? '');
        if (!$clean_data['credit_card_number']) $errors[] = 'Invalid credit card number';
        
        $clean_data['credit_card_expiry_date'] = $this->validateExpiryDate($data['credit_card_expiry_date'] ?? '');
        if (!$clean_data['credit_card_expiry_date']) $errors[] = 'Invalid expiry date';
        
        if (!empty($errors)) {
            return ['valid' => false, 'error' => implode(', ', $errors)];
        }
        
        return ['valid' => true, 'data' => $clean_data];
    }
    
    private function validateName($name, $field) {
        $name = trim(htmlspecialchars($name, ENT_QUOTES, 'UTF-8'));
        if (strlen($name) < 1 || strlen($name) > 50 || !preg_match('/^[a-zA-Z\s\-\']+$/', $name)) {
            return false;
        }
        return $name;
    }
    
    private function validateEmail($email) {
        $email = trim(htmlspecialchars($email, ENT_QUOTES, 'UTF-8'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            return false;
        }
        return $email;
    }
    
    private function validatePhone($phone) {
        $phone = preg_replace('/[^0-9+\-\(\)\s]/', '', $phone);
        if (strlen($phone) < 10 || strlen($phone) > 20) {
            return false;
        }
        return $phone;
    }
    
    private function validateAddress($address) {
        $address = trim(htmlspecialchars($address, ENT_QUOTES, 'UTF-8'));
        if (strlen($address) < 5 || strlen($address) > 200) {
            return false;
        }
        return $address;
    }
    
    private function validateZipCode($zip) {
        $zip = trim($zip);
        if (!preg_match('/^[0-9]{5}(-[0-9]{4})?$/', $zip)) {
            return false;
        }
        return $zip;
    }
    
    private function validateCreditCard($cc) {
        $cc = preg_replace('/[^0-9]/', '', $cc);
        if (strlen($cc) < 13 || strlen($cc) > 19) {
            return false;
        }
        return $this->encryptCreditCard($cc);
    }
    
    private function validateExpiryDate($date) {
        if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', $date)) {
            return false;
        }
        $timestamp = strtotime($date . '-01');
        if ($timestamp < time()) {
            return false;
        }
        return $date;
    }
    
    private function encryptCreditCard($cc) {
        $key = 'your-encryption-key-here';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($cc, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
}
?>


<?php
class ProfileUpdater {
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function updateProfile($user_id, $data) {
        try {
            $sql = "UPDATE profiles SET 
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    phone_number = :phone_number,
                    street_address = :street_address,
                    city = :city,
                    zip_code = :zip_code,
                    credit_card_number = :credit_card_number,
                    credit_card_expiry_date = :credit_card_expiry_date,
                    updated_at = NOW()
                    WHERE user_id = :user_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':first_name', $data['first_name'], PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $data['last_name'], PDO::PARAM_STR);
            $stmt->bindParam(':email', $data['email'], PDO::PARAM_STR);
            $stmt->bindParam(':phone_number', $data['phone_number'], PDO::PARAM_STR);
            $stmt->bindParam(':street_address', $data['street_address'], PDO::PARAM_STR);
            $stmt->bindParam(':city', $data['city'], PDO::PARAM_STR);
            $stmt->bindParam(':zip_code', $data['zip_code'], PDO::PARAM_STR);
            $stmt->bindParam(':credit_card_number', $data['credit_card_number'], PDO::PARAM_STR);
            $stmt->bindParam(':credit_card_expiry_date', $data['credit_card_expiry_date'], PDO::PARAM_STR);
            
            $result = $stmt->execute();
            
            return ['success' => $result && $stmt->rowCount() > 0];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Database error'];
        }
    }
}
?>


<?php
class Logger {
    private $log_file;
    
    public function __construct() {
        $this->log_file = '../logs/profile_updates.log';
    }
    
    public function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
        
        if (!file_exists(dirname($this->log_file))) {
            mkdir(dirname($this->log_file), 0755, true);
        }
        
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}
?>


<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Updated Successfully</title>
</head>
<body>
    <h1>Profile Updated Successfully</h1>
    <p>Your profile has been updated successfully.</p>
    <a href="dashboard.php">Return to Dashboard</a>
</body>
</html>
?>