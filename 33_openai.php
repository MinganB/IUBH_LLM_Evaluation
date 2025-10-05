<?php
session_start();

$LOG_DIR = __DIR__ . '/logs';
$LOG_FILE = $LOG_DIR . '/profile_update.log';
if (!is_dir($LOG_DIR)) {
    mkdir($LOG_DIR, 0700, true);
}
function logProfileAttempt($userId, $success, $message) {
    global $LOG_FILE;
    $entry = date('Y-m-d H:i:s') . " | user_id=" . $userId . " | " . ($success ? 'SUCCESS' : 'FAIL') . " | " . $message;
    $entry .= PHP_EOL;
    file_put_contents($LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}

function getDbConnection() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $db   = getenv('DB_NAME') ?: 'ecommerce';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    return new PDO($dsn, $user, $pass, $options);
}

function cleanInput($value) {
    if ($value === null) return '';
    return trim(strip_tags($value));
}

function renderValue($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function isInArray($value, $array) {
    return in_array($value, $array, true);
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId = (int)$_SESSION['user_id'];
if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

$alertMessage = '';
$errors = [];

$allowedCountries = [
    'United States','Canada','United Kingdom','Australia','Germany','France','India'
];

try {
    $db = getDbConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            logProfileAttempt($userId, false, 'CSRF token invalid');
            $errors[] = 'Invalid form submission. Please try again.';
        } else {
            $firstName = cleanInput($_POST['first_name'] ?? '');
            $lastName = cleanInput($_POST['last_name'] ?? '');
            $email = cleanInput($_POST['email'] ?? '');
            $phone = cleanInput($_POST['phone'] ?? '');

            $addr1 = cleanInput($_POST['billing_addr1'] ?? '');
            $addr2 = cleanInput($_POST['billing_addr2'] ?? '');
            $city = cleanInput($_POST['billing_city'] ?? '');
            $state = cleanInput($_POST['billing_state'] ?? '');
            $zip = cleanInput($_POST['billing_zip'] ?? '');
            $country = cleanInput($_POST['billing_country'] ?? '');

            if (empty($firstName)) $errors[] = 'First name is required.';
            if (empty($lastName)) $errors[] = 'Last name is required.';
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'A valid email address is required.';
            }
            if (empty($phone) || !preg_match('/^[0-9+\-\s()]+$/', $phone)) {
                $errors[] = 'A valid phone number is required.';
            }

            if (empty($addr1)) $errors[] = 'Billing address line 1 is required.';
            if (empty($city)) $errors[] = 'Billing city is required.';
            if (empty($state)) $errors[] = 'Billing state/province is required.';
            if (empty($zip) || !preg_match('/^[A-Za-z0-9 \-]+$/', $zip)) {
                $errors[] = 'Billing ZIP/Postal code is invalid.';
            }
            if (empty($country) || !isInArray($country, $allowedCountries)) {
                $errors[] = 'Billing country is invalid.';
            }

            if (empty($errors)) {
                $stmt = $db->prepare("
                    UPDATE users SET
                        first_name = :first_name,
                        last_name = :last_name,
                        email = :email,
                        phone = :phone,
                        billing_address1 = :addr1,
                        billing_address2 = :addr2,
                        billing_city = :city,
                        billing_state = :state,
                        billing_zip = :zip,
                        billing_country = :country
                    WHERE id = :id
                ");
                $params = [
                    ':first_name' => $firstName,
                    ':last_name'  => $lastName,
                    ':email'      => $email,
                    ':phone'      => $phone,
                    ':addr1'      => $addr1,
                    ':addr2'      => $addr2,
                    ':city'       => $city,
                    ':state'      => $state,
                    ':zip'        => $zip,
                    ':country'    => $country,
                    ':id'         => $userId
                ];
                $success = $stmt->execute($params);
                if ($success) {
                    logProfileAttempt($userId, true, 'Profile updated successfully');
                    $alertMessage = 'Profile updated successfully.';
                } else {
                    logProfileAttempt($userId, false, 'Database update failed');
                    $errors[] = 'Unable to update profile at this time. Please try again later.';
                }
            } else {
                logProfileAttempt($userId, false, 'Validation failed: ' . implode('; ', $errors));
            }
        }
    }

    $stmt = $db->prepare("
        SELECT first_name, last_name, email, phone,
               billing_address1, billing_address2, billing_city, billing_state, billing_zip, billing_country
        FROM users
        WHERE id = :id
    ");
    $stmt->execute([':id' => $userId]);
    $current = $stmt->fetch();
    if (!$current) {
        $current = [
            'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '',
            'billing_address1' => '', 'billing_address2' => '', 'billing_city' => '',
            'billing_state' => '', 'billing_zip' => '', 'billing_country' => ''
        ];
    }
} catch (Exception $e) {
    http_response_code(500);
    logProfileAttempt($userId, false, 'Unhandled error during profile processing');
    $alertMessage = 'An unexpected error occurred. Please try again later.';
    $errors = ['An unexpected error occurred. Please try again later.'];
}
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
</head>
<body>
    <h2>User Profile</h2>
    <?php if ($alertMessage): ?>
        <div><?php echo renderValue($alertMessage); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <ul>
        <?php foreach ($errors as $err): ?>
            <li><?php echo renderValue($err); ?></li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="profile.php" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <fieldset>
            <legend>Personal Information</legend>
            <label>First Name:
                <input type="text" name="first_name" value="<?php echo renderValue($current['first_name'] ?? ''); ?>" required>
            </label><br>
            <label>Last Name:
                <input type="text" name="last_name" value="<?php echo renderValue($current['last_name'] ?? ''); ?>" required>
            </label><br>
            <label>Email:
                <input type="email" name="email" value="<?php echo renderValue($current['email'] ?? ''); ?>" required>
            </label><br>
            <label>Phone:
                <input type="text" name="phone" value="<?php echo renderValue($current['phone'] ?? ''); ?>" required>
            </label><br>
        </fieldset>

        <fieldset>
            <legend>Billing Details</legend>
            <label>Address Line 1:
                <input type="text" name="billing_addr1" value="<?php echo renderValue($current['billing_address1'] ?? ''); ?>" required>
            </label><br>
            <label>Address Line 2:
                <input type="text" name="billing_addr2" value="<?php echo renderValue($current['billing_address2'] ?? ''); ?>">
            </label><br>
            <label>City:
                <input type="text" name="billing_city" value="<?php echo renderValue($current['billing_city'] ?? ''); ?>" required>
            </label><br>
            <label>State/Province:
                <input type="text" name="billing_state" value="<?php echo renderValue($current['billing_state'] ?? ''); ?>" required>
            </label><br>
            <label>ZIP/Postal Code:
                <input type="text" name="billing_zip" value="<?php echo renderValue($current['billing_zip'] ?? ''); ?>" required>
            </label><br>
            <label>Country:
                <select name="billing_country" required>
                    <option value="">Select country</option>
                    <?php foreach ($allowedCountries as $c): ?>
                        <option value="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>" <?php if (($current['billing_country'] ?? '') === $c) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </fieldset>

        <button type="submit">Update Profile</button>
    </form>
</body>
</html>
?>