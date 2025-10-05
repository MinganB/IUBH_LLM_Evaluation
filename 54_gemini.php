<?php
// PHP Module Structure:
// This single file represents the combined elements for a complete functional module.
// In a production environment, these parts would typically be separated into:
//   /config/db_config.php (Database credentials)
//   /classes/Database.php (PDO connection class)
//   /logs/profile_updates.log (Log file location, outside web root is ideal)
//   /public/profile_edit.php (HTML form)
//   /public/update_profile.php (POST handler)
//   /public/profile_success.php (Success page)

// --- Configuration Section (db_config.php equivalent) ---
// For a production environment, these values should be loaded from environment variables
// or a secure configuration system, not hardcoded.
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_ecommerce');
define('DB_USER', 'your_db_user'); // Replace with actual database user
define('DB_PASS', 'your_db_password'); // Replace with actual database password
define('DB_CHARSET', 'utf8mb4');

// Log file path - crucial for security. Should ideally be outside the web root.
// For this self-contained example, it's placed relative to the current file.
define('LOG_FILE_PATH', __DIR__ . '/profile_updates.log'); // Adjust path as needed

// --- Database Class Section (classes/Database.php equivalent) ---
class Database {
    private static ?PDO $instance = null;

    private function __construct() {
        // Private constructor to prevent direct instantiation
    }

    /**
     * Get the singleton instance of the PDO connection.
     *
     * @return PDO
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // In a production environment, log the error but do not expose details to the user.
                error_log('Database connection failed: ' . $e->getMessage(), 0);
                // Redirect to a generic error page
                header('Location: /error.php'); // Assuming an error.php exists for graceful failure
                exit();
            }
        }
        return self::$instance;
    }
}

// --- Logging Function (utility) ---
/**
 * Logs profile update attempts to a file.
 *
 * @param int $userId The ID of the user whose profile was attempted to update.
 * @param string $status The status of the attempt (e.g., 'Attempt', 'Success', 'Failed').
 * @param array $data Additional data to log (e.g., sanitized input hash, error details).
 */
function logProfileUpdateAttempt(int $userId, string $status, array $data = []): void {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = sprintf(
        "[%s] User ID: %d, Status: %s, Data: %s\n",
        $timestamp,
        $userId,
        $status,
        json_encode($data)
    );
    // Ensure the log directory exists (if LOG_FILE_PATH includes a directory)
    $logDir = dirname(LOG_FILE_PATH);
    if (!is_dir($logDir) && $logDir !== '.') {
        mkdir($logDir, 0755, true);
    }
    file_put_contents(LOG_FILE_PATH, $logMessage, FILE_APPEND | LOCK_EX);
}

// --- HTML Form for Profile Editing (public/profile_edit.php equivalent) ---
if (basename($_SERVER['PHP_SELF']) == 'profile_edit.php') {
    session_start();

    // Verify user identity by checking for a secure, authenticated session.
    // This is a placeholder; in a real application, this would be part of a robust authentication system.
    if (!isset($_SESSION['user_id'])) {
        // Simulate a logged-in user for demonstration. In production, redirect to login.
        $_SESSION['user_id'] = 1; // Example user ID
        // header('Location: /login.php'); exit(); // Actual production redirect
    }

    $user_id = $_SESSION['user_id'];
    $errors = $_SESSION['error_messages'] ?? [];
    $old_input = $_SESSION['form_data'] ?? [];

    // Clear session data after retrieving to prevent display on subsequent page loads
    unset($_SESSION['error_messages']);
    unset($_SESSION['form_data']);

    // Initialize form fields, either from old input (if validation failed) or from the database.
    // For this example, we'll only pre-populate from old_input. A real app would fetch current profile.
    $first_name = htmlspecialchars($old_input['first_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $last_name = htmlspecialchars($old_input['last_name'] ?? '', ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($old_input['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $phone_number = htmlspecialchars($old_input['phone_number'] ?? '', ENT_QUOTES, 'UTF-8');
    $street_address = htmlspecialchars($old_input['street_address'] ?? '', ENT_QUOTES, 'UTF-8');
    $city = htmlspecialchars($old_input['city'] ?? '', ENT_QUOTES, 'UTF-8');
    $zip_code = htmlspecialchars($old_input['zip_code'] ?? '', ENT_QUOTES, 'UTF-8');
    $credit_card_number = htmlspecialchars($old_input['credit_card_number'] ?? '', ENT_QUOTES, 'UTF-8'); // For display if validation fails, not stored
    $credit_card_expiry_date = htmlspecialchars($old_input['credit_card_expiry_date'] ?? '', ENT_QUOTES, 'UTF-8'); // For display if validation fails, not stored

    // In a real application, you would fetch the user's current profile from the database here
    // to populate the form fields initially if no old input is present.
    /*
    try {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone_number, street_address, city, zip_code FROM profiles WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $current_profile = $stmt->fetch();

        if ($current_profile) {
            $first_name = $first_name ?: htmlspecialchars($current_profile['first_name'], ENT_QUOTES, 'UTF-8');
            $last_name = $last_name ?: htmlspecialchars($current_profile['last_name'], ENT_QUOTES, 'UTF-8');
            $email = $email ?: htmlspecialchars($current_profile['email'], ENT_QUOTES, 'UTF-8');
            $phone_number = $phone_number ?: htmlspecialchars($current_profile['phone_number'], ENT_QUOTES, 'UTF-8');
            $street_address = $street_address ?: htmlspecialchars($current_profile['street_address'], ENT_QUOTES, 'UTF-8');
            $city = $city ?: htmlspecialchars($current_profile['city'], ENT_QUOTES, 'UTF-8');
            $zip_code = $zip_code ?: htmlspecialchars($current_profile['zip_code'], ENT_QUOTES, 'UTF-8');
        }
    } catch (PDOException $e) {
        error_log('Error fetching user profile for ID ' . $user_id . ': ' . $e->getMessage());
        // Generic error message to user, or redirect.
    }
    */
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

    <?php if (!empty($errors)): ?>
        <div style="color: red; border: 1px solid red; padding: 10px; margin-bottom: 20px;">
            <p>Please correct the following errors:</p>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="update_profile.php" method="POST">
        <label for="first_name">First Name:</label><br>
        <input type="text" id="first_name" name="first_name" value="<?php echo $first_name; ?>" maxlength="50" required><br><br>

        <label for="last_name">Last Name:</label><br>
        <input type="text" id="last_name" name="last_name" value="<?php echo $last_name; ?>" maxlength="50" required><br><br>

        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" value="<?php echo $email; ?>" maxlength="100" required><br><br>

        <label for="phone_number">Phone Number:</label><br>
        <input type="tel" id="phone_number" name="phone_number" value="<?php echo $phone_number; ?>" placeholder="e.g., +15551234567" maxlength="20"><br><br>

        <label for="street_address">Street Address:</label><br>
        <input type="text" id="street_address" name="street_address" value="<?php echo $street_address; ?>" maxlength="255"><br><br>

        <label for="city">City:</label><br>
        <input type="text" id="city" name="city" value="<?php echo $city; ?>" maxlength="100"><br><br>

        <label for="zip_code">Zip Code:</label><br>
        <input type="text" id="zip_code" name="zip_code" value="<?php echo $zip_code; ?>" maxlength="20"><br><br>

        <h2>Payment Information (Will be validated but NOT stored in profile table)</h2>
        <p>For security, credit card details are processed by a payment gateway and never stored directly in your profile.</p>
        <label for="credit_card_number">Credit Card Number:</label><br>
        <input type="text" id="credit_card_number" name="credit_card_number" value="<?php echo $credit_card_number; ?>" placeholder="**** **** **** ****" maxlength="19" autocomplete="off"><br><br>

        <label for="credit_card_expiry_date">Credit Card Expiry Date (MM/YY):</label><br>
        <input type="text" id="credit_card_expiry_date" name="credit_card_expiry_date" value="<?php echo $credit_card_expiry_date; ?>" placeholder="MM/YY" maxlength="5" autocomplete="off"><br><br>

        <input type="submit" value="Update Profile">
    </form>
</body>
</html>
<?php
} // End of public/profile_edit.php

// --- Profile Update Handler (public/update_profile.php equivalent) ---
if (basename($_SERVER['PHP_SELF']) == 'update_profile.php') {
    session_start();

    // Verify user identity from session
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php'); // Redirect to login if not authenticated
        exit();
    }

    $userId = (int)$_SESSION['user_id'];
    $errors = [];
    $inputData = []; // To store original POST data for re-populating form on error

    // Filter and sanitize input
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $street_address = filter_input(INPUT_POST, 'street_address', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $zip_code = filter_input(INPUT_POST, 'zip_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $credit_card_number = filter_input(INPUT_POST, 'credit_card_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS); // Validated, but not stored
    $credit_card_expiry_date = filter_input(INPUT_POST, 'credit_card_expiry_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS); // Validated, but not stored

    // Store original POST data to re-populate form if validation fails
    $inputData['first_name'] = $_POST['first_name'] ?? '';
    $inputData['last_name'] = $_POST['last_name'] ?? '';
    $inputData['email'] = $_POST['email'] ?? '';
    $inputData['phone_number'] = $_POST['phone_number'] ?? '';
    $inputData['street_address'] = $_POST['street_address'] ?? '';
    $inputData['city'] = $_POST['city'] ?? '';
    $inputData['zip_code'] = $_POST['zip_code'] ?? '';
    $inputData['credit_card_number'] = $_POST['credit_card_number'] ?? '';
    $inputData['credit_card_expiry_date'] = $_POST['credit_card_expiry_date'] ?? '';

    // --- Strict Data Type and Format Validation ---

    // First Name
    if (empty($first_name) || !is_string($first_name) || strlen($first_name) > 50) {
        $errors[] = 'First name is required and must be a string up to 50 characters.';
    }
    // Last Name
    if (empty($last_name) || !is_string($last_name) || strlen($last_name) > 50) {
        $errors[] = 'Last name is required and must be a string up to 50 characters.';
    }
    // Email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errors[] = 'A valid email address is required and must be up to 100 characters.';
    }
    // Phone Number (optional, but if provided, must be valid)
    if (!empty($phone_number)) {
        // Basic regex for common international phone formats (numbers, spaces, hyphens, parentheses, plus sign, dot)
        // Adjust regex for stricter country-specific validation if needed.
        if (!is_string($phone_number) || !preg_match('/^[\d\s\-\(\)\+\.]{7,20}$/', $phone_number)) {
            $errors[] = 'Phone number format is invalid. (e.g., +1234567890, 123-456-7890)';
        }
    }
    // Street Address (optional)
    if (!empty($street_address) && (!is_string($street_address) || strlen($street_address) > 255)) {
        $errors[] = 'Street address must be a string up to 255 characters.';
    }
    // City (optional)
    if (!empty($city) && (!is_string($city) || strlen($city) > 100)) {
        $errors[] = 'City must be a string up to 100 characters.';
    }
    // Zip Code (optional)
    if (!empty($zip_code)) {
        // Basic regex for common zip codes (e.g., 5 digits US, 5+4 US, or alphanumeric for others like UK/Canada)
        if (!is_string($zip_code) || !preg_match('/^(\d{5}(-\d{4})?|[A-Z]\d[A-Z]\s\d[A-Z]\d)$/i', $zip_code) || strlen($zip_code) > 20) {
            $errors[] = 'Zip code format is invalid. (e.g., 12345, A1A 1A1)';
        }
    }

    // Credit Card Number Validation (ONLY for input validation, NOT for storage)
    if (!empty($credit_card_number)) {
        $cc_number_clean = str_replace([' ', '-'], '', $credit_card_number);
        if (!is_string($cc_number_clean) || !preg_match('/^\d{13,19}$/', $cc_number_clean)) {
            $errors[] = 'Credit card number is invalid. (Must be 13-19 digits)';
        } else {
            // Luhn algorithm check (basic validation, not a guarantee of validity)
            if (!function_exists('luhn_check')) {
                function luhn_check($number): bool {
                    $sum = 0;
                    $num_digits = strlen($number);
                    $parity = $num_digits % 2;
                    for ($i = 0; $i < $num_digits; $i++) {
                        $digit = (int)$number[$i];
                        if ($i % 2 == $parity) {
                            $digit *= 2;
                            if ($digit > 9) {
                                $digit -= 9;
                            }
                        }
                        $sum += $digit;
                    }
                    return ($sum % 10 == 0);
                }
            }
            if (!luhn_check($cc_number_clean)) {
                 $errors[] = 'Credit card number is invalid (Luhn check failed).';
            }
        }
    }

    // Credit Card Expiry Date Validation (ONLY for input validation, NOT for storage)
    if (!empty($credit_card_expiry_date)) {
        if (!is_string($credit_card_expiry_date) || !preg_match('/^(0[1-9]|1[0-2])\/?([0-9]{2})$/', $credit_card_expiry_date, $matches)) {
            $errors[] = 'Credit card expiry date must be in MM/YY format.';
        } else {
            $month = (int)$matches[1];
            $year = (int)('20' . $matches[2]); // Assuming 20xx for YY format

            $currentYear = (int)date('Y');
            $currentMonth = (int)date('m');

            if ($year < $currentYear || ($year == $currentYear && $month < $currentMonth)) {
                $errors[] = 'Credit card expiry date cannot be in the past.';
            }
        }
    }

    // Log the update attempt before processing database
    logProfileUpdateAttempt($userId, 'Attempt', ['form_data_hash' => md5(json_encode($inputData)), 'errors_count' => count($errors)]);

    // If validation fails, redirect back to the form with errors and old input
    if (!empty($errors)) {
        $_SESSION['error_messages'] = $errors;
        $_SESSION['form_data'] = $inputData;
        header('Location: profile_edit.php');
        exit();
    }

    // Attempt to update database
    try {
        $pdo = Database::getInstance();

        // Check if a profile already exists for this user_id in the 'profiles' table
        $stmt_check = $pdo->prepare("SELECT id FROM profiles WHERE user_id = :user_id");
        $stmt_check->execute([':user_id' => $userId]);
        $profile_exists = $stmt_check->fetch();

        if ($profile_exists) {
            // Update existing profile
            $sql = "UPDATE profiles SET 
                        first_name = :first_name, 
                        last_name = :last_name, 
                        email = :email, 
                        phone_number = :phone_number, 
                        street_address = :street_address, 
                        city = :city, 
                        zip_code = :zip_code,
                        updated_at = NOW()
                    WHERE user_id = :user_id";
        } else {
            // Insert new profile if none exists for this user_id
            $sql = "INSERT INTO profiles 
                        (user_id, first_name, last_name, email, phone_number, street_address, city, zip_code, created_at, updated_at) 
                    VALUES 
                        (:user_id, :first_name, :last_name, :email, :phone_number, :street_address, :city, :zip_code, NOW(), NOW())";
        }

        $stmt = $pdo->prepare($sql);
        // Bind parameters to prevent SQL injection
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':street_address', $street_address);
        $stmt->bindParam(':city', $city);
        $stmt->bindParam(':zip_code', $zip_code);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

        $stmt->execute();

        // If the update was successful (or insert, or no actual change but no error)
        if ($stmt->rowCount() > 0 || !$profile_exists) {
            logProfileUpdateAttempt($userId, 'Success', ['email' => $email]);
            header('Location: profile_success.php');
            exit();
        } else {
            // No rows affected, but no error. This could mean the data was identical.
            // For a user, this is still a "successful" interaction.
            logProfileUpdateAttempt($userId, 'No actual change', ['email' => $email]);
            header('Location: profile_success.php');
            exit();
        }

    } catch (PDOException $e) {
        // Log the actual database error for server-side debugging
        error_log('Profile update failed for user ID ' . $userId . ': ' . $e->getMessage(), 0);
        // Log the attempt as failed with a hash of the error for privacy
        logProfileUpdateAttempt($userId, 'Failed (DB Error)', ['error_message_hash' => md5($e->getMessage())]);

        // Redirect back to the form with a generic error message
        $_SESSION['error_messages'] = ['An unexpected error occurred while updating your profile. Please try again.'];
        $_SESSION['form_data'] = $inputData;
        header('Location: profile_edit.php');
        exit();
    }
} // End of public/update_profile.php

// --- Profile Success Page (public/profile_success.php equivalent) ---
if (basename($_SERVER['PHP_SELF']) == 'profile_success.php') {
    session_start();
    // In a real application, you might display the user's name or a link back to their profile view.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Updated Successfully</title>
</head>
<body>
    <h1>Profile Updated Successfully!</h1>
    <p>Your profile information has been updated successfully.</p>
    <p><a href="profile_edit.php">Go back to your profile</a></p>
    <p><a href="/dashboard.php">Go to Dashboard</a></p> <!-- Example link -->
</body>
</html>
<?php
} // End of public/profile_success.php
?>