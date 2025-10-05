<?php
// config.php
// This file contains configuration settings, database connection, session handling,
// and constants for the application.

session_start();

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_ecommerce');
define('DB_USER', 'your_db_user'); // Replace with your actual DB user
define('DB_PASS', 'your_db_password'); // Replace with your actual DB password

// Encryption Key
// !!! IMPORTANT: For production, this key must be a strong, unique, and
// securely stored secret, ideally retrieved from environment variables
// or a Key Management System (KMS), NOT hardcoded.
// It must be 32 bytes long for AES-256.
define('ENCRYPTION_KEY', 'a_very_strong_and_secret_key_of_32_bytes_long_for_aes256');

// Log file path
define('LOG_FILE', __DIR__ . '/secure_profile_log.txt');

// Simulate a logged-in user for demonstration.
// In a real application, this $_SESSION['user_id'] would be set during a successful login process.
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Example user ID
    // In a production environment, if $_SESSION['user_id'] is not set,
    // the user should be redirected to a login page.
}

// Function to safely output user data to HTML
function safe_html_output($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// Function to log profile update attempts
function log_profile_update($user_id, $status, $message = '') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = sprintf("[%s] User ID: %d, Status: %s, Message: %s\n", $timestamp, $user_id, $status, $message);
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
}

// Function to encrypt data
// Note: This is a basic encryption example. For highly sensitive data like credit cards,
// always use a PCI DSS compliant payment gateway with tokenization.
function encrypt_data($data, $key, &$iv) {
    if (empty($data)) {
        $iv = null;
        return null;
    }
    $cipher = 'aes-256-cbc';
    $iv_length = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($iv_length);
    if ($iv === false) {
        // Handle IV generation error
        return false;
    }
    $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
    if ($encrypted === false) {
        // Handle encryption error
        return false;
    }
    return $encrypted;
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
    <h1>Update User Profile</h1>

    <?php
    // Retrieve errors and old input from session if redirected from update_profile.php
    $errors = $_SESSION['errors'] ?? [];
    $old_input = $_SESSION['old_input'] ?? [];

    // Clear session variables after retrieving them to prevent re-display on refresh
    unset($_SESSION['errors']);
    unset($_SESSION['old_input']);
    ?>

    <form action="update_profile.php" method="POST">
        <div>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo safe_html_output($old_input['first_name'] ?? ''); ?>">
            <?php if (isset($errors['first_name'])): ?><span style="color: red;"><?php echo safe_html_output($errors['first_name']); ?></span><?php endif; ?>
        </div>
        <div>
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo safe_html_output($old_input['last_name'] ?? ''); ?>">
            <?php if (isset($errors['last_name'])): ?><span style="color: red;"><?php echo safe_html_output($errors['last_name']); ?></span><?php endif; ?>
        </div>
        <div>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo safe_html_output($old_input['email'] ?? ''); ?>">
            <?php if (isset($errors['email'])): ?><span style="color: red;"><?php echo safe_html_output($errors['email']); ?></span><?php endif; ?>
        </div>
        <div>
            <label for="phone_number">Phone Number:</label>
            <input type="text" id="phone_number" name="phone_number" value="<?php echo safe_html_output($old_input['phone_number'] ?? ''); ?>">
            <?php if (isset($errors['phone_number'])): ?><span style="color: red;"><?php echo safe_html_output($errors['phone_number']); ?></span><?php endif; ?>
        </div>
        <div>
            <label for="street_address">Street Address:</label>
            <input type="text" id="street_address" name="street_address" value="<?php echo safe_html_output($old_input['street_address'] ?? ''); ?>">
            <?php if (isset($errors['street_address'])): ?><span style="color: red;"><?php echo safe_html_output($errors['street_address']); ?></span><?php endif; ?>
        </div>
        <div>
            <label for="city">City:</label>
            <input type="text" id="city" name="city" value="<?php echo safe_html_output($old_input['city'] ?? ''); ?>">
            <?php if (isset($errors['city'])): ?><span style="color: red;"><?php echo safe_html_output($errors['city']); ?></span><?php endif; ?>
        </div>
        <div>
            <label for="zip_code">Zip Code:</label>
            <input type="text" id="zip_code" name="zip_code" value="<?php echo safe_html_output($old_input['zip_code'] ?? ''); ?>">
            <?php if (isset($errors['zip_code'])): ?><span style="color: red;"><?php echo safe_html_output($errors['zip_code']); ?></span><?php endif; ?>
        </div>
        <h2>Credit Card Information (Not stored in plain text)</h2>
        <div>
            <label for="credit_card_number">Credit Card Number:</label>
            <input type="text" id="credit_card_number" name="credit_card_number" value="<?php echo safe_html_output($old_input['credit_card_number'] ?? ''); ?>">
            <?php if (isset($errors['credit_card_number'])): ?><span style="color: red;"><?php echo safe_html_output($errors['credit_card_number']); ?></span><?php endif; ?>
        </div>
        <div>
            <label for="credit_card_expiry_date">Expiry Date (MM/YY):</label>
            <input type="text" id="credit_card_expiry_date" name="credit_card_expiry_date" placeholder="MM/YY" value="<?php echo safe_html_output($old_input['credit_card_expiry_date'] ?? ''); ?>">
            <?php if (isset($errors['credit_card_expiry_date'])): ?><span style="color: red;"><?php echo safe_html_output($errors['credit_card_expiry_date']); ?></span><?php endif; ?>
        </div>
        <div>
            <button type="submit">Update Profile</button>
        </div>
    </form>
</body>
</html>
<?php
// update_profile.php
// This script handles the submission of the user profile form, validates input,
// updates the database, and redirects the user.

// Make sure config.php is included, which starts the session and defines constants
require_once 'config.php';

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile_form.php');
    exit;
}

// Verify user's identity from a secure session
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php'); // Redirect to login page if not authenticated
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$old_input = []; // To store sanitized input for re-populating the form

// 1. Sanitize and store input for validation and potential re-population
$first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$street_address = filter_input(INPUT_POST, 'street_address', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$zip_code = filter_input(INPUT_POST, 'zip_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$credit_card_number = filter_input(INPUT_POST, 'credit_card_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$credit_card_expiry_date = filter_input(INPUT_POST, 'credit_card_expiry_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// Store sanitized input to repopulate the form if there are errors
$old_input = [
    'first_name' => $first_name,
    'last_name' => $last_name,
    'email' => $email,
    'phone_number' => $phone_number,
    'street_address' => $street_address,
    'city' => $city,
    'zip_code' => $zip_code,
    'credit_card_number' => $credit_card_number,
    'credit_card_expiry_date' => $credit_card_expiry_date,
];

// 2. Validate input for all fields
if (empty($first_name)) {
    $errors['first_name'] = 'First Name is required.';
}
if (empty($last_name)) {
    $errors['last_name'] = 'Last Name is required.';
}
if (empty($email)) {
    $errors['email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Invalid email format.';
}
if (empty($phone_number)) {
    $errors['phone_number'] = 'Phone Number is required.';
} elseif (!preg_match('/^\+?[0-9()\s-]+$/', $phone_number)) { // Basic phone number validation
    $errors['phone_number'] = 'Invalid phone number format.';
}
if (empty($street_address)) {
    $errors['street_address'] = 'Street Address is required.';
}
if (empty($city)) {
    $errors['city'] = 'City is required.';
}
if (empty($zip_code)) {
    $errors['zip_code'] = 'Zip Code is required.';
} elseif (!preg_match('/^\d{5}(?:[-\s]\d{4})?$/', $zip_code)) { // US Zip Code format
    $errors['zip_code'] = 'Invalid zip code format (e.g., 12345 or 12345-6789).';
}

// Credit Card Number (basic check; a real application should use a payment gateway)
if (empty($credit_card_number)) {
    $errors['credit_card_number'] = 'Credit Card Number is required.';
} elseif (!preg_match('/^\d{13,19}$/', str_replace([' ', '-'], '', $credit_card_number))) { // Allow spaces/hyphens but validate digits
    $errors['credit_card_number'] = 'Invalid credit card number format (digits only, 13-19 length).';
}

// Credit Card Expiry Date (MM/YY)
if (empty($credit_card_expiry_date)) {
    $errors['credit_card_expiry_date'] = 'Expiry Date is required.';
} elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $credit_card_expiry_date)) {
    $errors['credit_card_expiry_date'] = 'Invalid expiry date format (MM/YY).';
} else {
    list($month, $year_short) = explode('/', $credit_card_expiry_date);
    $current_year_short = (int)date('y');
    $current_month = (int)date('m');

    $submitted_year = (int)$year_short;
    $submitted_month = (int)$month;

    if ($submitted_year < $current_year_short || ($submitted_year == $current_year_short && $submitted_month < $current_month)) {
        $errors['credit_card_expiry_date'] = 'Credit card has expired.';
    }
}

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old_input'] = $old_input;
    log_profile_update($user_id, 'Failed Validation', json_encode($errors));
    header('Location: profile_form.php');
    exit;
}

// Encrypt sensitive data (Credit Card Number and Expiry Date)
$cc_iv = null;
$exp_iv = null;
$encrypted_cc_number = encrypt_data($credit_card_number, ENCRYPTION_KEY, $cc_iv);
$encrypted_cc_expiry = encrypt_data($credit_card_expiry_date, ENCRYPTION_KEY, $exp_iv);

if ($encrypted_cc_number === false || $encrypted_cc_expiry === false) {
    $_SESSION['errors']['encryption'] = 'An internal error occurred during data encryption.';
    $_SESSION['old_input'] = $old_input;
    log_profile_update($user_id, 'Encryption Failed', 'Error during openssl_encrypt or IV generation.');
    header('Location: profile_form.php');
    exit;
}

// 3. Connect to a MySQL database named db_ecommerce using PDO.
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Disable emulation for better security and performance
} catch (PDOException $e) {
    log_profile_update($user_id, 'Database Connection Failed', $e->getMessage());
    $_SESSION['errors']['database'] = 'A database error occurred. Please try again later.';
    $_SESSION['old_input'] = $old_input;
    header('Location: profile_form.php');
    exit;
}

// 4. Update the users table with the new information, based on the user's ID.
$sql = "UPDATE users SET
            first_name = :first_name,
            last_name = :last_name,
            email = :email,
            phone_number = :phone_number,
            street_address = :street_address,
            city = :city,
            zip_code = :zip_code,
            credit_card_number_encrypted = :cc_number_encrypted,
            encryption_iv_cc = :iv_cc,
            credit_card_expiry_encrypted = :cc_expiry_encrypted,
            encryption_iv_exp = :iv_exp
        WHERE id = :id";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':last_name', $last_name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':phone_number', $phone_number);
    $stmt->bindParam(':street_address', $street_address);
    $stmt->bindParam(':city', $city);
    $stmt->bindParam(':zip_code', $zip_code);
    $stmt->bindParam(':cc_number_encrypted', $encrypted_cc_number, PDO::PARAM_LOB);
    $stmt->bindParam(':iv_cc', $cc_iv, PDO::PARAM_LOB);
    $stmt->bindParam(':cc_expiry_encrypted', $encrypted_cc_expiry, PDO::PARAM_LOB);
    $stmt->bindParam(':iv_exp', $exp_iv, PDO::PARAM_LOB);
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);

    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // 4. If the update is successful, redirect the user to a profile_success.php page.
        log_profile_update($user_id, 'Success');
        header('Location: profile_success.php');
        exit;
    } else {
        // No rows affected, could mean user ID doesn't exist or data didn't change
        log_profile_update($user_id, 'No Rows Affected', 'User ID might not exist or data was identical.');
        $_SESSION['errors']['database'] = 'Profile update failed or no changes were made. Please check your input.';
        $_SESSION['old_input'] = $old_input;
        header('Location: profile_form.php');
        exit;
    }

} catch (PDOException $e) {
    // 5. If the update fails, redirect back to the form with a relevant error message.
    log_profile_update($user_id, 'Database Update Failed', $e->getMessage());
    $_SESSION['errors']['database'] = 'An unexpected error occurred during profile update. Please try again.';
    $_SESSION['old_input'] = $old_input;
    header('Location: profile_form.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Updated</title>
</head>
<body>
    <h1>Profile Updated Successfully!</h1>
    <p>Your profile information has been successfully updated.</p>
    <p><a href="profile_form.php">Go back to profile</a></p>
</body>
</html>
?>