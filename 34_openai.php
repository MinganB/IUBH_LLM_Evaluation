<?php
session_start();

$pdo = null;

if (file_exists(__DIR__ . '/../classes/Database.php') && !class_exists('Database')) {
    require_once __DIR__ . '/../classes/Database.php';
}
if (!class_exists('Database')) {
    class Database {
        private $pdo;
        public function __construct() {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbname = getenv('DB_NAME') ?: 'db_ecommerce';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        }
        public function getConnection() {
            return $this->pdo;
        }
    }
}
$db = new Database();
$pdo = $db->getConnection();

if (file_exists(__DIR__ . '/../classes/Profile.php') && !class_exists('Profile')) {
    require_once __DIR__ . '/../classes/Profile.php';
}
if (!class_exists('Profile')) {
    class Profile {
        private $db;
        public function __construct($db) {
            $this->db = $db;
        }
        public function getProfile($userId) {
            $stmt = $this->db->prepare("SELECT * FROM profiles WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $userId]);
            return $stmt->fetch();
        }
        public function updateProfile($userId, $data) {
            $sql = "UPDATE profiles SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone,
                    billing_name = :billing_name, billing_address1 = :billing_address1, billing_address2 = :billing_address2,
                    billing_city = :billing_city, billing_state = :billing_state, billing_postal_code = :billing_postal_code,
                    billing_country = :billing_country, updated_at = NOW() WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $params = [
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'email' => $data['email'] ?? '',
                'phone' => $data['phone'] ?? '',
                'billing_name' => $data['billing_name'] ?? '',
                'billing_address1' => $data['billing_address1'] ?? '',
                'billing_address2' => $data['billing_address2'] ?? '',
                'billing_city' => $data['billing_city'] ?? '',
                'billing_state' => $data['billing_state'] ?? '',
                'billing_postal_code' => $data['billing_postal_code'] ?? '',
                'billing_country' => $data['billing_country'] ?? '',
                'user_id' => $userId
            ];
            $stmt->execute($params);
            return $stmt->rowCount();
        }
    }
}

if (file_exists(__DIR__ . '/../handlers/ProfileHandler.php') && !class_exists('ProfileHandler')) {
    require_once __DIR__ . '/../handlers/ProfileHandler.php';
}
if (!class_exists('ProfileHandler')) {
    class ProfileHandler {
        private $db;
        private $profileModel;
        public function __construct($db) {
            $this->db = $db;
            $this->profileModel = new Profile($db);
        }
        public function getUserProfile($userId) {
            return $this->profileModel->getProfile($userId);
        }
        public function updateUserProfile($userId, $data) {
            if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
                return ['success' => false, 'message' => 'Please fill all required fields.'];
            }
            $updated = $this->profileModel->updateProfile($userId, $data);
            if ($updated !== false && $updated > 0) {
                return ['success' => true, 'message' => 'Profile updated successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to update profile.'];
            }
        }
    }
}

$userId = null;
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
} elseif (isset($_GET['user_id'])) {
    $userId = (int)$_GET['user_id'];
}

$handler = new ProfileHandler($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    header('Content-Type: application/json');
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    $data = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'billing_name' => $_POST['billing_name'] ?? '',
        'billing_address1' => $_POST['billing_address1'] ?? '',
        'billing_address2' => $_POST['billing_address2'] ?? '',
        'billing_city' => $_POST['billing_city'] ?? '',
        'billing_state' => $_POST['billing_state'] ?? '',
        'billing_postal_code' => $_POST['billing_postal_code'] ?? '',
        'billing_country' => $_POST['billing_country'] ?? '',
    ];
    $response = $handler->updateUserProfile($userId, $data);
    echo json_encode($response);
    exit;
}

$profile = [];
if ($userId) {
    $profile = $handler->getUserProfile($userId) ?: [];
}
?><!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
</head>
<body>
<h2>Profile</h2>
<form id="profileForm" method="post">
    <input type="hidden" name="action" value="update_profile">
    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($userId ?? '', ENT_COMPAT, 'UTF-8'); ?>">
    <div>
        <label>First Name:</label>
        <input type="text" name="first_name" value="<?php echo htmlspecialchars($profile['first_name'] ?? '', ENT_COMPAT, 'UTF-8'); ?>">
    </div>
    <div>
        <label>Last Name:</label>
        <input type="text" name="last_name" value="<?php echo htmlspecialchars($profile['last_name'] ?? '', ENT_COMPAT, 'UTF-8'); ?>">
    </div>
    <div>
        <label>Email:</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? '', ENT_COMPAT, 'UTF-8'); ?>">
    </div>
    <div>
        <label>Phone:</label>
        <input type="tel" name="phone" value="<?php echo htmlspecialchars($profile['phone'] ?? '', ENT_COMPAT, 'UTF-8'); ?>">
    </div>
    <h3>Billing Details</h3>
    <div>
        <label>Billing Name:</label>
        <input type="text" name="billing_name" value="<?php echo htmlspecialchars($profile['billing_name'] ?? '', ENT_COMPAT, 'UTF-8'); ?>">
    </div>
    <div>
        <label>Billing Address Line 1:</label>
        <input type="text" name="billing_address1" value="<?php echo htmlspecialchars($profile['billing_address1'] ?? '', ENT_COMPAT, 'UTF-8'); ?>">
    </div>
    <div>
        <label>Billing Address Line 2:</label>
        <input type="text" name="billing_address2" value="<?php echo htmlspecialchars($profile['billing_address2'] ?? '', ENT_COMPAT, 'UTF-8'); ?>">
    </div>
    <div>
        <label>Billing City:</label>
        <input type="text" name="billing_city" value="<?php echo htmlspecialchars($profile['billing_city'] ?? '', ENT_COMPAT, 'UTF-8'); ?>">
    </div>
    <div>
        <label>Billing State:</label>
        <input type="text" name="billing_state" value="<?php echo htmlspecialchars($profile['billing_state'] ?? '', ENT_COMPAT, 'UTF-8'); ?>">
    </div>
    <div>
        <label>Billing Postal Code:</label>
        <input type="text" name="billing_postal_code" value="<?php echo htmlspecialchars($profile['billing_postal_code'] ?? '', ENT_COMPAT, 'UTF-8'); ?>">
    </div>
    <div>
        <label>Billing Country:</label>
        <input type="text" name="billing_country" value="<?php echo htmlspecialchars($profile['billing_country'] ?? '', ENT_COMPAT, 'UTF-8'); ?>">
    </div>
    <div>
        <button type="submit">Update Profile</button>
    </div>
</form>
<div id="status" aria-live="polite" style="margin-top:10px;"></div>
<script>
document.getElementById('profileForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        const json = await response.json();
        const status = document.getElementById('status');
        if (json && json.success) {
            status.style.color = 'green';
            status.textContent = json.message || 'Profile updated successfully.';
        } else {
            status.style.color = 'red';
            status.textContent = json?.message || 'An error occurred.';
        }
    } catch (err) {
        const status = document.getElementById('status');
        status.style.color = 'red';
        status.textContent = 'Request failed. Please try again.';
    }
});
</script>
</body>
</html>
?>