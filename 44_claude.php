<?php
// /classes/Database.php
class Database {
    private $host = 'localhost';
    private $db_name = 'db_ecommerce';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>


<?php
// /classes/Profile.php
class Profile {
    private $conn;
    private $table_name = "profiles";

    public $id;
    public $user_id;
    public $first_name;
    public $last_name;
    public $email;
    public $phone;
    public $address_line1;
    public $address_line2;
    public $city;
    public $state;
    public $postal_code;
    public $country;
    public $billing_first_name;
    public $billing_last_name;
    public $billing_address_line1;
    public $billing_address_line2;
    public $billing_city;
    public $billing_state;
    public $billing_postal_code;
    public $billing_country;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                 SET first_name = :first_name, last_name = :last_name, email = :email, 
                     phone = :phone, address_line1 = :address_line1, address_line2 = :address_line2,
                     city = :city, state = :state, postal_code = :postal_code, country = :country,
                     billing_first_name = :billing_first_name, billing_last_name = :billing_last_name,
                     billing_address_line1 = :billing_address_line1, billing_address_line2 = :billing_address_line2,
                     billing_city = :billing_city, billing_state = :billing_state, 
                     billing_postal_code = :billing_postal_code, billing_country = :billing_country
                 WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($query);

        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->address_line1 = htmlspecialchars(strip_tags($this->address_line1));
        $this->address_line2 = htmlspecialchars(strip_tags($this->address_line2));
        $this->city = htmlspecialchars(strip_tags($this->city));
        $this->state = htmlspecialchars(strip_tags($this->state));
        $this->postal_code = htmlspecialchars(strip_tags($this->postal_code));
        $this->country = htmlspecialchars(strip_tags($this->country));
        $this->billing_first_name = htmlspecialchars(strip_tags($this->billing_first_name));
        $this->billing_last_name = htmlspecialchars(strip_tags($this->billing_last_name));
        $this->billing_address_line1 = htmlspecialchars(strip_tags($this->billing_address_line1));
        $this->billing_address_line2 = htmlspecialchars(strip_tags($this->billing_address_line2));
        $this->billing_city = htmlspecialchars(strip_tags($this->billing_city));
        $this->billing_state = htmlspecialchars(strip_tags($this->billing_state));
        $this->billing_postal_code = htmlspecialchars(strip_tags($this->billing_postal_code));
        $this->billing_country = htmlspecialchars(strip_tags($this->billing_country));

        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":address_line1", $this->address_line1);
        $stmt->bindParam(":address_line2", $this->address_line2);
        $stmt->bindParam(":city", $this->city);
        $stmt->bindParam(":state", $this->state);
        $stmt->bindParam(":postal_code", $this->postal_code);
        $stmt->bindParam(":country", $this->country);
        $stmt->bindParam(":billing_first_name", $this->billing_first_name);
        $stmt->bindParam(":billing_last_name", $this->billing_last_name);
        $stmt->bindParam(":billing_address_line1", $this->billing_address_line1);
        $stmt->bindParam(":billing_address_line2", $this->billing_address_line2);
        $stmt->bindParam(":billing_city", $this->billing_city);
        $stmt->bindParam(":billing_state", $this->billing_state);
        $stmt->bindParam(":billing_postal_code", $this->billing_postal_code);
        $stmt->bindParam(":billing_country", $this->billing_country);
        $stmt->bindParam(":user_id", $this->user_id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->user_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->id = $row['id'];
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->address_line1 = $row['address_line1'];
            $this->address_line2 = $row['address_line2'];
            $this->city = $row['city'];
            $this->state = $row['state'];
            $this->postal_code = $row['postal_code'];
            $this->country = $row['country'];
            $this->billing_first_name = $row['billing_first_name'];
            $this->billing_last_name = $row['billing_last_name'];
            $this->billing_address_line1 = $row['billing_address_line1'];
            $this->billing_address_line2 = $row['billing_address_line2'];
            $this->billing_city = $row['billing_city'];
            $this->billing_state = $row['billing_state'];
            $this->billing_postal_code = $row['billing_postal_code'];
            $this->billing_country = $row['billing_country'];
        }
    }
}
?>


<?php
// /classes/Validator.php
class Validator {
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validatePhone($phone) {
        return preg_match('/^[\+]?[1-9][\d]{0,15}$/', $phone);
    }

    public static function validateRequired($value) {
        return !empty(trim($value));
    }

    public static function validateLength($value, $min = 1, $max = 255) {
        $length = strlen(trim($value));
        return $length >= $min && $length <= $max;
    }

    public static function validatePostalCode($postal_code) {
        return preg_match('/^[A-Za-z0-9\s\-]{3,10}$/', $postal_code);
    }
}
?>


<?php
// /handlers/update_profile.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

require_once '../classes/Database.php';
require_once '../classes/Profile.php';
require_once '../classes/Validator.php';

$database = new Database();
$db = $database->getConnection();
$profile = new Profile($db);

$errors = [];

if (!Validator::validateRequired($_POST['first_name'])) {
    $errors[] = 'First name is required';
}

if (!Validator::validateRequired($_POST['last_name'])) {
    $errors[] = 'Last name is required';
}

if (!Validator::validateRequired($_POST['email']) || !Validator::validateEmail($_POST['email'])) {
    $errors[] = 'Valid email is required';
}

if (!Validator::validateRequired($_POST['phone']) || !Validator::validatePhone($_POST['phone'])) {
    $errors[] = 'Valid phone number is required';
}

if (!Validator::validateRequired($_POST['address_line1'])) {
    $errors[] = 'Address line 1 is required';
}

if (!Validator::validateRequired($_POST['city'])) {
    $errors[] = 'City is required';
}

if (!Validator::validateRequired($_POST['state'])) {
    $errors[] = 'State is required';
}

if (!Validator::validateRequired($_POST['postal_code']) || !Validator::validatePostalCode($_POST['postal_code'])) {
    $errors[] = 'Valid postal code is required';
}

if (!Validator::validateRequired($_POST['country'])) {
    $errors[] = 'Country is required';
}

if (!Validator::validateRequired($_POST['billing_first_name'])) {
    $errors[] = 'Billing first name is required';
}

if (!Validator::validateRequired($_POST['billing_last_name'])) {
    $errors[] = 'Billing last name is required';
}

if (!Validator::validateRequired($_POST['billing_address_line1'])) {
    $errors[] = 'Billing address line 1 is required';
}

if (!Validator::validateRequired($_POST['billing_city'])) {
    $errors[] = 'Billing city is required';
}

if (!Validator::validateRequired($_POST['billing_state'])) {
    $errors[] = 'Billing state is required';
}

if (!Validator::validateRequired($_POST['billing_postal_code']) || !Validator::validatePostalCode($_POST['billing_postal_code'])) {
    $errors[] = 'Valid billing postal code is required';
}

if (!Validator::validateRequired($_POST['billing_country'])) {
    $errors[] = 'Billing country is required';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
    exit;
}

$profile->user_id = $_SESSION['user_id'];
$profile->first_name = $_POST['first_name'];
$profile->last_name = $_POST['last_name'];
$profile->email = $_POST['email'];
$profile->phone = $_POST['phone'];
$profile->address_line1 = $_POST['address_line1'];
$profile->address_line2 = $_POST['address_line2'] ?? '';
$profile->city = $_POST['city'];
$profile->state = $_POST['state'];
$profile->postal_code = $_POST['postal_code'];
$profile->country = $_POST['country'];
$profile->billing_first_name = $_POST['billing_first_name'];
$profile->billing_last_name = $_POST['billing_last_name'];
$profile->billing_address_line1 = $_POST['billing_address_line1'];
$profile->billing_address_line2 = $_POST['billing_address_line2'] ?? '';
$profile->billing_city = $_POST['billing_city'];
$profile->billing_state = $_POST['billing_state'];
$profile->billing_postal_code = $_POST['billing_postal_code'];
$profile->billing_country = $_POST['billing_country'];

if ($profile->update()) {
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
}
?>


<?php
// /public/profile.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../classes/Database.php';
require_once '../classes/Profile.php';

$database = new Database();
$db = $database->getConnection();
$profile = new Profile($db);
$profile->user_id = $_SESSION['user_id'];
$profile->read();
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
        <form id="profileForm" action="../handlers/update_profile.php" method="POST">
            <fieldset>
                <legend>Personal Information</legend>
                <div>
                    <label for="first_name">First Name *</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($profile->first_name ?? ''); ?>" required>
                </div>
                <div>
                    <label for="last_name">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($profile->last_name ?? ''); ?>" required>
                </div>
                <div>
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile->email ?? ''); ?>" required>
                </div>
                <div>
                    <label for="phone">Phone *</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($profile->phone ?? ''); ?>" required>
                </div>
            </fieldset>

            <fieldset>
                <legend>Address Information</legend>
                <div>
                    <label for="address_line1">Address Line 1 *</label>
                    <input type="text" id="address_line1" name="address_line1" value="<?php echo htmlspecialchars($profile->address_line1 ?? ''); ?>" required>
                </div>
                <div>
                    <label for="address_line2">Address Line 2</label>
                    <input type="text" id="address_line2" name="address_line2" value="<?php echo htmlspecialchars($profile->address_line2 ?? ''); ?>">
                </div>
                <div>
                    <label for="city">City *
?>