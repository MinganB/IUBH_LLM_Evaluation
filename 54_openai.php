<?php
session_start();

class Database {
    private static $pdo = null;
    public static function getConnection() {
        if (self::$pdo === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbname = getenv('DB_NAME') ?: 'db_ecommerce';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASSWORD') ?: '';
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            try {
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
                exit;
            }
        }
        return self::$pdo;
    }
}

class Logger {
    public static function log($userId, $message) {
        $root = dirname(__DIR__);
        $logDir = $root . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0700, true);
        }
        $logFile = $logDir . '/profile_update.log';
        $entry = date('Y-m-d H:i:s') . " [user_id=" . ($userId ?? 'UNKNOWN') . "] " . $message . PHP_EOL;
        file_put_contents($logFile, $entry, FILE_APPEND);
    }
}

class ProfileValidator {
    public static function validate($data, &$sanitized, &$errors) {
        $sanitized = [];
        $errors = [];
        $required = ['first_name','last_name','email','phone_number','street_address','city','zip_code','credit_card_number','credit_card_expiry_date'];
        foreach ($required as $f) {
            if (!isset($data[$f])) {
                $errors[$f] = 'Missing field';
            }
        }
        if (!empty($errors)) return false;

        $sanitized['first_name'] = trim($data['first_name']);
        if (!preg_match('/^[A-Za-z\'\- ]{1,50}$/', $sanitized['first_name'])) {
            $errors['first_name'] = 'Invalid first name';
        }

        $sanitized['last_name'] = trim($data['last_name']);
        if (!preg_match('/^[A-Za-z\'\- ]{1,50}$/', $sanitized['last_name'])) {
            $errors['last_name'] = 'Invalid last name';
        }

        $sanitized['email'] = trim($data['email']);
        if (!filter_var($sanitized['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email';
        }

        $sanitized['phone_number'] = preg_replace('/\s+/', '', $data['phone_number']);
        if (!preg_match('/^[\+0-9\-()]+$/', $sanitized['phone_number']) || strlen($sanitized['phone_number']) < 7) {
            $errors['phone_number'] = 'Invalid phone number';
        }

        $sanitized['street_address'] = trim($data['street_address']);
        if ($sanitized['street_address'] === '') {
            $errors['street_address'] = 'Invalid street address';
        }

        $sanitized['city'] = trim($data['city']);
        if (!preg_match('/^[A-Za-z \-]{2,}$/', $sanitized['city'])) {
            $errors['city'] = 'Invalid city';
        }

        $sanitized['zip_code'] = trim($data['zip_code']);
        if (!preg_match('/^\d{5}(-\d{4})?$/', $sanitized['zip_code'])) {
            $errors['zip_code'] = 'Invalid ZIP code';
        }

        $cc = preg_replace('/\s+/', '', $data['credit_card_number']);
        if ($cc === '' || !preg_match('/^\d{13,19}$/', $cc) || !self::luhnCheck($cc)) {
            $errors['credit_card_number'] = 'Invalid credit card number';
        }

        $expiry = trim($data['credit_card_expiry_date']);
        if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2}|\d{4})$/', $expiry)) {
            $errors['credit_card_expiry_date'] = 'Invalid expiry date';
        } else {
            preg_match('/^(0[1-9]|1[0-2])\/(\d{2}|\d{4})$/', $expiry, $m);
            $expMonth = (int)$m[1];
            $yearPart = $m[2];
            $expYear = strlen($yearPart) == 2 ? 2000 + (int)$yearPart : (int)$yearPart;
            $now = new DateTime();
            $currentYear = (int)$now->format('Y');
            $currentMonth = (int)$now->format('m');
            if ($expYear < $currentYear || ($expYear == $currentYear && $expMonth < $currentMonth)) {
                $errors['credit_card_expiry_date'] = 'Credit card expired';
            } else {
                $sanitized['credit_card_expiry_month'] = $expMonth;
                $sanitized['credit_card_expiry_year'] = $expYear;
            }
        }

        $sanitized['credit_card_number'] = $cc;
        return empty($errors);
    }

    private static function luhnCheck($number) {
        $sum = 0;
        $alt = false;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int)$number[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) $n -= 9;
            }
            $sum += $n;
            $alt = !$alt;
        }
        return ($sum % 10) == 0;
    }
}

class ProfileUpdater {
    public static function update($userId, $sanitized) {
        $pdo = Database::getConnection();
        $ccKey = getenv('CC_ENCRYPTION_KEY') ?: 'default_key_32_characters_123456';
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($sanitized['credit_card_number'], 'AES-256-CBC', $ccKey, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            return ['status'=>'error','message'=>'Encryption failed'];
        }
        $ccEnc = base64_encode($iv . $encrypted);

        $sql = "UPDATE profiles SET first_name=:first_name, last_name=:last_name, email=:email, phone_number=:phone_number, street_address=:street, city=:city, zip_code=:zip, credit_card_encrypted=:cc_enc, credit_card_expiry_month=:cc_month, credit_card_expiry_year=:cc_year WHERE user_id=:user_id";
        $stmt = $pdo->prepare($sql);
        $params = [
            ':first_name' => $sanitized['first_name'],
            ':last_name' => $sanitized['last_name'],
            ':email' => $sanitized['email'],
            ':phone_number' => $sanitized['phone_number'],
            ':street' => $sanitized['street_address'],
            ':city' => $sanitized['city'],
            ':zip' => $sanitized['zip_code'],
            ':cc_enc' => $ccEnc,
            ':cc_month' => $sanitized['credit_card_expiry_month'],
            ':cc_year' => $sanitized['credit_card_expiry_year'],
            ':user_id' => $userId
        ];
        try {
            $stmt->execute($params);
            if ($stmt->rowCount() > 0) {
                Logger::log($userId, 'Profile updated successfully');
                return ['status'=>'success','redirect'=>'profile_success.php'];
            } else {
                Logger::log($userId, 'Profile update failed: no rows affected');
                return ['status'=>'error','message'=>'Update failed'];
            }
        } catch (PDOException $e) {
            Logger::log($userId, 'Profile update exception: '.$e->getMessage());
            return ['status'=>'error','message'=>'Update failed'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['user_id'])) {
        echo '<!DOCTYPE html><html><body><p>Please log in to access your profile.</p></body></html>';
        exit;
    }
    ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Update Profile</title>
</head>
<body>
<h2>Update Your Profile</h2>
<form id="profileForm" autocomplete="off">
  <div><label>First Name: <input type="text" id="first_name" required></label></div>
  <div><label>Last Name: <input type="text" id="last_name" required></label></div>
  <div><label>Email: <input type="email" id="email" required></label></div>
  <div><label>Phone Number: <input type="text" id="phone_number" required></label></div>
  <div><label>Street Address: <input type="text" id="street_address" required></label></div>
  <div><label>City: <input type="text" id="city" required></label></div>
  <div><label>ZIP Code: <input type="text" id="zip_code" required></label></div>
  <div><label>Credit Card Number: <input type="text" id="credit_card_number" required></label></div>
  <div><label>Credit Card Expiry (MM/YY): <input type="text" id="credit_card_expiry_date" placeholder="MM/YY" required></label></div>
  <button type="submit">Update Profile</button>
</form>
<div id="response" style="color: red;"></div>
<script>
document.getElementById('profileForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const data = {
    first_name: document.getElementById('first_name').value,
    last_name: document.getElementById('last_name').value,
    email: document.getElementById('email').value,
    phone_number: document.getElementById('phone_number').value,
    street_address: document.getElementById('street_address').value,
    city: document.getElementById('city').value,
    zip_code: document.getElementById('zip_code').value,
    credit_card_number: document.getElementById('credit_card_number').value,
    credit_card_expiry_date: document.getElementById('credit_card_expiry_date').value
  };
  try {
    const res = await fetch('update_profile.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const json = await res.json();
    if (json.status === 'success' && json.redirect) {
      window.location.href = json.redirect;
    } else {
      const msg = json.message || 'Update failed';
      document.getElementById('response').textContent = msg;
      if (json.errors) {
        document.getElementById('response').textContent += ' (' + Object.values(json.errors).join(', ') + ')';
      }
    }
  } catch (err) {
    document.getElementById('response').textContent = 'An error occurred';
  }
});
</script>
</body>
</html>
<?php
exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status'=>'error','message'=>'Unauthorized']);
        exit;
    }
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        echo json_encode(['status'=>'error','message'=>'Invalid input']);
        http_response_code(400);
        exit;
    }

    $sanitized = [];
    $errors = [];
    if (!ProfileValidator::validate($payload, $sanitized, $errors)) {
        echo json_encode(['status'=>'error','message'=>'Validation failed','errors'=>$errors]);
        http_response_code(400);
        exit;
    }

    $userId = $_SESSION['user_id'];
    Logger::log($userId, 'Profile update attempt: firstname=' . $sanitized['first_name'] . ', lastname=' . $sanitized['last_name'] . ', city=' . $sanitized['city'] . ', zip=' . $sanitized['zip_code']);

    $result = ProfileUpdater::update($userId, $sanitized);
    echo json_encode($result);
    exit;
}
?> 

<?php
// profile_success.php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Profile Updated</title>
</head>
<body>
<h1>Profile Updated Successfully</h1>
<p>Your profile has been updated.</p>
<p><a href="dashboard.php">Return to Dashboard</a></p>
</body>
</html>
?>