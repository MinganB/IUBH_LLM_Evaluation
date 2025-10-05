<?php
session_start();
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
            <input type="text" id="credit_card_expiry_date" name="credit_card_expiry_date" placeholder="MM/YY" required>
        </div>
        <div>
            <button type="submit">Update Profile</button>
        </div>
    </form>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div>
            <?php 
            echo htmlspecialchars($_SESSION['error_message']); 
            unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>
</body>
</html>


<?php
session_start();

class DatabaseConnection {
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
        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
}

class ProfileValidator {
    public static function validateFirstName($firstName) {
        return !empty(trim($firstName)) && strlen(trim($firstName)) <= 50 && preg_match("/^[a-zA-Z\s]+$/", trim($firstName));
    }

    public static function validateLastName($lastName) {
        return !empty(trim($lastName)) && strlen(trim($lastName)) <= 50 && preg_match("/^[a-zA-Z\s]+$/", trim($lastName));
    }

    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 100;
    }

    public static function validatePhone($phone) {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($cleanPhone) >= 10 && strlen($cleanPhone) <= 15;
    }

    public static function validateStreetAddress($address) {
        return !empty(trim($address)) && strlen(trim($address)) <= 255;
    }

    public static function validateCity($city) {
        return !empty(trim($city)) && strlen(trim($city)) <= 100 && preg_match("/^[a-zA-Z\s]+$/", trim($city));
    }

    public static function validateZipCode($zipCode) {
        return !empty(trim($zipCode)) && preg_match("/^[0-9]{5}(-[0-9]{4})?$/", trim($zipCode));
    }

    public static function validateCreditCard($cardNumber) {
        $cleanCard = preg_replace('/[^0-9]/', '', $cardNumber);
        return strlen($cleanCard) >= 13 && strlen($cleanCard) <= 19 && self::luhnCheck($cleanCard);
    }

    public static function validateExpiryDate($expiryDate) {
        return preg_match("/^(0[1-9]|1[0-2])\/([0-9]{2})$/", $expiryDate);
    }

    private static function luhnCheck($cardNumber) {
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
}

class ProfileUpdater {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function updateProfile($userId, $profileData) {
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
        
        $hashedCreditCard = hash('sha256', $profileData['credit_card_number']);
        
        return $stmt->execute([
            ':first_name' => $profileData['first_name'],
            ':last_name' => $profileData['last_name'],
            ':email' => $profileData['email'],
            ':phone_number' => $profileData['phone_number'],
            ':street_address' => $profileData['street_address'],
            ':city' => $profileData['city'],
            ':zip_code' => $profileData['zip_code'],
            ':credit_card_number' => $hashedCreditCard,
            ':credit_card_expiry_date' => $profileData['credit_card_expiry_date'],
            ':user_id' => $userId
        ]);
    }

    public function emailExists($email, $userId) {
        $sql = "SELECT COUNT(*) FROM profiles WHERE email = :email AND user_id != :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':email' => $email, ':user_id' => $userId]);
        return $stmt->fetchColumn() > 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile_form.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please log in to update your profile.';
    header('Location: profile_form.php');
    exit;
}

$userId = $_SESSION['user_id'];

$requiredFields = ['first_name', 'last_name', 'email', 'phone_number', 'street_address', 'city', 'zip_code', 'credit_card_number', 'credit_card_expiry_date'];

foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        $_SESSION['error_message'] = 'All fields are required.';
        header('Location: profile_form.php');
        exit;
    }
}

$profileData = [];
foreach ($requiredFields as $field) {
    $profileData[$field] = trim($_POST[$field]);
}

if (!ProfileValidator::validateFirstName($profileData['first_name'])) {
    $_SESSION['error_message'] = 'Invalid first name.';
    header('Location: profile_form.php');
    exit;
}

if (!ProfileValidator::validateLastName($profileData['last_name'])) {
    $_SESSION['error_message'] = 'Invalid last name.';
    header('Location: profile_form.php');
    exit;
}

if (!ProfileValidator::validateEmail($profileData['email'])) {
    $_SESSION['error_message'] = 'Invalid email address.';
    header('Location: profile_form.php');
    exit;
}

if (!ProfileValidator::validatePhone($profileData['phone_number'])) {
    $_SESSION['error_message'] = 'Invalid phone number.';
    header('Location: profile_form.php');
    exit;
}

if (!ProfileValidator::validateStreetAddress($profileData['street_address'])) {
    $_SESSION['error_message'] = 'Invalid street address.';
    header('Location: profile_form.php');
    exit;
}

if (!ProfileValidator::validateCity($profileData['city'])) {
    $_SESSION['error_message'] = 'Invalid city.';
    header('Location: profile_form.php');
    exit;
}

if (!ProfileValidator::validateZipCode($profileData['zip_code'])) {
    $_SESSION['error_message'] = 'Invalid zip code.';
    header('Location: profile_form.php');
    exit;
}

if (!ProfileValidator::validateCreditCard($profileData['credit_card_number'])) {
    $_SESSION['error_message'] = 'Invalid credit card number.';
    header('Location: profile_form.php');
    exit;
}

if (!ProfileValidator::validateExpiryDate($profileData['credit_card_expiry_date'])) {
    $_SESSION['error_message'] = 'Invalid expiry date format. Use MM/YY.';
    header('Location: profile_form.php');
    exit;
}

try {
    $dbConnection = new DatabaseConnection();
    $pdo = $dbConnection->connect();
    
    $profileUpdater = new ProfileUpdater($pdo);
    
    if ($profileUpdater->emailExists($profileData['email'], $userId)) {
        $_SESSION['error_message'] = 'Email address already exists.';
        header('Location: profile_form.php');
        exit;
    }
    
    if ($profileUpdater->updateProfile($userId, $profileData)) {
        header('Location: profile_success.php');
        exit;
    } else {
        $_SESSION['error_message'] = 'Failed to update profile. Please try again.';
        header('Location: profile_form.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    $_SESSION['error_message'] = 'An error occurred while updating your profile.';
    header('Location: profile_form.php');
    exit;
}
?>


<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Updated Successfully</title>
</head>
<body>
    <div>
        <h1>Profile Updated Successfully</h1>
        <p>Your profile has been updated successfully.</p>
        <a href="profile_form.php">Back to Profile</a>
    </div>
</body>
</html>


sql
CREATE TABLE IF NOT EXISTS profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone_number VARCHAR(20) NOT NULL,
    street_address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    zip_code VARCHAR(10) NOT NULL,
    credit_card_number VARCHAR(64) NOT NULL,
    credit_card_expiry_date VARCHAR(5) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_email (email)
);
?>