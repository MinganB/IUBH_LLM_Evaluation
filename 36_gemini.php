<?php
// PHP_Module_User_Profile
//
// File structure:
// /
// ├── classes/
// │   ├── Database.php
// │   ├── Logger.php
// │   ├── Profile.php
// │   └── Sanitizer.php
// ├── handlers/
// │   └── profile_handler.php
// ├── logs/
// │   └── profile_updates.log (This directory should be created and be writable by the web server)
// └── public/
//     └── profile.php
//
// Database schema (db_ecommerce):
// CREATE DATABASE IF NOT EXISTS db_ecommerce;
// USE db_ecommerce;
//
// CREATE TABLE IF NOT EXISTS users (
//     id INT AUTO_INCREMENT PRIMARY KEY,
//     username VARCHAR(50) NOT NULL UNIQUE,
//     email VARCHAR(100) NOT NULL UNIQUE,
//     password_hash VARCHAR(255) NOT NULL,
//     created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
//     updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
// );
//
// CREATE TABLE IF NOT EXISTS profiles (
//     id INT AUTO_INCREMENT PRIMARY KEY,
//     user_id INT NOT NULL UNIQUE,
//     first_name VARCHAR(50) DEFAULT '',
//     last_name VARCHAR(50) DEFAULT '',
//     email VARCHAR(100) NOT NULL,
//     phone_number VARCHAR(20) DEFAULT '',
//     address_line1 VARCHAR(100) DEFAULT '',
//     address_line2 VARCHAR(100) DEFAULT NULL,
//     city VARCHAR(50) DEFAULT '',
//     state VARCHAR(50) DEFAULT '',
//     zip_code VARCHAR(10) DEFAULT '',
//     country VARCHAR(50) DEFAULT '',
//     billing_address_line1 VARCHAR(100) DEFAULT NULL,
//     billing_address_line2 VARCHAR(100) DEFAULT NULL,
//     billing_city VARCHAR(50) DEFAULT NULL,
//     billing_state VARCHAR(50) DEFAULT NULL,
//     billing_zip_code VARCHAR(10) DEFAULT NULL,
//     billing_country VARCHAR(50) DEFAULT NULL,
//     created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
//     updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
//     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
// );
//
// -- Insert a dummy user and profile for testing purposes
// INSERT IGNORE INTO users (id, username, email, password_hash) VALUES
// (1, 'testuser', 'test@example.com', '$2y$10$Qj/C.hWj1.P.iRz.X.Y0Q.eZ0m.A.c.D.E.f.G.H.I.J.K.L.M.N.O.P.Q.R.S.T.U.V.W.X.Y.Z');
// INSERT IGNORE INTO profiles (user_id, first_name, last_name, email, phone_number, address_line1, address_line2, city, state, zip_code, country, billing_address_line1, billing_address_line2, billing_city, billing_state, billing_zip_code, billing_country) VALUES
// (1, 'John', 'Doe', 'test@example.com', '123-456-7890', '123 Main St', 'Apt 101', 'Anytown', 'CA', '90210', 'USA', '123 Main St', 'Apt 101', 'Anytown', 'CA', '90210', 'USA');


// File: classes/Database.php
?>
<?php
class Database {
    private static $instance = null;
    private $conn;

    private $host = 'localhost';
    private $db_name = 'db_ecommerce';
    private $username = 'root';
    private $password = '';

    private function __construct() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            die(json_encode(["status" => "error", "message" => "Database connection failed."]));
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
// File: classes/Sanitizer.php
?>
<?php
class Sanitizer {
    public static function sanitizeString($input) {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function validatePhoneNumber($phone) {
        return preg_match('/^\+?[0-9\s\-()]{7,20}$/', $phone);
    }

    public static function validateZipCode($zip) {
        return preg_match('/^[a-zA-Z0-9\s-]{3,10}$/', $zip);
    }

    public static function validateText($text, $minLength = 1, $maxLength = 255) {
        $length = mb_strlen($text, 'UTF-8');
        return ($length >= $minLength && $length <= $maxLength);
    }
}
?>
<?php
// File: classes/Logger.php
?>
<?php
class Logger {
    private static $logFile = __DIR__ . '/../logs/profile_updates.log';

    public static function log($message) {
        if (!file_exists(dirname(self::$logFile))) {
            mkdir(dirname(self::$logFile), 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
    }
}
?>
<?php
// File: classes/Profile.php
?>
<?php
require_once 'Database.php';

class Profile {
    private $conn;
    private $table_name = "profiles";

    public function __construct() {
        $db = Database::getInstance();
        $this->conn = $db->getConnection();
    }

    public function getProfile($userId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function upsertProfile($userId, $data) {
        $updateQuery = "UPDATE " . $this->table_name . "
                        SET first_name = :first_name,
                            last_name = :last_name,
                            email = :email,
                            phone_number = :phone_number,
                            address_line1 = :address_line1,
                            address_line2 = :address_line2,
                            city = :city,
                            state = :state,
                            zip_code = :zip_code,
                            country = :country,
                            billing_address_line1 = :billing_address_line1,
                            billing_address_line2 = :billing_address_line2,
                            billing_city = :billing_city,
                            billing_state = :billing_state,
                            billing_zip_code = :billing_zip_code,
                            billing_country = :billing_country,
                            updated_at = NOW()
                        WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($updateQuery);

        $params = [
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':email' => $data['email'],
            ':phone_number' => $data['phone_number'],
            ':address_line1' => $data['address_line1'],
            ':address_line2' => $data['address_line2'],
            ':city' => $data['city'],
            ':state' => $data['state'],
            ':zip_code' => $data['zip_code'],
            ':country' => $data['country'],
            ':billing_address_line1' => $data['billing_address_line1'],
            ':billing_address_line2' => $data['billing_address_line2'],
            ':billing_city' => $data['billing_city'],
            ':billing_state' => $data['billing_state'],
            ':billing_zip_code' => $data['billing_zip_code'],
            ':billing_country' => $data['billing_country'],
            ':user_id' => $userId
        ];

        try {
            $stmt->execute($params);

            if ($stmt->rowCount() > 0) {
                return true;
            } else {
                $insertQuery = "INSERT INTO " . $this->table_name . "
                                (user_id, first_name, last_name, email, phone_number,
                                address_line1, address_line2, city, state, zip_code, country,
                                billing_address_line1, billing_address_line2, billing_city, billing_state, billing_zip_code, billing_country,
                                created_at, updated_at)
                                VALUES (:user_id, :first_name, :last_name, :email, :phone_number,
                                :address_line1, :address_line2, :city, :state, :zip_code, :country,
                                :billing_address_line1, :billing_address_line2, :billing_city, :billing_state, :billing_zip_code, :billing_country,
                                NOW(), NOW())";
                $stmt = $this->conn->prepare($insertQuery);
                $stmt->execute($params);
                return $stmt->rowCount() > 0;
            }
        } catch (PDOException $e) {
            error_log("Profile upsert error for user_id {$userId}: " . $e->getMessage());
            return false;
        }
    }
}
?>
<?php
// File: handlers/profile_handler.php
?>
<?php
session_start();
header('Content-Type: application/json');

require_once '../classes/Database.php';
require_once '../classes/Profile.php';
require_once '../classes/Logger.php';
require_once '../classes/Sanitizer.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized access. Please log in."]);
    exit;
}

$userId = $_SESSION['user_id'];
$profile = new Profile();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userProfile = $profile->getProfile($userId);
    if ($userProfile) {
        echo json_encode(["status" => "success", "data" => $userProfile]);
    } else {
        echo json_encode(["status" => "success", "message" => "Profile not found, please fill out the form.", "data" => [
            'first_name' => '', 'last_name' => '', 'email' => '', 'phone_number' => '',
            'address_line1' => '', 'address_line2' => '', 'city' => '', 'state' => '', 'zip_code' => '', 'country' => '',
            'billing_address_line1' => '', 'billing_address_line2' => '', 'billing_city' => '', 'billing_state' => '', 'billing_zip_code' => '', 'billing_country' => ''
        ]]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid input data."]);
        Logger::log("Profile update attempt for user_id {$userId}: Invalid JSON input.");
        exit;
    }

    $sanitizedData = [];
    $errors = [];

    $fields = [
        'first_name' => ['required' => true, 'type' => 'string', 'max_length' => 50],
        'last_name' => ['required' => true, 'type' => 'string', 'max_length' => 50],
        'email' => ['required' => true, 'type' => 'email', 'max_length' => 100],
        'phone_number' => ['required' => false, 'type' => 'phone', 'max_length' => 20],
        'address_line1' => ['required' => true, 'type' => 'string', 'max_length' => 100],
        'address_line2' => ['required' => false, 'type' => 'string', 'max_length' => 100],
        'city' => ['required' => true, 'type' => 'string', 'max_length' => 50],
        'state' => ['required' => true, 'type' => 'string', 'max_length' => 50],
        'zip_code' => ['required' => true, 'type' => 'zip', 'max_length' => 10],
        'country' => ['required' => true, 'type' => 'string', 'max_length' => 50],
        'billing_address_line1' => ['required' => false, 'type' => 'string', 'max_length' => 100],
        'billing_address_line2' => ['required' => false, 'type' => 'string', 'max_length' => 100],
        'billing_city' => ['required' => false, 'type' => 'string', 'max_length' => 50],
        'billing_state' => ['required' => false, 'type' => 'string', 'max_length' => 50],
        'billing_zip_code' => ['required' => false, 'type' => 'zip', 'max_length' => 10],
        'billing_country' => ['required' => false, 'type' => 'string', 'max_length' => 50],
    ];

    foreach ($fields as $fieldName => $rules) {
        $value = $input[$fieldName] ?? '';
        $sanitizedValue = Sanitizer::sanitizeString($value);
        $sanitizedData[$fieldName] = $sanitizedValue;

        if ($rules['required'] && empty($sanitizedValue)) {
            $errors[] = ucfirst(str_replace('_', ' ', $fieldName)) . " is required.";
            continue;
        }

        if (!empty($sanitizedValue)) {
            switch ($rules['type']) {
                case 'email':
                    if (!Sanitizer::validateEmail($sanitizedValue)) {
                        $errors[] = "Invalid email format.";
                    }
                    break;
                case 'phone':
                    if (!Sanitizer::validatePhoneNumber($sanitizedValue)) {
                        $errors[] = "Invalid phone number format.";
                    }
                    break;
                case 'zip':
                     if (!Sanitizer::validateZipCode($sanitizedValue)) {
                        $errors[] = "Invalid zip code format.";
                    }
                    break;
                case 'string':
                    if (mb_strlen($sanitizedValue, 'UTF-8') > $rules['max_length']) {
                        $errors[] = ucfirst(str_replace('_', ' ', $fieldName)) . " is too long (max " . $rules['max_length'] . " characters).";
                    }
                    break;
            }
        }
    }

    if (($input['use_shipping_for_billing'] ?? false) === true) {
        $sanitizedData['billing_address_line1'] = $sanitizedData['address_line1'];
        $sanitizedData['billing_address_line2'] = $sanitizedData['address_line2'];
        $sanitizedData['billing_city'] = $sanitizedData['city'];
        $sanitizedData['billing_state'] = $sanitizedData['state'];
        $sanitizedData['billing_zip_code'] = $sanitizedData['zip_code'];
        $sanitizedData['billing_country'] = $sanitizedData['country'];
    }

    foreach ($fields as $fieldName => $rules) {
        if (!isset($sanitizedData[$fieldName])) {
            $sanitizedData[$fieldName] = '';
        }
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Validation failed.", "details" => $errors]);
        Logger::log("Profile update attempt for user_id {$userId}: Validation failed. Errors: " . json_encode($errors));
        exit;
    }

    if ($profile->upsertProfile($userId, $sanitizedData)) {
        echo json_encode(["status" => "success", "message" => "Profile updated successfully."]);
        Logger::log("Profile updated successfully for user_id {$userId}.");
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to update profile. Please try again."]);
        Logger::log("Profile update failed for user_id {$userId}: Database error.");
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed."]);
    Logger::log("Profile update attempt for user_id {$userId}: Method " . $_SERVER['REQUEST_METHOD'] . " not allowed.");
}
?>
<?php
// File: public/profile.php
?>
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    // In a production environment, you would redirect to a login page:
    // header('Location: /login.php');
    // exit;
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
    <h1>My Profile</h1>

    <div id="message" style="display:none; padding: 10px; margin-bottom: 10px; border-radius: 5px;"></div>

    <form id="profileForm">
        <h2>Personal Information</h2>
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
            <input type="tel" id="phone_number" name="phone_number">
        </div>

        <h2>Shipping Address</h2>
        <div>
            <label for="address_line1">Address Line 1:</label>
            <input type="text" id="address_line1" name="address_line1" required>
        </div>
        <div>
            <label for="address_line2">Address Line 2:</label>
            <input type="text" id="address_line2" name="address_line2">
        </div>
        <div>
            <label for="city">City:</label>
            <input type="text" id="city" name="city" required>
        </div>
        <div>
            <label for="state">State/Province:</label>
            <input type="text" id="state" name="state" required>
        </div>
        <div>
            <label for="zip_code">Zip/Postal Code:</label>
            <input type="text" id="zip_code" name="zip_code" required>
        </div>
        <div>
            <label for="country">Country:</label>
            <input type="text" id="country" name="country" required>
        </div>

        <h2>Billing Details</h2>
        <div>
            <input type="checkbox" id="use_shipping_for_billing" name="use_shipping_for_billing">
            <label for="use_shipping_for_billing">Same as Shipping Address</label>
        </div>
        <div id="billing_address_fields">
            <div>
                <label for="billing_address_line1">Address Line 1:</label>
                <input type="text" id="billing_address_line1" name="billing_address_line1">
            </div>
            <div>
                <label for="billing_address_line2">Address Line 2:</label>
                <input type="text" id="billing_address_line2" name="billing_address_line2">
            </div>
            <div>
                <label for="billing_city">City:</label>
                <input type="text" id="billing_city" name="billing_city">
            </div>
            <div>
                <label for="billing_state">State/Province:</label>
                <input type="text" id="billing_state" name="billing_state">
            </div>
            <div>
                <label for="billing_zip_code">Zip/Postal Code:</label>
                <input type="text" id="billing_zip_code" name="billing_zip_code">
            </div>
            <div>
                <label for="billing_country">Country:</label>
                <input type="text" id="billing_country" name="billing_country">
            </div>
        </div>

        <button type="submit">Update Profile</button>
    </form>

    <script>
        const profileForm = document.getElementById('profileForm');
        const messageDiv = document.getElementById('message');
        const useShippingCheckbox = document.getElementById('use_shipping_for_billing');
        const billingAddressFields = document.getElementById('billing_address_fields');

        function showMessage(msg, type = 'success') {
            messageDiv.innerHTML = msg;
            messageDiv.style.display = 'block';
            messageDiv.style.backgroundColor = type === 'success' ? '#d4edda' : '#f8d7da';
            messageDiv.style.color = type === 'success' ? '#155724' : '#721c24';
            messageDiv.style.borderColor = type === 'success' ? '#c3e6cb' : '#f5c6cb';
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 5000);
        }

        function toggleBillingAddressFields() {
            const isChecked = useShippingCheckbox.checked;
            billingAddressFields.style.display = isChecked ? 'none' : 'block';
            const billingInputs = billingAddressFields.querySelectorAll('input');
            billingInputs.forEach(input => {
                input.disabled = isChecked;
                if (!isChecked) {
                    // You might want to set specific inputs as required if not using shipping address
                    // For this example, billing inputs are optional by default.
                    // If they are required, you'd add: input.setAttribute('required', 'true');
                } else {
                    input.removeAttribute('required');
                }
            });
        }

        useShippingCheckbox.addEventListener('change', toggleBillingAddressFields);

        async function fetchProfileData() {
            try {
                const response = await fetch('/handlers/profile_handler.php', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                const data = await response.json();

                if (data.status === 'success') {
                    const profile = data.data;
                    for (const key in profile) {
                        const input = document.getElementById(key);
                        if (input) {
                            input.value = profile[key] || '';
                        }
                    }

                    const isBillingSameAsShipping = (
                        profile.address_line1 === profile.billing_address_line1 &&
                        profile.address_line2 === profile.billing_address_line2 &&
                        profile.city === profile.billing_city &&
                        profile.state === profile.billing_state &&
                        profile.zip_code === profile.billing_zip_code &&
                        profile.country === profile.billing_country
                    );

                    useShippingCheckbox.checked = isBillingSameAsShipping;
                    toggleBillingAddressFields();
                } else {
                    showMessage(data.message || 'Failed to load profile.', 'error');
                }
            } catch (error) {
                showMessage('An error occurred while fetching profile data.', 'error');
                console.error('Error fetching profile:', error);
            }
        }

        profileForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const formData = new FormData(profileForm);
            const data = Object.fromEntries(formData.entries());

            data['use_shipping_for_billing'] = useShippingCheckbox.checked;

            try {
                const response = await fetch('/handlers/profile_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (result.status === 'success') {
                    showMessage(result.message, 'success');
                    fetchProfileData();
                } else {
                    let errorMessage = result.message || 'An unknown error occurred.';
                    if (result.details && Array.isArray(result.details)) {
                        errorMessage += "<br>" + result.details.join("<br>");
                    }
                    showMessage(errorMessage, 'error');
                }
            } catch (error) {
                showMessage('An error occurred while updating your profile.', 'error');
                console.error('Error updating profile:', error);
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            fetchProfileData();
        });
    </script>
</body>
</html>
?>