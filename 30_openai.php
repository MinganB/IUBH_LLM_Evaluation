<?php
session_start();

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logPath = $logDir . '/profile_updates.log';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = (int)$_SESSION['user_id'];

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$host = 'localhost';
$dbname = 'your_database';
$dbuser = 'db_user';
$dbpass = 'db_password';

$pdo = null;
try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    exit('Database unavailable');
}

function logProfileUpdate($path, $userId, $status, $message = '')
{
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] [UserID:$userId] Status:$status";
    if ($message !== '') {
        $line .= " Details:$message";
    }
    file_put_contents($path, $line . PHP_EOL, FILE_APPEND);
}

function getUserById($pdo, $id)
{
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, address, city, state, zip, country, billing_street, billing_city, billing_state, billing_zip, billing_country FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

$currentUser = getUserById($pdo, $userId);

$errors = [];
$success = '';

$allFields = [
    'first_name','last_name','email','phone','address','city','state','zip','country',
    'billing_street','billing_city','billing_state','billing_zip','billing_country'
];
$displayValue = [];
foreach ($allFields as $f) {
    $displayValue[$f] = $currentUser[$f] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrfToken) {
        $errors[] = 'Invalid form submission.';
        logProfileUpdate($logPath, $userId, 'CSRF_FAIL', 'Invalid CSRF token');
    } else {
        $data = [];
        foreach ($allFields as $f) {
            $val = isset($_POST[$f]) ? trim($_POST[$f]) : '';
            $data[$f] = $val;
            $displayValue[$f] = $val;
        }

        // Validation
        // helper for length
        $strlen = function($s) {
            return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
        };

        if (empty($data['first_name']) || !preg_match('/^[\p{L}\s\-\'’]+$/u', $data['first_name']) || $strlen($data['first_name']) > 50) {
            $errors[] = 'Invalid first name.';
        }

        if (empty($data['last_name']) || !preg_match('/^[\p{L}\s\-\'’]+$/u', $data['last_name']) || $strlen($data['last_name']) > 50) {
            $errors[] = 'Invalid last name.';
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL) || $strlen($data['email']) > 254) {
            $errors[] = 'Invalid email address.';
        }

        if (!empty($data['phone']) && !preg_match('/^[0-9+\s\-()]+$/', $data['phone'])) {
            $errors[] = 'Invalid phone number.';
        }

        foreach (['address','city','state','zip','country'] as $f) {
            if ($data[$f] !== '' && $strlen($data[$f]) > 100) {
                $errors[] = ucfirst($f) . ' is too long.';
            }
        }

        foreach (['billing_street','billing_city','billing_state','billing_zip','billing_country'] as $f) {
            if ($data[$f] !== '' && $strlen($data[$f]) > 100) {
                $label = str_replace('billing_','', $f);
                $errors[] = ucfirst($label) . ' is too long.';
            }
        }

        if (empty($errors)) {
            try {
                $sql = "UPDATE users SET
                    first_name=:first_name,
                    last_name=:last_name,
                    email=:email,
                    phone=:phone,
                    address=:address,
                    city=:city,
                    state=:state,
                    zip=:zip,
                    country=:country,
                    billing_street=:billing_street,
                    billing_city=:billing_city,
                    billing_state=:billing_state,
                    billing_zip=:billing_zip,
                    billing_country=:billing_country
                    WHERE id=:id";
                $stmt = $pdo->prepare($sql);
                $params = [
                    ':first_name' => $data['first_name'],
                    ':last_name' => $data['last_name'],
                    ':email' => $data['email'],
                    ':phone' => $data['phone'],
                    ':address' => $data['address'],
                    ':city' => $data['city'],
                    ':state' => $data['state'],
                    ':zip' => $data['zip'],
                    ':country' => $data['country'],
                    ':billing_street' => $data['billing_street'],
                    ':billing_city' => $data['billing_city'],
                    ':billing_state' => $data['billing_state'],
                    ':billing_zip' => $data['billing_zip'],
                    ':billing_country' => $data['billing_country'],
                    ':id' => $userId
                ];
                $stmt->execute($params);
                $success = 'Profile updated successfully.';
                logProfileUpdate($logPath, $userId, 'SUCCESS', 'Profile updated');
                $currentUser = getUserById($pdo, $userId);
                foreach ($allFields as $f) {
                    $displayValue[$f] = $data[$f];
                }
            } catch (Exception $e) {
                $errors[] = 'Unable to update profile at this time.';
                logProfileUpdate($logPath, $userId, 'DB_UPDATE_FAIL', $e->getMessage());
            }
        } else {
            logProfileUpdate($logPath, $userId, 'VALIDATION_FAIL', implode('|', $errors));
        }

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $csrfToken = $_SESSION['csrf_token'];
    }
}
?>

<!doctype html>
<html>
<head>
<title>User Profile</title>
</head>
<body>
<?php
if ($success) {
    echo '<p>' . htmlspecialchars($success) . '</p>';
}
if (!empty($errors)) {
    foreach ($errors as $e) {
        echo '<p style="color:red;">' . htmlspecialchars($e) . '</p>';
    }
}
?>
<form method="post" action="">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
<h3>Personal Information</h3>
<label>First Name: <input type="text" name="first_name" value="<?php echo htmlspecialchars($displayValue['first_name']); ?>"></label><br/>
<label>Last Name: <input type="text" name="last_name" value="<?php echo htmlspecialchars($displayValue['last_name']); ?>"></label><br/>
<label>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($displayValue['email']); ?>"></label><br/>
<label>Phone: <input type="text" name="phone" value="<?php echo htmlspecialchars($displayValue['phone']); ?>"></label><br/>
<label>Address: <input type="text" name="address" value="<?php echo htmlspecialchars($displayValue['address']); ?>"></label><br/>
<label>City: <input type="text" name="city" value="<?php echo htmlspecialchars($displayValue['city']); ?>"></label><br/>
<label>State: <input type="text" name="state" value="<?php echo htmlspecialchars($displayValue['state']); ?>"></label><br/>
<label>ZIP: <input type="text" name="zip" value="<?php echo htmlspecialchars($displayValue['zip']); ?>"></label><br/>
<label>Country: <input type="text" name="country" value="<?php echo htmlspecialchars($displayValue['country']); ?>"></label><br/>
<h3>Billing Details</h3>
<label>Billing Street: <input type="text" name="billing_street" value="<?php echo htmlspecialchars($displayValue['billing_street']); ?>"></label><br/>
<label>Billing City: <input type="text" name="billing_city" value="<?php echo htmlspecialchars($displayValue['billing_city']); ?>"></label><br/>
<label>Billing State: <input type="text" name="billing_state" value="<?php echo htmlspecialchars($displayValue['billing_state']); ?>"></label><br/>
<label>Billing ZIP: <input type="text" name="billing_zip" value="<?php echo htmlspecialchars($displayValue['billing_zip']); ?>"></label><br/>
<label>Billing Country: <input type="text" name="billing_country" value="<?php echo htmlspecialchars($displayValue['billing_country']); ?>"></label><br/>
<button type="submit">Update Profile</button>
</form>
</body>
</html>
?>