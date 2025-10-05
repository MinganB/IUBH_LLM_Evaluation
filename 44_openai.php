<?php
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbname = getenv('DB_NAME') ?: 'db_ecommerce';
        $user = getenv('DB_USER') ?: 'dbuser';
        $pass = getenv('DB_PASS') ?: 'dbpass';
        $charset = 'utf8mb4';
        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }

    public static function getConnection() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }
}
?><?php
require_once __DIR__ . '/../classes/Database.php';
class ProfileHandler {
    private $db;
    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function validate(array $data) {
        $errors = [];
        $required = [
            'first_name','last_name','email','address_line1','city','postal_code','country',
            'billing_name','billing_address_line1','billing_city','billing_postal_code','billing_country',
            'card_number','card_expiry','card_cvc'
        ];
        foreach ($required as $k) {
            if (empty($data[$k] ?? '')) {
                $errors[$k] = 'This field is required.';
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format.';
        }

        if (!empty($data['card_number'])) {
            $num = preg_replace('/\D/', '', $data['card_number']);
            if (strlen($num) < 13 || strlen($num) > 19 || !ctype_digit($num) || !$this->luhnCheck($num)) {
                $errors['card_number'] = 'Invalid card number.';
            } else {
                $data['card_number'] = $num;
            }
        }

        if (!empty($data['card_expiry'])) {
            $mmYY = $this->normalizeExpiry($data['card_expiry']);
            if (!$mmYY) {
                $errors['card_expiry'] = 'Invalid expiry format. Use MM/YY or MM/YYYY';
            } else {
                if (!$this->isExpiryInFuture($mmYY)) {
                    $errors['card_expiry'] = 'Card expiry must be in the future.';
                } else {
                    $data['card_expiry'] = $mmYY;
                }
            }
        }

        if (!empty($data['card_cvc'])) {
            if (!preg_match('/^\d{3,4}$/', $data['card_cvc'])) {
                $errors['card_cvc'] = 'Invalid CVC';
            }
        }

        return $errors;
    }

    private function luhnCheck($num) {
        $sum = 0;
        $alt = false;
        for ($i = strlen($num) - 1; $i >= 0; $i--) {
            $n = intval($num[$i]);
            if ($alt) {
                $n *= 2;
                if ($n > 9) $n -= 9;
            }
            $sum += $n;
            $alt = !$alt;
        }
        return ($sum % 10) == 0;
    }

    private function normalizeExpiry($str) {
        $str = preg_replace('/\s+/', '', $str);
        if (preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $str, $m)) {
            return $m[1] . '/' . $m[2];
        }
        if (preg_match('/^(0[1-9]|1[0-2])\/([0-9]{4})$/', $str, $m)) {
            $year = substr($m[2], -2);
            return $m[1] . '/' . $year;
        }
        return null;
    }

    private function isExpiryInFuture($mmYY) {
        $parts = explode('/', $mmYY);
        if (count($parts) != 2) return false;
        $month = (int)$parts[0];
        $year = (int)$parts[1];
        if ($year < 0) return false;
        $year += ($year < 100) ? 2000 : 0;
        $exp = mktime(0,0,0,$month,1,$year);
        $lastDay = date('t', $exp);
        $expTimestamp = mktime(23,59,59,$month,$lastDay,$year);
        return $expTimestamp > time();
    }

    public function updateProfile($userId, array $data) {
        $cardLast4 = null;
        if (!empty($data['card_number'])) {
            $num = preg_replace('/\D/','',$data['card_number']);
            $cardLast4 = substr($num, -4);
        }
        $cardExpiry = isset($data['card_expiry']) ? $data['card_expiry'] : null;

        $stmt = $this->db->prepare("
            INSERT INTO profiles (
                user_id, first_name, last_name, email, phone,
                address_line1, address_line2, city, state, postal_code, country,
                billing_name, billing_address_line1, billing_address_line2, billing_city, billing_state, billing_postal_code, billing_country,
                card_last4, card_expiry, updated_at
            ) VALUES (
                :user_id, :first_name, :last_name, :email, :phone,
                :address_line1, :address_line2, :city, :state, :postal_code, :country,
                :billing_name, :billing_address_line1, :billing_address_line2, :billing_city, :billing_state, :billing_postal_code, :billing_country,
                :card_last4, :card_expiry, NOW()
            )
            ON DUPLICATE KEY UPDATE
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                email = VALUES(email),
                phone = VALUES(phone),
                address_line1 = VALUES(address_line1),
                address_line2 = VALUES(address_line2),
                city = VALUES(city),
                state = VALUES(state),
                postal_code = VALUES(postal_code),
                country = VALUES(country),
                billing_name = VALUES(billing_name),
                billing_address_line1 = VALUES(billing_address_line1),
                billing_address_line2 = VALUES(billing_address_line2),
                billing_city = VALUES(billing_city),
                billing_state = VALUES(billing_state),
                billing_postal_code = VALUES(billing_postal_code),
                billing_country = VALUES(billing_country),
                card_last4 = VALUES(card_last4),
                card_expiry = VALUES(card_expiry),
                updated_at = NOW()
        ");

        $params = [
            ':user_id' => $userId,
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
            ':billing_name' => $data['billing_name'] ?? '',
            ':billing_address_line1' => $data['billing_address_line1'] ?? '',
            ':billing_address_line2' => $data['billing_address_line2'] ?? '',
            ':billing_city' => $data['billing_city'] ?? '',
            ':billing_state' => $data['billing_state'] ?? '',
            ':billing_postal_code' => $data['billing_postal_code'] ?? '',
            ':billing_country' => $data['billing_country'] ?? '',
            ':card_last4' => $cardLast4,
            ':card_expiry' => $cardExpiry
        ];

        $stmt->execute($params);
    }

    private function formatExpiryForStorage($mmYY) {
        if (!$mmYY) return null;
        return $mmYY;
    }
}
?><?php
session_start();
ini_set('display_errors', '0');
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../handlers/ProfileHandler.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
$postedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!$postedToken || !$sessionToken || $postedToken !== $sessionToken) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}
$userId = (int)$_SESSION['user_id'];
$handler = new ProfileHandler();

$fields = ['first_name','last_name','email','phone','address_line1','address_line2','city','state','postal_code','country','billing_name','billing_address_line1','billing_address_line2','billing_city','billing_state','billing_postal_code','billing_country','card_number','card_expiry','card_cvc'];
$data = [];
foreach ($fields as $f) {
    $data[$f] = isset($_POST[$f]) ? trim($_POST[$f]) : '';
}

$errors = $handler->validate($data);
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
    exit;
}

try {
    $handler->updateProfile($userId, $data);
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' 'Database error']);
}
?><?php
session_start();
$csrfToken = $_SESSION['csrf_token'] ?? null;
if (!$csrfToken) {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}
?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>User Profile</title>
</head>
<body>
<form id="profileForm" onsubmit="event.preventDefault(); submitProfile();" method="post" action="update_profile.php">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
  <div>
    <label>First Name</label>
    <input type="text" name="first_name" required>
  </div>
  <div>
    <label>Last Name</label>
    <input type="text" name="last_name" required>
  </div>
  <div>
    <label>Email</label>
    <input type="email" name="email" required>
  </div>
  <div>
    <label>Phone</label>
    <input type="text" name="phone">
  </div>
  <div>
    <label>Address Line 1</label>
    <input type="text" name="address_line1" required>
  </div>
  <div>
    <label>Address Line 2</label>
    <input type="text" name="address_line2">
  </div>
  <div>
    <label>City</label>
    <input type="text" name="city" required>
  </div>
  <div>
    <label>State</label>
    <input type="text" name="state">
  </div>
  <div>
    <label>Postal Code</label>
    <input type="text" name="postal_code" required>
  </div>
  <div>
    <label>Country</label>
    <input type="text" name="country" required>
  </div>
  <div>
    <label>Billing Name</label>
    <input type="text" name="billing_name" required>
  </div>
  <div>
    <label>Billing Address Line 1</label>
    <input type="text" name="billing_address_line1" required>
  </div>
  <div>
    <label>Billing Address Line 2</label>
    <input type="text" name="billing_address_line2">
  </div>
  <div>
    <label>Billing City</label>
    <input type="text" name="billing_city" required>
  </div>
  <div>
    <label>Billing State</label>
    <input type="text" name="billing_state">
  </div>
  <div>
    <label>Billing Postal Code</label>
    <input type="text" name="billing_postal_code" required>
  </div>
  <div>
    <label>Billing Country</label>
    <input type="text" name="billing_country" required>
  </div>
  <div>
    <label>Card Number</label>
    <input type="text" name="card_number" required>
  </div>
  <div>
    <label>Card Expiry (MM/YY)</label>
    <input type="text" name="card_expiry" required>
  </div>
  <div>
    <label>Card CVC</label>
    <input type="text" name="card_cvc" required>
  </div>
  <button type="submit">Update Profile</button>
</form>
<div id="result"></div>
<script>
function submitProfile(){
  const form = document.getElementById('profileForm');
  const formData = new FormData(form);
  fetch('update_profile.php', {
    method: 'POST',
    body: formData
  }).then(res => res.json()).then(data => {
    const resDiv = document.getElementById('result');
    if (data.success) {
      resDiv.textContent = data.message || 'Profile updated successfully';
    } else {
      resDiv.textContent = data.message || 'Error updating profile';
    }
  }).catch(() => {
    document.getElementById('result').textContent = 'Request failed';
  });
}
</script>
</body>
</html>
?>