<?php
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
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>


<?php
class Profile {
    private $conn;
    private $table_name = "profiles";

    public $id;
    public $user_id;
    public $first_name;
    public $last_name;
    public $email;
    public $phone;
    public $address;
    public $city;
    public $state;
    public $zip_code;
    public $country;
    public $billing_first_name;
    public $billing_last_name;
    public $billing_address;
    public $billing_city;
    public $billing_state;
    public $billing_zip_code;
    public $billing_country;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getProfile($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            $this->user_id = $row['user_id'];
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->address = $row['address'];
            $this->city = $row['city'];
            $this->state = $row['state'];
            $this->zip_code = $row['zip_code'];
            $this->country = $row['country'];
            $this->billing_first_name = $row['billing_first_name'];
            $this->billing_last_name = $row['billing_last_name'];
            $this->billing_address = $row['billing_address'];
            $this->billing_city = $row['billing_city'];
            $this->billing_state = $row['billing_state'];
            $this->billing_zip_code = $row['billing_zip_code'];
            $this->billing_country = $row['billing_country'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        return false;
    }

    public function updateProfile() {
        $query = "UPDATE " . $this->table_name . " SET 
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    phone = :phone,
                    address = :address,
                    city = :city,
                    state = :state,
                    zip_code = :zip_code,
                    country = :country,
                    billing_first_name = :billing_first_name,
                    billing_last_name = :billing_last_name,
                    billing_address = :billing_address,
                    billing_city = :billing_city,
                    billing_state = :billing_state,
                    billing_zip_code = :billing_zip_code,
                    billing_country = :billing_country,
                    updated_at = NOW()
                  WHERE user_id = :user_id";

        $stmt = $this->conn->prepare($query);

        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->address = htmlspecialchars(strip_tags($this->address));
        $this->city = htmlspecialchars(strip_tags($this->city));
        $this->state = htmlspecialchars(strip_tags($this->state));
        $this->zip_code = htmlspecialchars(strip_tags($this->zip_code));
        $this->country = htmlspecialchars(strip_tags($this->country));
        $this->billing_first_name = htmlspecialchars(strip_tags($this->billing_first_name));
        $this->billing_last_name = htmlspecialchars(strip_tags($this->billing_last_name));
        $this->billing_address = htmlspecialchars(strip_tags($this->billing_address));
        $this->billing_city = htmlspecialchars(strip_tags($this->billing_city));
        $this->billing_state = htmlspecialchars(strip_tags($this->billing_state));
        $this->billing_zip_code = htmlspecialchars(strip_tags($this->billing_zip_code));
        $this->billing_country = htmlspecialchars(strip_tags($this->billing_country));

        $stmt->bindParam(':first_name', $this->first_name);
        $stmt->bindParam(':last_name', $this->last_name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':address', $this->address);
        $stmt->bindParam(':city', $this->city);
        $stmt->bindParam(':state', $this->state);
        $stmt->bindParam(':zip_code', $this->zip_code);
        $stmt->bindParam(':country', $this->country);
        $stmt->bindParam(':billing_first_name', $this->billing_first_name);
        $stmt->bindParam(':billing_last_name', $this->billing_last_name);
        $stmt->bindParam(':billing_address', $this->billing_address);
        $stmt->bindParam(':billing_city', $this->billing_city);
        $stmt->bindParam(':billing_state', $this->billing_state);
        $stmt->bindParam(':billing_zip_code', $this->billing_zip_code);
        $stmt->bindParam(':billing_country', $this->billing_country);
        $stmt->bindParam(':user_id', $this->user_id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function createProfile() {
        $query = "INSERT INTO " . $this->table_name . " 
                    (user_id, first_name, last_name, email, phone, address, city, state, zip_code, country, 
                     billing_first_name, billing_last_name, billing_address, billing_city, billing_state, 
                     billing_zip_code, billing_country, created_at, updated_at)
                  VALUES 
                    (:user_id, :first_name, :last_name, :email, :phone, :address, :city, :state, :zip_code, :country,
                     :billing_first_name, :billing_last_name, :billing_address, :billing_city, :billing_state,
                     :billing_zip_code, :billing_country, NOW(), NOW())";

        $stmt = $this->conn->prepare($query);

        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->address = htmlspecialchars(strip_tags($this->address));
        $this->city = htmlspecialchars(strip_tags($this->city));
        $this->state = htmlspecialchars(strip_tags($this->state));
        $this->zip_code = htmlspecialchars(strip_tags($this->zip_code));
        $this->country = htmlspecialchars(strip_tags($this->country));
        $this->billing_first_name = htmlspecialchars(strip_tags($this->billing_first_name));
        $this->billing_last_name = htmlspecialchars(strip_tags($this->billing_last_name));
        $this->billing_address = htmlspecialchars(strip_tags($this->billing_address));
        $this->billing_city = htmlspecialchars(strip_tags($this->billing_city));
        $this->billing_state = htmlspecialchars(strip_tags($this->billing_state));
        $this->billing_zip_code = htmlspecialchars(strip_tags($this->billing_zip_code));
        $this->billing_country = htmlspecialchars(strip_tags($this->billing_country));

        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':first_name', $this->first_name);
        $stmt->bindParam(':last_name', $this->last_name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':address', $this->address);
        $stmt->bindParam(':city', $this->city);
        $stmt->bindParam(':state', $this->state);
        $stmt->bindParam(':zip_code', $this->zip_code);
        $stmt->bindParam(':country', $this->country);
        $stmt->bindParam(':billing_first_name', $this->billing_first_name);
        $stmt->bindParam(':billing_last_name', $this->billing_last_name);
        $stmt->bindParam(':billing_address', $this->billing_address);
        $stmt->bindParam(':billing_city', $this->billing_city);
        $stmt->bindParam(':billing_state', $this->billing_state);
        $stmt->bindParam(':billing_zip_code', $this->billing_zip_code);
        $stmt->bindParam(':billing_country', $this->billing_country);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>


<?php
header("Content-Type: application/json");
session_start();

include_once '../classes/Database.php';
include_once '../classes/Profile.php';

$database = new Database();
$db = $database->getConnection();

$profile = new Profile($db);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(array("success" => false, "message" => "User not authenticated"));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'update_profile') {
        $profile->user_id = $_SESSION['user_id'];
        $profile->first_name = $_POST['first_name'];
        $profile->last_name = $_POST['last_name'];
        $profile->email = $_POST['email'];
        $profile->phone = $_POST['phone'];
        $profile->address = $_POST['address'];
        $profile->city = $_POST['city'];
        $profile->state = $_POST['state'];
        $profile->zip_code = $_POST['zip_code'];
        $profile->country = $_POST['country'];
        $profile->billing_first_name = $_POST['billing_first_name'];
        $profile->billing_last_name = $_POST['billing_last_name'];
        $profile->billing_address = $_POST['billing_address'];
        $profile->billing_city = $_POST['billing_city'];
        $profile->billing_state = $_POST['billing_state'];
        $profile->billing_zip_code = $_POST['billing_zip_code'];
        $profile->billing_country = $_POST['billing_country'];

        if ($profile->getProfile($_SESSION['user_id'])) {
            if ($profile->updateProfile()) {
                echo json_encode(array("success" => true, "message" => "Profile updated successfully"));
            } else {
                echo json_encode(array("success" => false, "message" => "Failed to update profile"));
            }
        } else {
            if ($profile->createProfile()) {
                echo json_encode(array("success" => true, "message" => "Profile created successfully"));
            } else {
                echo json_encode(array("success" => false, "message" => "Failed to create profile"));
            }
        }
    }
} else if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (isset($_GET['action']) && $_GET['action'] == 'get_profile') {
        if ($profile->getProfile($_SESSION['user_id'])) {
            $profile_data = array(
                "id" => $profile->id,
                "user_id" => $profile->user_id,
                "first_name" => $profile->first_name,
                "last_name" => $profile->last_name,
                "email" => $profile->email,
                "phone" => $profile->phone,
                "address" => $profile->address,
                "city" => $profile->city,
                "state" => $profile->state,
                "zip_code" => $profile->zip_code,
                "country" => $profile->country,
                "billing_first_name" => $profile->billing_first_name,
                "billing_last_name" => $profile->billing_last_name,
                "billing_address" => $profile->billing_address,
                "billing_city" => $profile->billing_city,
                "billing_state" => $profile->billing_state,
                "billing_zip_code" => $profile->billing_zip_code,
                "billing_country" => $profile->billing_country,
                "created_at" => $profile->created_at,
                "updated_at" => $profile->updated_at
            );
            echo json_encode(array("success" => true, "data" => $profile_data));
        } else {
            echo json_encode(array("success" => false, "message" => "Profile not found"));
        }
    }
} else {
    echo json_encode(array("success" => false, "message" => "Invalid request method"));
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
        
        <form id="profile-form">
            <fieldset>
                <legend>Personal Information</legend>
                
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" required>
                
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required>
                
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
                
                <label for="phone">Phone:</label>
                <input type="tel" id="phone" name="phone">
                
                <label for="address">Address:</label>
                <input type="text" id="address" name="address">
                
                <label for="city">City:</label>
                <input type="text" id="city" name="city">
                
                <label for="state">State:</label>
                <input type="text
?>