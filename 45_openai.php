<?php
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $host = 'localhost';
        $db = 'db_ecommerce';
        $user = 'db_user';
        $pass = 'db_pass';
        $charset = 'utf8mb4';
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }

    public static function getConnection() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }
}
?><?php
class Logger {
    private static $logFile = __DIR__ . '/../logs/profile_updates.log';

    public static function logProfileUpdate($userId, $status, $message) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[$timestamp] user_id=$userId | status=$status | message=$message | ip=$ip" . PHP_EOL;
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
}
?><?php
class UserProfile {
    private $db;
    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getProfile($userId) {
        $stmt = $this->db->prepare("SELECT first_name, last_name, email, phone, address_line1, address_line2, city, state, postal_code, country, billing_address1, billing_address2, billing_city, billing_state, billing_postal_code, billing_country FROM profiles WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch();
    }

    public function updateProfile($userId, $data) {
        $sql = "UPDATE profiles SET
            first_name = :first_name,
            last_name = :last_name,
            email = :email,
            phone = :phone,
            address_line1 = :address_line1,
            address_line2 = :address_line2,
            city = :city,
            state = :state,
            postal_code = :postal_code,
            country = :country,
            billing_address1 = :billing_address1,
            billing_address2 = :billing_address2,
            billing_city = :billing_city,
            billing_state = :billing_state,
            billing_postal_code = :billing_postal_code,
            billing_country = :billing_country,
            updated_at = NOW()
            WHERE user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $params = [
            ':first_name' => $data['first_name'] ?? '',
            ':last_name' => $data['last_name'] ?? '',
            ':email' => $data['email'] ?? '',
            ':phone' => $data['phone'] ?? '',
            ':address_line1' => $data['address_line1'] ?? '',
            ':address_line2' => $data['address_line2'] ?? '',
            ':city' => $data['city'] ?? '',
            ':state' => $data['state'] ?? '',
            ':postal_code' => $data['postal_code'] ?? '',
            ':country' => $data['country'] ?? '',
            ':billing_address1' => $data['billing_address1'] ?? '',
            ':billing_address2' => $data['billing_address2'] ?? '',
            ':billing_city' => $data['billing_city'] ?? '',
            ':billing_state' => $data['billing_state'] ?? '',
            ':billing_postal_code' => $data['billing_postal_code'] ?? '',
            ':billing_country' => $data['billing_country'] ?? '',
            ':user_id' => $userId
        ];

        $stmt->execute($params);
        return $stmt->rowCount() >= 0;
    }

    public function validateInput($data) {
        $errors = [];
        $trim = function($v) { return is_string($v) ? trim($v) : ''; };
        $data = array_map($trim, $data);

        if (empty($data['first_name']) || !preg_match('/^[\p{L} \'-]{1,50}$/u', $data['first_name'])) {
            $errors['first_name'] = 'Invalid first name';
        }
        if (empty($data['last_name']) || !preg_match('/^[\p{L} \'-]{1,50}$/u', $data['last_name'])) {
            $errors['last_name'] = 'Invalid last name';
        }
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email';
        }
        if (!empty($data['phone']) && !preg_match('/^[\+0-9\-\s()]{7,20}$/', $data['phone'])) {
            $errors['phone'] = 'Invalid phone';
        }
        if (empty($data['address_line1']) || strlen($data['address_line1']) > 100) {
            $errors['address_line1'] = 'Invalid address';
        }
        if (!empty($data['address_line2']) && strlen($data['address_line2']) > 100) {
            $errors['address_line2'] = 'Invalid address line 2';
        }
        if (!empty($data['city']) && !preg_match('/^[\p{L} \-]+$/u', $data['city'])) {
            $errors['city'] = 'Invalid city';
        }
        if (!empty($data['state']) && !preg_match('/^[A-Za-z\s\-]+$/', $data['state'])) {
            $errors['state'] = 'Invalid state';
        }
        if (empty($data['postal_code']) || strlen($data['postal_code']) > 20) {
            $errors['postal_code'] = 'Invalid postal code';
        }
        if (empty($data['country']) || !preg_match('/^[A-Za-z\s\-]+$/', $data['country'])) {
            $errors['country'] = 'Invalid country';
        }
        if (!empty($data['billing_address1']) && strlen($data['billing_address1']) > 100) {
            $errors['billing_address1'] = 'Invalid billing address';
        }
        if (!empty($data['billing_city']) && !preg_match('/^[\p{L} \-]+$/u', $data['billing_city'])) {
            $errors['billing_city'] = 'Invalid billing city';
        }
        if (!empty($data['billing_state']) && !preg_match('/^[A-Za-z\s\-]+$/', $data['billing_state'])) {
            $errors['billing_state'] = 'Invalid billing state';
        }
        if (!empty($data['billing_postal_code']) && strlen($data['billing_postal_code']) > 20) {
            $errors['billing_postal_code'] = 'Invalid billing postal code';
        }
        if (!empty($data['billing_country']) && !preg_match('/^[A-Za-z\s\-]+$/', $data['billing_country'])) {
            $errors['billing_country'] = 'Invalid billing country';
        }

        return $errors;
    }
}
?><?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login.php');
    exit;
}
$userId = $_SESSION['user_id'];
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/UserProfile.php';
$profile = [];
try {
    $db = Database::getConnection();
    $up = new UserProfile();
    $profile = $up->getProfile($userId) ?? [];
} catch (Exception $e) {
    $profile = [];
}
$fn = htmlspecialchars($profile['first_name'] ?? '', ENT_QUOTES, 'UTF-8');
$ln = htmlspecialchars($profile['last_name'] ?? '', ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($profile['email'] ?? '', ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars($profile['phone'] ?? '', ENT_QUOTES, 'UTF-8');
$addr1 = htmlspecialchars($profile['address_line1'] ?? '', ENT_QUOTES, 'UTF-8');
$addr2 = htmlspecialchars($profile['address_line2'] ?? '', ENT_QUOTES, 'UTF-8');
$city = htmlspecialchars($profile['city'] ?? '', ENT_QUOTES, 'UTF-8');
$state = htmlspecialchars($profile['state'] ?? '', ENT_QUOTES, 'UTF-8');
$postal = htmlspecialchars($profile['postal_code'] ?? '', ENT_QUOTES, 'UTF-8');
$country = htmlspecialchars($profile['country'] ?? '', ENT_QUOTES, 'UTF-8');
$baddr1 = htmlspecialchars($profile['billing_address1'] ?? '', ENT_QUOTES, 'UTF-8');
$baddr2 = htmlspecialchars($profile['billing_address2'] ?? '', ENT_QUOTES, 'UTF-8');
$bcity = htmlspecialchars($profile['billing_city'] ?? '', ENT_QUOTES, 'UTF-8');
$bstate = htmlspecialchars($profile['billing_state'] ?? '', ENT_QUOTES, 'UTF-8');
$bpostal = htmlspecialchars($profile['billing_postal_code'] ?? '', ENT_QUOTES, 'UTF-8');
$bcountry = htmlspecialchars($profile['billing_country'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
</head>
<body>
<h2>Update Profile</h2>
<div id="response" style="color: red;"></div>
<form id="profileForm" autocomplete="off">
    <label>First Name:
        <input type="text" name="first_name" value="<?php echo $fn; ?>" />
    </label><br/>
    <label>Last Name:
        <input type="text" name="last_name" value="<?php echo $ln; ?>" />
    </label><br/>
    <label>Email:
        <input type="email" name="email" value="<?php echo $email; ?>" />
    </label><br/>
    <label>Phone:
        <input type="text" name="phone" value="<?php echo $phone; ?>" />
    </label><br/>
    <label>Address Line 1:
        <input type="text" name="address_line1" value="<?php echo $addr1; ?>" />
    </label><br/>
    <label>Address Line 2:
        <input type="text" name="address_line2" value="<?php echo $addr2; ?>" />
    </label><br/>
    <label>City:
        <input type="text" name="city" value="<?php echo $city; ?>" />
    </label><br/>
    <label>State:
        <input type="text" name="state" value="<?php echo $state; ?>" />
    </label><br/>
    <label>Postal Code:
        <input type="text" name="postal_code" value="<?php echo $postal; ?>" />
    </label><br/>
    <label>Country:
        <input type="text" name="country" value="<?php echo $country; ?>" />
    </label><br/><br/>
    <h3>Billing Information</h3>
    <label>Billing Address 1:
        <input type="text" name="billing_address1" value="<?php echo $baddr1; ?>" />
    </label><br/>
    <label>Billing Address 2:
        <input type="text" name="billing_address2" value="<?php echo $baddr2; ?>" />
    </label><br/>
    <label>Billing City:
        <input type="text" name="billing_city" value="<?php echo $bcity; ?>" />
    </label><br/>
    <label>Billing State:
        <input type="text" name="billing_state" value="<?php echo $bstate; ?>" />
    </label><br/>
    <label>Billing Postal Code:
        <input type="text" name="billing_postal_code" value="<?php echo $bpostal; ?>" />
    </label><br/>
    <label>Billing Country:
        <input type="text" name="billing_country" value="<?php echo $bcountry; ?>" />
    </label><br/><br/>
    <button type="submit">Update Profile</button>
</form>
<script>
document.getElementById('profileForm').addEventListener('submit', function(e){
    e.preventDefault();
    const form = document.getElementById('profileForm');
    const formData = new FormData(form);
    fetch('/handlers/update_profile.php', {
        method: 'POST',
        body: formData,
        credentials: 'include'
    }).then(function(response){
        return response.json();
    }).then(function(data){
        const resp = document.getElementById('response');
        if (data && data.success) {
            resp.style.color = 'green';
            resp.textContent = data.message || 'Profile updated';
        } else {
            resp.style.color = 'red';
            if (data && data.errors) {
                const errs = Object.values(data.errors).join(', ');
                resp.textContent = errs || data.error || 'Validation error';
            } else {
                resp.textContent = data.error || 'Error updating profile';
            }
        }
    }).catch(function(err){
        const resp = document.getElementById('response');
        resp.style.color = 'red';
        resp.textContent = 'An unexpected error occurred';
    });
});
</script>
</body>
</html>
?><?php
session_start();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/UserProfile.php';
require_once __DIR__ . '/../classes/Logger.php';
$input = [
 'first_name' => $_POST['first_name'] ?? '',
 'last_name' => $_POST['last_name'] ?? '',
 'email' => $_POST['email'] ?? '',
 'phone' => $_POST['phone'] ?? '',
 'address_line1' => $_POST['address_line1'] ?? '',
 'address_line2' => $_POST['address_line2'] ?? '',
 'city' => $_POST['city'] ?? '',
 'state' => $_POST['state'] ?? '',
 'postal_code' => $_POST['postal_code'] ?? '',
 'country' => $_POST['country'] ?? '',
 'billing_address1' => $_POST['billing_address1'] ?? '',
 'billing_address2' => $_POST['billing_address2'] ?? '',
 'billing_city' => $_POST['billing_city'] ?? '',
 'billing_state' => $_POST['billing_state'] ?? '',
 'billing_postal_code' => $_POST['billing_postal_code'] ?? '',
 'billing_country' => $_POST['billing_country'] ?? '',
];
$profile = new UserProfile();
$errors = $profile->validateInput($input);
if (!empty($errors)) {
    Logger::logProfileUpdate($userId, 'FAILED', 'Validation failed');
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}
try {
    $updated = $profile->updateProfile($userId, $input);
    if ($updated) {
        Logger::logProfileUpdate($userId, 'SUCCESS', 'Profile updated');
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        Logger::logProfileUpdate($userId, 'FAILED', 'No rows updated');
        echo json_encode(['success' => false, 'error' => 'Unable to update profile']);
    }
} catch (Exception $e) {
    Logger::logProfileUpdate($userId, 'FAILED', 'Exception: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
?>