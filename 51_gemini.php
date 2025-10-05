<?php
// config.php
// Configuration file for database connection and other constants.

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_ecommerce');
define('DB_USER', 'db_user'); // Replace with your database username
define('DB_PASS', 'db_password'); // Replace with your database password
define('DB_CHARSET', 'utf8mb4');

// Log file path (ensure this directory is writable by the web server)
define('LOG_FILE', __DIR__ . '/logs/profile_updates.log');

// Path to the profile form
define('PROFILE_FORM_PAGE', 'profile.php');
// Path to the success page
define('PROFILE_SUCCESS_PAGE', 'profile_success.php');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

<?php
// functions.php
// Contains helper functions for logging and database connection.

/**
 * Establishes a PDO database connection.
 *
 * @return PDO
 * @throws PDOException If the connection fails.
 */
function getDbConnection() {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Log the error but don't expose it directly to the user
        logProfileUpdate('DATABASE_CONNECTION_ERROR', 0, 'Failed to connect to database: ' . $e->getMessage());
        throw new PDOException("Unable to connect to the database. Please try again later.");
    }
}

/**
 * Logs profile update attempts and their outcomes.
 *
 * @param string $eventType A string describing the event (e.g., 'SUCCESS', 'VALIDATION_ERROR', 'DB_ERROR').
 * @param int $userId The ID of the user attempting the update.
 * @param string $message A detailed message about the event.
 */
function logProfileUpdate(string $eventType, int $userId, string $message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = sprintf("[%s] [USER_ID: %d] [%s] %s" . PHP_EOL, $timestamp, $userId, $eventType, $message);

    // Ensure the log directory exists
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    error_log($logEntry, 3, LOG_FILE);
}

/**
 * Validates a credit card number using the Luhn algorithm.
 * This is for format validation only, not security.
 *
 * @param string $cardNumber The credit card number to validate.
 * @return bool True if valid, false otherwise.
 */
function isValidCreditCardNumber(string $cardNumber): bool {
    $cardNumber = preg_replace('/\D/', '', $cardNumber); // Remove non-digits
    if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
        return false;
    }

    $sum = 0;
    $numDigits = strlen($cardNumber);
    $parity = $numDigits % 2;

    for ($i = 0; $i < $numDigits; $i++) {
        $digit = (int)$cardNumber[$i];
        if (($i % 2) == $parity) {
            $digit *= 2;
        }
        if ($digit > 9) {
            $digit -= 9;
        }
        $sum += $digit;
    }
    return ($sum % 10) == 0;
}

/**
 * Validates a credit card expiry date.
 *
 * @param int $month Expiry month (1-12).
 * @param int $year Expiry year (e.g., 2023).
 * @return bool True if valid and not expired, false otherwise.
 */
function isValidCreditCardExpiryDate(int $month, int $year): bool {
    if ($month < 1 || $month > 12) {
        return false;
    }

    $currentYear = (int)date('Y');
    $currentMonth = (int)date('m');

    // Check if the year is in the past
    if ($year < $currentYear) {
        return false;
    }
    // Check if the month in the current year is in the past
    if ($year == $currentYear && $month < $currentMonth) {
        return false;
    }

    // A reasonable future limit for expiry, e.g., 15 years from now
    if ($year > $currentYear + 15) {
        return false;
    }

    return true;
}

<?php
// profile.php
// HTML form for user profile updates.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    // In a real application, redirect to login page
    header('Location: /login.php');
    exit();
}

// Initialize an empty array for existing user data.
// In a full implementation, you would fetch this from the database
// to pre-fill the form fields. For this specific request focused on update logic,
// we will assume a user arrives to this page to *input* new data,
// but placeholders are provided for clarity.
$user_data = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone_number' => '',
    'street_address' => '',
    'city' => '',
    'zip_code' => ''
];

// If there's an error message from update_profile.php, display it
$errorMessage = '';
if (isset($_GET['error'])) {
    $errorMessage = htmlspecialchars($_GET['error']);
}

// If there's previous input data from a failed submission, pre-fill it (basic example)
// In a real app, you might pass all POST data back or specific errors per field
$previousInput = $_SESSION['last_form_data'] ?? [];
unset($_SESSION['last_form_data']); // Clear after use

foreach ($user_data as $key => $value) {
    if (isset($previousInput[$key])) {
        $user_data[$key] = htmlspecialchars($previousInput[$key]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
</head>
<body>
    <h1>Edit Your Profile</h1>

    <?php if ($errorMessage): ?>
        <p style="color: red;"><?php echo $errorMessage; ?></p>
    <?php endif; ?>

    <form action="update_profile.php" method="POST">
        <p>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo $user_data['first_name']; ?>" required>
        </p>
        <p>
            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo $user_data['last_name']; ?>" required>
        </p>
        <p>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo $user_data['email']; ?>" required>
        </p>
        <p>
            <label for="phone_number">Phone Number:</label>
            <input type="tel" id="phone_number" name="phone_number" value="<?php echo $user_data['phone_number']; ?>" pattern="[0-9]{3}[0-9]{3}[0-9]{4}|[0-9]{10}" placeholder="e.g., 1234567890">
        </p>
        <p>
            <label for="street_address">Street Address:</label>
            <input type="text" id="street_address" name="street_address" value="<?php echo $user_data['street_address']; ?>">
        </p>
        <p>
            <label for="city">City:</label>
            <input type="text" id="city" name="city" value="<?php echo $user_data['city']; ?>">
        </p>
        <p>
            <label for="zip_code">Zip Code:</label>
            <input type="text" id="zip_code" name="zip_code" value="<?php echo $user_data['zip_code']; ?>" pattern="[0-9]{5}|[0-9]{5}-[0-9]{4}" placeholder="e.g., 12345">
        </p>
        <p>
            <label for="credit_card_number">Credit Card Number:</label>
            <input type="text" id="credit_card_number" name="credit_card_number" pattern="[0-9]{13,19}" placeholder="XXXX XXXX XXXX XXXX" maxlength="19">
            <small> (Not stored, for validation only)</small>
        </p>
        <p>
            <label for="credit_card_expiry_month">Expiry Month (MM):</label>
            <input type="number" id="credit_card_expiry_month" name="credit_card_expiry_month" min="1" max="12" placeholder="MM" maxlength="2">
            <label for="credit_card_expiry_year">Expiry Year (YYYY):</label>
            <input type="number" id="credit_card_expiry_year" name="credit_card_expiry_year" min="<?php echo date('Y'); ?>" max="<?php echo date('Y') + 15; ?>" placeholder="YYYY" maxlength="4">
        </p>
        <p>
            <button type="submit">Update Profile</button>
        </p>
    </form>
</body>
</html>

<?php
// update_profile.php
// Handles form submission, validation, database update, and redirection.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login or a generic error page if not authenticated
    header('Location: /login.php'); // Replace with your actual login page
    exit();
}

$userId = (int)$_SESSION['user_id'];
$errors = [];
$formData = [];

// Ensure the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . PROFILE_FORM_PAGE . '?error=' . urlencode('Invalid request method.'));
    exit();
}

// Sanitize and validate inputs
$formData['first_name'] = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
$formData['last_name'] = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
$formData['email'] = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$formData['phone_number'] = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING); // Sanitize as string, then validate format
$formData['street_address'] = filter_input(INPUT_POST, 'street_address', FILTER_SANITIZE_STRING);
$formData['city'] = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_STRING);
$formData['zip_code'] = filter_input(INPUT_POST, 'zip_code', FILTER_SANITIZE_STRING);

// Credit card data - validated but NOT stored in the users table
$creditCardNumber = filter_input(INPUT_POST, 'credit_card_number', FILTER_SANITIZE_STRING);
$creditCardExpiryMonth = filter_input(INPUT_POST, 'credit_card_expiry_month', FILTER_VALIDATE_INT);
$creditCardExpiryYear = filter_input(INPUT_POST, 'credit_card_expiry_year', FILTER_VALIDATE_INT);

// Basic validation checks
if (empty($formData['first_name'])) {
    $errors[] = 'First Name is required.';
}
if (empty($formData['last_name'])) {
    $errors[] = 'Last Name is required.';
}
if (empty($formData['email'])) {
    $errors[] = 'Email is required.';
} elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format.';
}

// Phone number validation (optional field, but if provided, must be valid)
if (!empty($formData['phone_number']) && !preg_match('/^\d{10,15}$/', preg_replace('/[^0-9]/', '', $formData['phone_number']))) {
    $errors[] = 'Invalid phone number format. Must be 10-15 digits.';
}

// Zip code validation (optional field, but if provided, must be valid)
if (!empty($formData['zip_code']) && !preg_match('/^\d{5}(?:-\d{4})?$/', $formData['zip_code'])) {
    $errors[] = 'Invalid zip code format. Must be 5 digits or 5+4 digits.';
}

// Credit card validation (only if fields are provided)
$creditCardProvided = !empty($creditCardNumber) || !empty($creditCardExpiryMonth) || !empty($creditCardExpiryYear);

if ($creditCardProvided) {
    if (empty($creditCardNumber)) {
        $errors[] = 'Credit Card Number is required if any credit card field is provided.';
    } elseif (!isValidCreditCardNumber($creditCardNumber)) {
        $errors[] = 'Invalid credit card number.';
    }

    if (empty($creditCardExpiryMonth) || empty($creditCardExpiryYear)) {
        $errors[] = 'Credit Card Expiry Month and Year are required if credit card number is provided.';
    } elseif (!isValidCreditCardExpiryDate($creditCardExpiryMonth, $creditCardExpiryYear)) {
        $errors[] = 'Invalid credit card expiry date. It might be in the past or malformed.';
    }
}

// If there are validation errors, redirect back to the form
if (!empty($errors)) {
    // Store form data to re-populate fields (excluding sensitive CC data)
    $_SESSION['last_form_data'] = $formData;
    logProfileUpdate('VALIDATION_ERROR', $userId, 'Profile update failed due to validation errors: ' . implode(', ', $errors));
    header('Location: ' . PROFILE_FORM_PAGE . '?error=' . urlencode(implode(' ', $errors)));
    exit();
}

// If validation passes, attempt to update the database
try {
    $pdo = getDbConnection();

    // Check if the email already exists for another user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
    $stmt->execute([':email' => $formData['email'], ':id' => $userId]);
    if ($stmt->fetch()) {
        $errors[] = 'This email is already registered by another user.';
    }

    if (!empty($errors)) {
        $_SESSION['last_form_data'] = $formData;
        logProfileUpdate('VALIDATION_ERROR', $userId, 'Profile update failed: ' . implode(', ', $errors));
        header('Location: ' . PROFILE_FORM_PAGE . '?error=' . urlencode(implode(' ', $errors)));
        exit();
    }

    $sql = "UPDATE users SET 
                first_name = :first_name, 
                last_name = :last_name, 
                email = :email, 
                phone_number = :phone_number, 
                street_address = :street_address, 
                city = :city, 
                zip_code = :zip_code,
                updated_at = NOW()
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':first_name' => $formData['first_name'],
        ':last_name' => $formData['last_name'],
        ':email' => $formData['email'],
        ':phone_number' => $formData['phone_number'],
        ':street_address' => $formData['street_address'],
        ':city' => $formData['city'],
        ':zip_code' => $formData['zip_code'],
        ':id' => $userId
    ]);

    // Check if any rows were affected
    if ($stmt->rowCount() > 0) {
        logProfileUpdate('SUCCESS', $userId, 'User profile updated successfully.');
        header('Location: ' . PROFILE_SUCCESS_PAGE);
        exit();
    } else {
        // No rows affected means either data was identical or user ID not found
        // For a logged-in user, it's more likely data was identical.
        // We can treat this as a success for the user's perspective, or inform them no changes were made.
        // For logging, it's still an 'update attempt' even if no change.
        logProfileUpdate('INFO', $userId, 'User profile update attempted, but no changes were made (data might be identical or user not found).');
        header('Location: ' . PROFILE_SUCCESS_PAGE . '?message=' . urlencode('Profile updated, or no changes were necessary.'));
        exit();
    }

} catch (PDOException $e) {
    // Log the database error
    logProfileUpdate('DB_ERROR', $userId, 'Database update failed: ' . $e->getMessage());
    // Redirect with a generic error message
    header('Location: ' . PROFILE_FORM_PAGE . '?error=' . urlencode('An unexpected error occurred. Please try again.'));
    exit();
} catch (Exception $e) {
    // Catch any other unexpected errors
    logProfileUpdate('UNEXPECTED_ERROR', $userId, 'An unexpected error occurred: ' . $e->getMessage());
    header('Location: ' . PROFILE_FORM_PAGE . '?error=' . urlencode('An unexpected error occurred. Please try again.'));
    exit();
}
?>

<?php
// profile_success.php
// Simple success page for profile updates.

require_once __DIR__ . '/config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php'); // Replace with your actual login page
    exit();
}

$message = 'Your profile has been successfully updated.';
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Update Success</title>
</head>
<body>
    <h1>Profile Update Successful!</h1>
    <p><?php echo $message; ?></p>
    <p><a href="/">Go back to home</a></p> <!-- Replace with your actual home page link -->
</body>
</html>
?>