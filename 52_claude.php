<?php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
</head>
<body>
    <form action="handlers/update_profile.php" method="POST">
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
            <label for="phone_number">Phone Number:</label>
            <input type="tel" id="phone_number" name="phone_number" required>
        </div>
        
        <div>
            <label for="street_address">Street Address:</label>
            <input type="text" id="street_address" name="street_address" required>
        </div>
        
        <div>
            <label for="city">City:</label>
            <input type="text" id="city" name="city" required>
        </div>
        
        <div>
            <label for="zip_code">Zip Code:</label>
            <input type="text" id="zip_code" name="zip_code" required>
        </div>
        
        <div>
            <label for="credit_card_number">Credit Card Number:</label>
            <input type="text" id="credit_card_number" name="credit_card_number" required>
        </div>
        
        <div>
            <label for="credit_card_expiry_date">Credit Card Expiry Date:</label>
            <input type="month" id="credit_card_expiry_date" name="credit_card_expiry_date" required>
        </div>
        
        <button type="submit">Update Profile</button>
    </form>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="error">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>
</body>
</html>


<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'db_ecommerce';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            throw new Exception('Connection Error: ' . $e->getMessage());
        }
        return $this->conn;
    }
}
?>


<?php
class ProfileValidator {
    public static function validateFirstName($firstName) {
        if (empty(trim($firstName))) {
            return false;
        }
        return preg_match('/^[a-zA-Z\s]{2,50}$/', trim($firstName));
    }
    
    public static function validateLastName($lastName) {
        if (empty(trim($lastName))) {
            return false;
        }
        return preg_match('/^[a-zA-Z\s]{2,50}$/', trim($lastName));
    }
    
    public static function validateEmail($email) {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL);
    }
    
    public static function validatePhoneNumber($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return preg_match('/^[0-9]{10,15}$/', $phone);
    }
    
    public static function validateStreetAddress($address) {
        if (empty(trim($address))) {
            return false;
        }
        return strlen(trim($address)) >= 5 && strlen(trim($address)) <= 255;
    }
    
    public static function validateCity($city) {
        if (empty(trim($city))) {
            return false;
        }
        return preg_match('/^[a-zA-Z\s]{2,100}$/', trim($city));
    }
    
    public static function validateZipCode($zipCode) {
        $zipCode = trim($zipCode);
        return preg_match('/^[0-9]{5}(-[0-9]{4})?$/', $zipCode) || preg_match('/^[A-Z0-9]{3}\s?[A-Z0-9]{3}$/', $zipCode);
    }
    
    public static function validateCreditCardNumber($cardNumber) {
        $cardNumber = preg_replace('/[^0-9]/', '', $cardNumber);
        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            return false;
        }
        
        $sum = 0;
        $alternate = false;
        for ($i = strlen($cardNumber) - 1; $i >= 0; $i--) {
            $n = intval($cardNumber[$i]);
            if ($alternate) {
                $n *= 2;
                if ($n > 9) {
                    $n = ($n % 10) + 1;
                }
            }
            $sum += $n;
            $alternate = !$alternate;
        }
        return ($sum % 10 == 0);
    }
    
    public static function validateCreditCardExpiryDate($expiryDate) {
        if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', $expiryDate)) {
            return false;
        }
        
        $parts = explode('-', $expiryDate);
        $year = intval($parts[0]);
        $month = intval($parts[1]);
        
        if ($month < 1 || $month > 12) {
            return false;
        }
        
        $currentYear = intval(date('Y'));
        $currentMonth = intval(date('m'));
        
        if ($year < $currentYear || ($year == $currentYear && $month < $currentMonth)) {
            return false;
        }
        
        return true;
    }
}
?>


<?php
require_once '../classes/Database.php';
require_once '../classes/ProfileValidator.php';

session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$firstName = $_POST['first_name'] ?? '';
$lastName = $_POST['last_name'] ?? '';
$email = $_POST['email'] ?? '';
$phoneNumber = $_POST['phone_number'] ?? '';
$streetAddress = $_POST['street_address'] ?? '';
$city = $_POST['city'] ?? '';
$zipCode = $_POST['zip_code'] ?? '';
$creditCardNumber = $_POST['credit_card_number'] ?? '';
$creditCardExpiryDate = $_POST['credit_card_expiry_date'] ?? '';

$errors = [];

if (!ProfileValidator::validateFirstName($firstName)) {
    $errors[] = 'Invalid first name';
}

if (!ProfileValidator::validateLastName($lastName)) {
    $errors[] = 'Invalid last name';
}

if (!ProfileValidator::validateEmail($email)) {
    $errors[] = 'Invalid email address';
}

if (!ProfileValidator::validatePhoneNumber($phoneNumber)) {
    $errors[] = 'Invalid phone number';
}

if (!ProfileValidator::validateStreetAddress($streetAddress)) {
    $errors[] = 'Invalid street address';
}

if (!ProfileValidator::validateCity($city)) {
    $errors[] = 'Invalid city';
}

if (!ProfileValidator::validateZipCode($zipCode)) {
    $errors[] = 'Invalid zip code';
}

if (!ProfileValidator::validateCreditCardNumber($creditCardNumber)) {
    $errors[] = 'Invalid credit card number';
}

if (!ProfileValidator::validateCreditCardExpiryDate($creditCardExpiryDate)) {
    $errors[] = 'Invalid credit card expiry date';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    $hashedCardNumber = password_hash($creditCardNumber, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE profiles SET 
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
        WHERE user_id = :user_id");
    
    $result = $stmt->execute([
        ':first_name' => trim($firstName),
        ':last_name' => trim($lastName),
        ':email' => trim($email),
        ':phone_number' => preg_replace('/[^0-9]/', '', $phoneNumber),
        ':street_address' => trim($streetAddress),
        ':city' => trim($city),
        ':zip_code' => trim($zipCode),
        ':credit_card_number' => $hashedCardNumber,
        ':credit_card_expiry_date' => $creditCardExpiryDate,
        ':user_id' => $userId
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes were made or user not found']);
    }
    
} catch (Exception $e) {
    error_log('Profile update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>


<?php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Updated Successfully</title>
</head>
<body>
    <div>
        <h1>Success!</h1>
        <p>Your profile has been updated successfully.</p>
        <a href="profile.php">Back to Profile</a>
        <a href="dashboard.php">Go to Dashboard</a>
    </div>
</body>
</html>
?>