<?php

// File: classes/Database.php

class Database {
    private static $instance = null;
    private $conn;

    private $host = 'localhost';
    private $db_name = 'db_ecommerce';
    private $username = 'root';
    private $password = '';

    private function __construct() {
        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Database connection failed.");
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}

?>
<?php

// File: classes/Validator.php

class Validator {
    public static function validateString(string $value, int $minLength = 1, int $maxLength = 255): bool {
        $trimmedValue = trim($value);
        if (strlen($trimmedValue) < $minLength || strlen($trimmedValue) > $maxLength) {
            return false;
        }
        return preg_match('/^[a-zA-Z0-9\s.,\-\'\/_]+$/', $trimmedValue);
    }

    public static function validateName(string $value, int $minLength = 1, int $maxLength = 255): bool {
        $trimmedValue = trim($value);
        if (strlen($trimmedValue) < $minLength || strlen($trimmedValue) > $maxLength) {
            return false;
        }
        return preg_match('/^[a-zA-Z\s\-\']+$/', $trimmedValue);
    }

    public static function validateEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validatePhoneNumber(string $phoneNumber): bool {
        return preg_match('/^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/', $phoneNumber);
    }

    public static function validateZipCode(string $zipCode): bool {
        return preg_match('/^\d{5}(?:[-\s]\d{4})?$|^[A-Za-z]\d[A-Za-z][-\s]?\d[A-Za-z]\d$/', $zipCode);
    }

    public static function validateCreditCardNumber(string $cardNumber): bool {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);
        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            return false;
        }
        return ctype_digit($cardNumber);
    }

    public static function validateCreditCardExpiryDate(string $expiryDate): bool {
        if (!preg_match('/^(0[1-9]|1[0-2])\/?([0-9]{2}|[0-9]{4})$/', $expiryDate, $matches)) {
            return false;
        }

        $month = (int)$matches[1];
        $year = (int)$matches[2];

        if (strlen($matches[2]) === 2) {
            $currentCentury = floor(date('Y') / 100) * 100;
            $year = $currentCentury + $year;
            if ($year < date('Y') - 10) {
                $year += 100;
            }
        }
        
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('m');

        if ($year < $currentYear) {
            return false;
        }
        if ($year === $currentYear && $month < $currentMonth) {
            return false;
        }
        if ($year > $currentYear + 20) {
            return false;
        }

        return true;
    }
}

?>
<?php

// File: classes/UserProfileManager.php

class UserProfileManager {
    private $db;

    public function __construct(PDO $pdo) {
        $this->db = $pdo;
    }

    public function updateProfile(int $userId, array $profileData): bool {
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

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':first_name', $profileData['first_name']);
            $stmt->bindValue(':last_name', $profileData['last_name']);
            $stmt->bindValue(':email', $profileData['email']);
            $stmt->bindValue(':phone_number', $profileData['phone_number']);
            $stmt->bindValue(':street_address', $profileData['street_address']);
            $stmt->bindValue(':city', $profileData['city']);
            $stmt->bindValue(':zip_code', $profileData['zip_code']);
            $stmt->bindValue(':credit_card_number', $profileData['credit_card_number']);
            $stmt->bindValue(':credit_card_expiry_date', $profileData['credit_card_expiry_date']);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Profile update failed for user_id {$userId}: " . $e->getMessage());
            return false;
        }
    }

    public function getProfileById(int $userId): ?array {
        $sql = "SELECT first_name, last_name, email, phone_number, street_address, city, zip_code, credit_card_number, credit_card_expiry_date FROM profiles WHERE user_id = :user_id";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            return $profile !== false ? $profile : null;
        } catch (PDOException $e) {
            error_log("Failed to retrieve profile for user_id {$userId}: " . $e->getMessage());
            return null;
        }
    }
}

?>
<?php

// File: handlers/update_profile.php

session_start();

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Validator.php';
require_once __DIR__ . '/../classes/UserProfileManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: /public/profile.php');
    exit();
}

$userId = $_POST['user_id'] ?? null; 
if (!$userId || !is_numeric($userId)) {
    $_SESSION['error_message'] = 'User ID is missing or invalid.';
    header('Location: /public/profile.php');
    exit();
}
$userId = (int)$userId;

$profileData = [
    'first_name' => trim($_POST['first_name'] ?? ''),
    'last_name' => trim($_POST['last_name'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
    'phone_number' => trim($_POST['phone_number'] ?? ''),
    'street_address' => trim($_POST['street_address'] ?? ''),
    'city' => trim($_POST['city'] ?? ''),
    'zip_code' => trim($_POST['zip_code'] ?? ''),
    'credit_card_number' => trim($_POST['credit_card_number'] ?? ''),
    'credit_card_expiry_date' => trim($_POST['credit_card_expiry_date'] ?? ''),
];

$errors = [];

if (!Validator::validateName($profileData['first_name'])) {
    $errors['first_name'] = 'First name is invalid.';
}
if (!Validator::validateName($profileData['last_name'])) {
    $errors['last_name'] = 'Last name is invalid.';
}
if (!Validator::validateEmail($profileData['email'])) {
    $errors['email'] = 'Email is invalid.';
}
if (!Validator::validatePhoneNumber($profileData['phone_number'])) {
    $errors['phone_number'] = 'Phone number is invalid.';
}
if (!Validator::validateString($profileData['street_address'], 5)) {
    $errors['street_address'] = 'Street address is invalid.';
}
if (!Validator::validateName($profileData['city'])) {
    $errors['city'] = 'City is invalid.';
}
if (!Validator::validateZipCode($profileData['zip_code'])) {
    $errors['zip_code'] = 'Zip code is invalid.';
}
if (!Validator::validateCreditCardNumber($profileData['credit_card_number'])) {
    $errors['credit_card_number'] = 'Credit card number is invalid.';
}
if (!Validator::validateCreditCardExpiryDate($profileData['credit_card_expiry_date'])) {
    $errors['credit_card_expiry_date'] = 'Credit card expiry date is invalid (MM/YY or MM/YYYY).';
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['old_input'] = $profileData;
    $_SESSION['error_message'] = 'Please correct the errors below.';
    header('Location: /public/profile.php');
    exit();
}

try {
    $database = Database::getInstance();
    $pdo = $database->getConnection();
    $profileManager = new UserProfileManager($pdo);

    $success = $profileManager->updateProfile($userId, $profileData);

    if ($success) {
        $_SESSION['success_message'] = 'Profile updated successfully!';
        header('Location: /public/profile_success.php');
        exit();
    } else {
        $_SESSION['error_message'] = 'Profile update failed. No changes or user not found.';
        header('Location: /public/profile.php');
        exit();
    }
} catch (Exception $e) {
    error_log("Update profile exception: " . $e->getMessage());
    $_SESSION['error_message'] = 'An unexpected error occurred during profile update.';
    header('Location: /public/profile.php');
    exit();
}

?>
<?php

// File: public/profile.php

session_start();

$userId = 1;

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/UserProfileManager.php';

$profileData = [];
$errorMessage = $_SESSION['error_message'] ?? '';
$formErrors = $_SESSION['form_errors'] ?? [];
$oldInput = $_SESSION['old_input'] ?? [];

unset($_SESSION['error_message']);
unset($_SESSION['form_errors']);
unset($_SESSION['old_input']);

try {
    $database = Database::getInstance();
    $pdo = $database->getConnection();
    $profileManager = new UserProfileManager($pdo);
    $existingProfile = $profileManager->getProfileById($userId);

    if ($existingProfile) {
        $profileData = array_merge($existingProfile, $oldInput);
    } else {
        $profileData = $oldInput;
    }
} catch (Exception $e) {
    error_log("Error loading profile data: " . $e->getMessage());
    $errorMessage = "Could not load existing profile data.";
}

function displayError(string $fieldName, array $errors): void {
    if (isset($errors[$fieldName])) {
        echo '<p style="color: red; font-size: 0.9em; margin: 0 0 5px 0;">' . htmlspecialchars($errors[$fieldName]) . '</p>';
    }
}

function getOldValue(string $fieldName, array $data): string {
    return htmlspecialchars($data[$fieldName] ?? '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User Profile</title>
</head>
<body>
    <h1>Edit User Profile</h1>

    <?php if ($errorMessage): ?>
        <p style="color: red; font-weight: bold;"><?php echo htmlspecialchars($errorMessage); ?></p>
    <?php endif; ?>

    <form action="/handlers/update_profile.php" method="POST">
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($userId); ?>">

        <label for="first_name">First Name:</label><br>
        <input type="text" id="first_name" name="first_name" value="<?php echo getOldValue('first_name', $profileData); ?>" required><br>
        <?php displayError('first_name', $formErrors); ?>

        <label for="last_name">Last Name:</label><br>
        <input type="text" id="last_name" name="last_name" value="<?php echo getOldValue('last_name', $profileData); ?>" required><br>
        <?php displayError('last_name', $formErrors); ?>

        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" value="<?php echo getOldValue('email', $profileData); ?>" required><br>
        <?php displayError('email', $formErrors); ?>

        <label for="phone_number">Phone Number:</label><br>
        <input type="tel" id="phone_number" name="phone_number" value="<?php echo getOldValue('phone_number', $profileData); ?>"><br>
        <?php displayError('phone_number', $formErrors); ?>

        <label for="street_address">Street Address:</label><br>
        <input type="text" id="street_address" name="street_address" value="<?php echo getOldValue('street_address', $profileData); ?>"><br>
        <?php displayError('street_address', $formErrors); ?>

        <label for="city">City:</label><br>
        <input type="text" id="city" name="city" value="<?php echo getOldValue('city', $profileData); ?>"><br>
        <?php displayError('city', $formErrors); ?>

        <label for="zip_code">Zip Code:</label><br>
        <input type="text" id="zip_code" name="zip_code" value="<?php echo getOldValue('zip_code', $profileData); ?>"><br>
        <?php displayError('zip_code', $formErrors); ?>

        <h2>Payment Information</h2>
        <label for="credit_card_number">Credit Card Number:</label><br>
        <input type="text" id="credit_card_number" name="credit_card_number" value="<?php echo getOldValue('credit_card_number', $profileData); ?>" autocomplete="cc-number" placeholder="XXXX XXXX XXXX XXXX"><br>
        <?php displayError('credit_card_number', $formErrors); ?>

        <label for="credit_card_expiry_date">Expiry Date (MM/YYYY):</label><br>
        <input type="text" id="credit_card_expiry_date" name="credit_card_expiry_date" value="<?php echo getOldValue('credit_card_expiry_date', $profileData); ?>" autocomplete="cc-exp" placeholder="MM/YYYY"><br>
        <?php displayError('credit_card_expiry_date', $formErrors); ?>

        <br>
        <button type="submit">Update Profile</button>
    </form>
</body>
</html>
<?php

// File: public/profile_success.php

session_start();

$successMessage = $_SESSION['success_message'] ?? 'Your profile has been updated successfully.';
unset($_SESSION['success_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Update Success</title>
</head>
<body>
    <h1>Profile Updated!</h1>
    <p style="color: green; font-weight: bold;"><?php echo htmlspecialchars($successMessage); ?></p>
    <p><a href="/public/profile.php">Go back to profile</a></p>
</body>
</html>
?>