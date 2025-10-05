<?php
// profile_form.php

session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; 
}

$user_id = $_SESSION['user_id'];
$userData = [];
$errorMessage = '';
$successMessage = '';

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'user_profile_db');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("An unexpected error occurred. Please try again later.");
}

$stmt = $conn->prepare("SELECT first_name, last_name, email, phone, street_address, city, state_province, zip_postal_code, country, billing_street_address, billing_city, billing_state_province, billing_zip_postal_code, billing_country FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
    }
    $stmt->close();
} else {
    error_log("Error preparing statement for fetching user data: " . $conn->error);
}

if (isset($_SESSION['profile_update_error'])) {
    $errorMessage = $_SESSION['profile_update_error'];
    unset($_SESSION['profile_update_error']);
}
if (isset($_SESSION['profile_update_success'])) {
    $successMessage = $_SESSION['profile_update_success'];
    unset($_SESSION['profile_update_success']);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
</head>
<body>
    <h1>User Profile</h1>

    <?php if ($errorMessage): ?>
        <p style="color: red;"><?php echo htmlspecialchars($errorMessage); ?></p>
    <?php endif; ?>
    <?php if ($successMessage): ?>
        <p style="color: green;"><?php echo htmlspecialchars($successMessage); ?></p>
    <?php endif; ?>

    <form action="update_profile.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

        <h2>Personal Information</h2>
        <div>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($userData['first_name'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($userData['last_name'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="phone">Phone:</label>
            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>">
        </div>

        <h2>Address</h2>
        <div>
            <label for="street_address">Street Address:</label>
            <input type="text" id="street_address" name="street_address" value="<?php echo htmlspecialchars($userData['street_address'] ?? ''); ?>">
        </div>
        <div>
            <label for="city">City:</label>
            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($userData['city'] ?? ''); ?>">
        </div>
        <div>
            <label for="state_province">State/Province:</label>
            <input type="text" id="state_province" name="state_province" value="<?php echo htmlspecialchars($userData['state_province'] ?? ''); ?>">
        </div>
        <div>
            <label for="zip_postal_code">Zip/Postal Code:</label>
            <input type="text" id="zip_postal_code" name="zip_postal_code" value="<?php echo htmlspecialchars($userData['zip_postal_code'] ?? ''); ?>">
        </div>
        <div>
            <label for="country">Country:</label>
            <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($userData['country'] ?? ''); ?>">
        </div>

        <h2>Billing Information</h2>
        <div>
            <label for="billing_street_address">Billing Street Address:</label>
            <input type="text" id="billing_street_address" name="billing_street_address" value="<?php echo htmlspecialchars($userData['billing_street_address'] ?? ''); ?>">
        </div>
        <div>
            <label for="billing_city">Billing City:</label>
            <input type="text" id="billing_city" name="billing_city" value="<?php echo htmlspecialchars($userData['billing_city'] ?? ''); ?>">
        </div>
        <div>
            <label for="billing_state_province">Billing State/Province:</label>
            <input type="text" id="billing_state_province" name="billing_state_province" value="<?php echo htmlspecialchars($userData['billing_state_province'] ?? ''); ?>">
        </div>
        <div>
            <label for="billing_zip_postal_code">Billing Zip/Postal Code:</label>
            <input type="text" id="billing_zip_postal_code" name="billing_zip_postal_code" value="<?php echo htmlspecialchars($userData['billing_zip_postal_code'] ?? ''); ?>">
        </div>
        <div>
            <label for="billing_country">Billing Country:</label>
            <input type="text" id="billing_country" name="billing_country" value="<?php echo htmlspecialchars($userData['billing_country'] ?? ''); ?>">
        </div>

        <div>
            <button type="submit">Update Profile</button>
        </div>
    </form>
</body>
</html>
<?php
$conn->close();
?>
<?php
// update_profile.php

session_start();

define('LOG_FILE', __DIR__ . '/../logs/profile_updates.log');

function log_profile_event($userId, $status, $message = '') {
    $timestamp = date('Y-m-d H:i:s');
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $logEntry = "[$timestamp] UserID: $userId | IP: $ipAddress | Status: $status";
    if (!empty($message)) {
        $logEntry .= " | Message: $message";
    }
    $logEntry .= PHP_EOL;
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

if (!isset($_SESSION['user_id'])) {
    log_profile_event('UNKNOWN', 'UNAUTHORIZED', 'Attempt to update profile without session user_id.');
    $_SESSION['profile_update_error'] = "You must be logged in to update your profile.";
    header('Location: profile_form.php');
    exit();
}

$user_id = $_SESSION['user_id'];

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'user_profile_db');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    log_profile_event($user_id, 'FAILURE', 'Database connection failed: ' . $conn->connect_error);
    $_SESSION['profile_update_error'] = "An unexpected error occurred. Please try again later.";
    header('Location: profile_form.php');
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    log_profile_event($user_id, 'FAILURE', 'CSRF token mismatch or missing.');
    $_SESSION['profile_update_error'] = "Invalid request. Please try again.";
    header('Location: profile_form.php');
    exit();
}
unset($_SESSION['csrf_token']);


$errors = [];

function validate_input($input, $filter = FILTER_SANITIZE_STRING, $options = []) {
    if ($filter === FILTER_SANITIZE_STRING) {
        $input = trim($input);
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return filter_var(trim($input), $filter, $options);
}

$fields = [
    'first_name'             => ['required' => true, 'type' => 'string', 'max_length' => 255],
    'last_name'              => ['required' => true, 'type' => 'string', 'max_length' => 255],
    'email'                  => ['required' => true, 'type' => 'email', 'max_length' => 255],
    'phone'                  => ['required' => false, 'type' => 'phone', 'max_length' => 20],
    'street_address'         => ['required' => false, 'type' => 'string', 'max_length' => 255],
    'city'                   => ['required' => false, 'type' => 'string', 'max_length' => 100],
    'state_province'         => ['required' => false, 'type' => 'string', 'max_length' => 100],
    'zip_postal_code'        => ['required' => false, 'type' => 'string', 'max_length' => 20],
    'country'                => ['required' => false, 'type' => 'string', 'max_length' => 100],
    'billing_street_address' => ['required' => false, 'type' => 'string', 'max_length' => 255],
    'billing_city'           => ['required' => false, 'type' => 'string', 'max_length' => 100],
    'billing_state_province' => ['required' => false, 'type' => 'string', 'max_length' => 100],
    'billing_zip_postal_code'=> ['required' => false, 'type' => 'string', 'max_length' => 20],
    'billing_country'        => ['required' => false, 'type' => 'string', 'max_length' => 100],
];

$validated_data = [];

foreach ($fields as $field_name => $rules) {
    $value = $_POST[$field_name] ?? '';

    if ($rules['required'] && empty($value)) {
        $errors[] = ucfirst(str_replace('_', ' ', $field_name)) . " is required.";
        continue;
    }

    if (!empty($value)) {
        switch ($rules['type']) {
            case 'string':
                $validated_value = validate_input($value);
                if (mb_strlen($validated_value) > ($rules['max_length'] ?? PHP_INT_MAX)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field_name)) . " is too long.";
                }
                break;
            case 'email':
                $validated_value = validate_input($value, FILTER_SANITIZE_EMAIL);
                if (!filter_var($validated_value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Invalid email format.";
                }
                break;
            case 'phone':
                $validated_value = preg_replace('/[^\d\s\-\(\)]/', '', $value);
                $validated_value = validate_input($validated_value);
                if (mb_strlen($validated_value) > ($rules['max_length'] ?? PHP_INT_MAX)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field_name)) . " is too long.";
                } elseif (!preg_match('/^[\d\s\-\(\)]{7,20}$/', $validated_value)) {
                }
                break;
            default:
                $validated_value = validate_input($value);
                break;
        }
        $validated_data[$field_name] = $validated_value;
    } else {
        $validated_data[$field_name] = null;
    }
}

if (!empty($errors)) {
    $error_message = implode(" ", $errors);
    log_profile_event($user_id, 'FAILURE', 'Validation errors: ' . $error_message);
    $_SESSION['profile_update_error'] = "Please correct the following issues: " . $error_message;
    header('Location: profile_form.php');
    exit();
}

$sql = "UPDATE users SET 
            first_name = ?, 
            last_name = ?, 
            email = ?, 
            phone = ?, 
            street_address = ?, 
            city = ?, 
            state_province = ?, 
            zip_postal_code = ?, 
            country = ?, 
            billing_street_address = ?, 
            billing_city = ?, 
            billing_state_province = ?, 
            billing_zip_postal_code = ?, 
            billing_country = ?
        WHERE id = ?";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    log_profile_event($user_id, 'FAILURE', 'SQL prepare failed: ' . $conn->error);
    $_SESSION['profile_update_error'] = "An unexpected database error occurred. Please try again.";
    header('Location: profile_form.php');
    exit();
}

$bind_types = "ssssssssssssssi";

$stmt->bind_param(
    $bind_types,
    $validated_data['first_name'],
    $validated_data['last_name'],
    $validated_data['email'],
    $validated_data['phone'],
    $validated_data['street_address'],
    $validated_data['city'],
    $validated_data['state_province'],
    $validated_data['zip_postal_code'],
    $validated_data['country'],
    $validated_data['billing_street_address'],
    $validated_data['billing_city'],
    $validated_data['billing_state_province'],
    $validated_data['billing_zip_postal_code'],
    $validated_data['billing_country'],
    $user_id
);

if ($stmt->execute()) {
    log_profile_event($user_id, 'SUCCESS', 'Profile updated successfully.');
    $_SESSION['profile_update_success'] = "Your profile has been updated successfully.";
} else {
    log_profile_event($user_id, 'FAILURE', 'Database update failed: ' . $stmt->error);
    $_SESSION['profile_update_error'] = "An error occurred while updating your profile. Please try again.";
}

$stmt->close();
$conn->close();

header('Location: profile_form.php');
exit();
?>