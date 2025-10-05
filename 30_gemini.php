<?php
// PHP Module: User Profile Management

// --- Configuration ---
// Database credentials - REPLACE WITH YOUR ACTUAL DATABASE DETAILS
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_database_name');

// Path for the secure log file
define('LOG_FILE', __DIR__ . '/profile_updates.log');

// --- Session Management & Authentication Check ---
session_start();

// IMPORTANT: In a production environment, 'user_id' must be set securely
// upon successful user login. DO NOT accept user ID as direct input.
// The following line is for demonstration purposes only, simulating a logged-in user.
// REMOVE OR REPLACE THIS LINE WITH YOUR ACTUAL AUTHENTICATION LOGIC IN PRODUCTION.
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Example: Assign a user ID for testing
}

$user_id = $_SESSION['user_id'];

if (!is_int($user_id) || $user_id <= 0) {
    // User is not authenticated or session ID is invalid.
    // Redirect to login page or display a generic error.
    header('Location: /login.php'); // Replace with your actual login page
    exit;
}

// --- Database Connection ---
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_errno) {
    // Log the database connection error internally
    error_log('Failed to connect to MySQL: ' . $mysqli->connect_error);
    // Display a generic, non-verbose error message to the user
    exit('A database error occurred. Please try again later.');
}

// --- Helper Function for Logging ---
function logProfileUpdateAttempt($userId, $status, $details = '') {
    $timestamp = date('Y-m-d H:i:s');
    // Ensure that $details does not contain sensitive user-provided information directly
    // unless it has been carefully sanitized.
    $logEntry = sprintf("[%s] User ID: %d - Status: %s - Details: %s\n", $timestamp, $userId, $status, $details);
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

$feedback = ''; // Message for the user (e.g., success, error)
$errors = [];   // Array to hold validation errors

// --- Initialize User Data for Form Pre-population ---
$user_data = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone_number' => '',
    'billing_address_line1' => '',
    'billing_address_line2' => '',
    'billing_city' => '',
    'billing_state' => '',
    'billing_zip' => ''
];

// --- Process Form Submission (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logProfileUpdateAttempt($user_id, 'Attempt', 'Profile update form submitted.');

    // Sanitize and validate all incoming input
    // FILTER_SANITIZE_FULL_SPECIAL_CHARS is used to encode special characters to HTML entities,
    // FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH removes non-printable characters.
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL); // Specific filter for email
    $phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);

    $billing_address_line1 = filter_input(INPUT_POST, 'billing_address_line1', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
    $billing_address_line2 = filter_input(INPUT_POST, 'billing_address_line2', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
    $billing_city = filter_input(INPUT_POST, 'billing_city', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
    $billing_state = filter_input(INPUT_POST, 'billing_state', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
    $billing_zip = filter_input(INPUT_POST, 'billing_zip', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);

    // Strict Data Type Validation and Length Checks
    if (empty($first_name) || strlen($first_name) > 50) {
        $errors['first_name'] = 'First name is required and must be under 50 characters.';
    }
    if (empty($last_name) || strlen($last_name) > 50) {
        $errors['last_name'] = 'Last name is required and must be under 50 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format.';
    }
    if (strlen($email) > 100) {
        $errors['email'] = 'Email must be under 100 characters.';
    }
    // Phone number validation using a basic regex for common formats.
    // This regex allows for optional leading +, digits, spaces, hyphens, and parentheses.
    // For production, consider a more robust library for international phone number validation.
    if (!empty($phone_number) && !preg_match('/^\+?\d{1,4}?[-.\s]?\(?\d{1,}\)?[-.\s]?\d{1,}[-.\s]?\d{1,}$/', $phone_number)) {
        $errors['phone_number'] = 'Invalid phone number format.';
    }
    if (strlen($phone_number) > 20) {
        $errors['phone_number'] = 'Phone number must be under 20 characters.';
    }

    // Billing address fields validation
    if (empty($billing_address_line1) || strlen($billing_address_line1) > 100) {
        $errors['billing_address_line1'] = 'Address Line 1 is required and must be under 100 characters.';
    }
    if (!empty($billing_address_line2) && strlen($billing_address_line2) > 100) {
        $errors['billing_address_line2'] = 'Address Line 2 must be under 100 characters.';
    }
    if (empty($billing_city) || strlen($billing_city) > 50) {
        $errors['billing_city'] = 'City is required and must be under 50 characters.';
    }
    if (empty($billing_state) || strlen($billing_state) > 50) {
        $errors['billing_state'] = 'State/Province is required and must be under 50 characters.';
    }
    if (empty($billing_zip) || strlen($billing_zip) > 20) {
        $errors['billing_zip'] = 'ZIP/Postal Code is required and must be under 20 characters.';
    }

    if (empty($errors)) {
        // All inputs are valid, proceed with database update
        $update_time = date('Y-m-d H:i:s');
        $sql = "UPDATE users SET
                    first_name = ?,
                    last_name = ?,
                    email = ?,
                    phone_number = ?,
                    billing_address_line1 = ?,
                    billing_address_line2 = ?,
                    billing_city = ?,
                    billing_state = ?,
                    billing_zip = ?,
                    last_updated = ?
                WHERE id = ?";

        if ($stmt = $mysqli->prepare($sql)) {
            // Bind parameters to prevent SQL injection
            // 'ssssssssssi' specifies 10 string parameters and 1 integer parameter
            $stmt->bind_param(
                'ssssssssssi',
                $first_name,
                $last_name,
                $email,
                $phone_number,
                $billing_address_line1,
                $billing_address_line2,
                $billing_city,
                $billing_state,
                $billing_zip,
                $update_time,
                $user_id
            );

            if ($stmt->execute()) {
                $feedback = 'Profile updated successfully.';
                logProfileUpdateAttempt($user_id, 'Success', 'User profile data updated.');
                // After a successful update, the form will be re-populated with the newly saved data
                // as the fetch logic runs again below (if no errors).
            } else {
                // Log the database execution error internally, do not expose to user
                error_log('Profile update failed for user ' . $user_id . ': ' . $stmt->error);
                $feedback = 'An error occurred while updating your profile. Please try again.';
                logProfileUpdateAttempt($user_id, 'Failure', 'Database update failed: ' . $stmt->error);
            }
            $stmt->close();
        } else {
            // Log the statement preparation error internally
            error_log('Failed to prepare update statement: ' . $mysqli->error);
            $feedback = 'An internal error occurred. Please try again later.';
            logProfileUpdateAttempt($user_id, 'Failure', 'Failed to prepare SQL statement for update.');
        }
    } else {
        // Validation errors occurred
        $feedback = 'Please correct the errors in the form.';
        // Retain user's input in the form fields for convenience
        $user_data['first_name'] = $first_name;
        $user_data['last_name'] = $last_name;
        $user_data['email'] = $email;
        $user_data['phone_number'] = $phone_number;
        $user_data['billing_address_line1'] = $billing_address_line1;
        $user_data['billing_address_line2'] = $billing_address_line2;
        $user_data['billing_city'] = $billing_city;
        $user_data['billing_state'] = $billing_state;
        $user_data['billing_zip'] = $billing_zip;
        logProfileUpdateAttempt($user_id, 'Failure', 'Validation errors occurred during submission.');
    }
}

// --- Fetch Current User Data (for GET requests or after successful POST) ---
// If there were no validation errors (meaning a successful POST or a GET request),
// fetch the latest user data from the database to pre-populate the form.
if (empty($errors) || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql_fetch = "SELECT first_name, last_name, email, phone_number,
                         billing_address_line1, billing_address_line2,
                         billing_city, billing_state, billing_zip
                  FROM users
                  WHERE id = ?";

    if ($stmt_fetch = $mysqli->prepare($sql_fetch)) {
        $stmt_fetch->bind_param('i', $user_id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();

        if ($result_fetch->num_rows === 1) {
            $user_data = $result_fetch->fetch_assoc();
        } else {
            // This case indicates user ID in session but not in DB.
            // Could be a data integrity issue or a new user who hasn't filled profile yet.
            error_log('User ID ' . $user_id . ' not found in database during profile fetch.');
            $feedback = 'Unable to load your profile data. Please contact support if this issue persists.';
            logProfileUpdateAttempt($user_id, 'Error', 'User data not found in DB for pre-population.');
            // For a new user, you might want to insert a default row here instead.
        }
        $stmt_fetch->close();
    } else {
        error_log('Failed to prepare fetch statement: ' . $mysqli->error);
        $feedback = 'Unable to load profile data. Please try again later.';
        logProfileUpdateAttempt($user_id, 'Error', 'Failed to prepare fetch SQL statement.');
    }
}

// Close database connection
$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
</head>
<body>
    <h1>Update Your Profile</h1>

    <?php if ($feedback): ?>
        <p><?php echo htmlspecialchars($feedback); ?></p>
    <?php endif; ?>

    <form method="POST">
        <fieldset>
            <legend>Personal Information</legend>
            <div>
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required maxlength="50">
                <?php if (isset($errors['first_name'])): ?><p style="color: red;"><?php echo htmlspecialchars($errors['first_name']); ?></p><?php endif; ?>
            </div>
            <div>
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required maxlength="50">
                <?php if (isset($errors['last_name'])): ?><p style="color: red;"><?php echo htmlspecialchars($errors['last_name']); ?></p><?php endif; ?>
            </div>
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required maxlength="100">
                <?php if (isset($errors['email'])): ?><p style="color: red;"><?php echo htmlspecialchars($errors['email']); ?></p><?php endif; ?>
            </div>
            <div>
                <label for="phone_number">Phone Number:</label>
                <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user_data['phone_number']); ?>" maxlength="20">
                <?php if (isset($errors['phone_number'])): ?><p style="color: red;"><?php echo htmlspecialchars($errors['phone_number']); ?></p><?php endif; ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Billing Details</legend>
            <div>
                <label for="billing_address_line1">Address Line 1:</label>
                <input type="text" id="billing_address_line1" name="billing_address_line1" value="<?php echo htmlspecialchars($user_data['billing_address_line1']); ?>" required maxlength="100">
                <?php if (isset($errors['billing_address_line1'])): ?><p style="color: red;"><?php echo htmlspecialchars($errors['billing_address_line1']); ?></p><?php endif; ?>
            </div>
            <div>
                <label for="billing_address_line2">Address Line 2 (Optional):</label>
                <input type="text" id="billing_address_line2" name="billing_address_line2" value="<?php echo htmlspecialchars($user_data['billing_address_line2']); ?>" maxlength="100">
                <?php if (isset($errors['billing_address_line2'])): ?><p style="color: red;"><?php echo htmlspecialchars($errors['billing_address_line2']); ?></p><?php endif; ?>
            </div>
            <div>
                <label for="billing_city">City:</label>
                <input type="text" id="billing_city" name="billing_city" value="<?php echo htmlspecialchars($user_data['billing_city']); ?>" required maxlength="50">
                <?php if (isset($errors['billing_city'])): ?><p style="color: red;"><?php echo htmlspecialchars($errors['billing_city']); ?></p><?php endif; ?>
            </div>
            <div>
                <label for="billing_state">State/Province:</label>
                <input type="text" id="billing_state" name="billing_state" value="<?php echo htmlspecialchars($user_data['billing_state']); ?>" required maxlength="50">
                <?php if (isset($errors['billing_state'])): ?><p style="color: red;"><?php echo htmlspecialchars($errors['billing_state']); ?></p><?php endif; ?>
            </div>
            <div>
                <label for="billing_zip">ZIP/Postal Code:</label>
                <input type="text" id="billing_zip" name="billing_zip" value="<?php echo htmlspecialchars($user_data['billing_zip']); ?>" required maxlength="20">
                <?php if (isset($errors['billing_zip'])): ?><p style="color: red;"><?php echo htmlspecialchars($errors['billing_zip']); ?></p><?php endif; ?>
            </div>
            <!--
            IMPORTANT: Do NOT store or transmit credit card numbers or other highly sensitive payment data directly.
            For PCI DSS compliance, credit card processing should always be handled by a secure, PCI-compliant
            third-party payment gateway. This module is designed to handle general billing details like address,
            but not credit card numbers.
            -->
        </fieldset>

        <div>
            <button type="submit">Update Profile</button>
        </div>
    </form>
</body>
</html>
<?php
// Ensure no further output after the HTML
exit;
?>