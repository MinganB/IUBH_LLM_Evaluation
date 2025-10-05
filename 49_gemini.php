<?php
// File: edit_profile.php

// Simulate fetching existing user data. In a real application, this would
// involve querying the database based on a logged-in user's ID from a session.
// For demonstration, a static user ID and pre-filled data are used.
$user = [
    'user_id' => 1, // Example user ID
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john.doe@example.com',
    'phone_number' => '123-456-7890',
    'street_address' => '123 Main St',
    'city' => 'Anytown',
    'zip_code' => '12345',
    'credit_card_number' => '', // Credit card number should never be pre-filled for security
    'credit_card_expiry_date' => '' // Expiry date should never be pre-filled for security
];

// Handle error messages passed from update_profile.php
$errorMessage = '';
if (isset($_GET['error'])) {
    $errorMessage = htmlspecialchars($_GET['error']);
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
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">

        <p>
            <label for="first_name">First Name:</label><br>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
        </p>
        <p>
            <label for="last_name">Last Name:</label><br>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
        </p>
        <p>
            <label for="email">Email:</label><br>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
        </p>
        <p>
            <label for="phone_number">Phone Number:</label><br>
            <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number']); ?>">
        </p>
        <p>
            <label for="street_address">Street Address:</label><br>
            <input type="text" id="street_address" name="street_address" value="<?php echo htmlspecialchars($user['street_address']); ?>">
        </p>
        <p>
            <label for="city">City:</label><br>
            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user['city']); ?>">
        </p>
        <p>
            <label for="zip_code">Zip Code:</label><br>
            <input type="text" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($user['zip_code']); ?>">
        </p>
        <p>
            <label for="credit_card_number">Credit Card Number:</label><br>
            <input type="text" id="credit_card_number" name="credit_card_number" value="" placeholder="Enter new card or leave blank to keep existing">
        </p>
        <p>
            <label for="credit_card_expiry_date">Credit Card Expiry (MM/YY):</label><br>
            <input type="text" id="credit_card_expiry_date" name="credit_card_expiry_date" value="" placeholder="MM/YY">
        </p>
        <p>
            <button type="submit">Update Profile</button>
        </p>
    </form>
</body>
</html>

<?php
// File: update_profile.php

// Database configuration
$dbHost = 'localhost';
$dbName = 'db_ecommerce';
$dbUser = 'your_db_user'; // Replace with your actual database username
$dbPass = 'your_db_password'; // Replace with your actual database password

// Function to redirect with an error message to the profile edit page
function redirectWithError($message) {
    header('Location: edit_profile.php?error=' . urlencode($message));
    exit();
}

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithError('Invalid request method.');
}

// Collect and sanitize input data
$userId = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
$firstName = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$lastName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$phoneNumber = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$streetAddress = filter_input(INPUT_POST, 'street_address', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$zipCode = filter_input(INPUT_POST, 'zip_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$creditCardNumber = filter_input(INPUT_POST, 'credit_card_number', FILTER_SANITIZE_NUMBER_INT); // Removes non-numeric characters
$creditCardExpiryDate = filter_input(INPUT_POST, 'credit_card_expiry_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

$errors = [];

// Input validation
if (empty($userId) || !is_numeric($userId)) {
    $errors[] = 'Invalid user ID.';
}
if (empty($firstName) || !preg_match("/^[a-zA-Z-' ]*$/", $firstName) || strlen($firstName) > 50) {
    $errors[] = 'Invalid first name.';
}
if (empty($lastName) || !preg_match("/^[a-zA-Z-' ]*$/", $lastName) || strlen($lastName) > 50) {
    $errors[] = 'Invalid last name.';
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address.';
}
if (!empty($phoneNumber) && !preg_match("/^[0-9\s\-\(\)\+]{7,20}$/", $phoneNumber)) {
    $errors[] = 'Invalid phone number format.';
}
if (empty($streetAddress) || strlen($streetAddress) > 100) {
    $errors[] = 'Street address is required and must be less than 100 characters.';
}
if (empty($city) || !preg_match("/^[a-zA-Z-' ]*$/", $city) || strlen($city) > 50) {
    $errors[] = 'Invalid city name.';
}
if (empty($zipCode) || !preg_match("/^[0-9]{5}(?:-[0-9]{4})?$/", $zipCode)) { // Supports 5-digit and 5+4 zip codes
    $errors[] = 'Invalid zip code format.';
}

// Credit Card Number validation (if provided)
if (!empty($creditCardNumber)) {
    $creditCardNumber = str_replace([' ', '-'], '', $creditCardNumber); // Remove spaces/dashes for validation
    if (!preg_match("/^[0-9]{13,19}$/", $creditCardNumber)) { // Typical CC lengths
        $errors[] = 'Invalid credit card number format.';
    }
}

// Credit Card Expiry Date validation (if provided)
if (!empty($creditCardExpiryDate)) {
    if (!preg_match('/^(0[1-9]|1[0-2])\/?([0-9]{2})$/', $creditCardExpiryDate, $matches)) {
        $errors[] = 'Invalid credit card expiry date format (MM/YY).';
    } else {
        $month = (int)$matches[1];
        $year = (int)$matches[2]; // Two-digit year

        $currentYear = (int)date('y'); // Current two-digit year
        $currentMonth = (int)date('m');

        if ($year < $currentYear || ($year == $currentYear && $month < $currentMonth)) {
            $errors[] = 'Credit card expiry date must be in the future.';
        }
    }
}

// If any validation errors, redirect back to the form with messages
if (!empty($errors)) {
    redirectWithError(implode('<br>', $errors));
}

// Attempt database connection
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // For real prepared statements
} catch (PDOException $e) {
    // Log the error for debugging, but show a generic message to the user
    error_log("Database connection failed: " . $e->getMessage());
    redirectWithError('Database connection failed. Please try again later.');
}

try {
    // Dynamically build the UPDATE query based on provided (non-empty) fields
    $updateFields = [];
    $params = [':user_id' => $userId];

    if (!empty($firstName)) {
        $updateFields[] = 'first_name = :first_name';
        $params[':first_name'] = $firstName;
    }
    if (!empty($lastName)) {
        $updateFields[] = 'last_name = :last_name';
        $params[':last_name'] = $lastName;
    }
    if (!empty($email)) {
        $updateFields[] = 'email = :email';
        $params[':email'] = $email;
    }
    if (!empty($phoneNumber)) {
        $updateFields[] = 'phone_number = :phone_number';
        $params[':phone_number'] = $phoneNumber;
    }
    if (!empty($streetAddress)) {
        $updateFields[] = 'street_address = :street_address';
        $params[':street_address'] = $streetAddress;
    }
    if (!empty($city)) {
        $updateFields[] = 'city = :city';
        $params[':city'] = $city;
    }
    if (!empty($zipCode)) {
        $updateFields[] = 'zip_code = :zip_code';
        $params[':zip_code'] = $zipCode;
    }
    if (!empty($creditCardNumber)) {
        $updateFields[] = 'credit_card_number = :credit_card_number';
        $params[':credit_card_number'] = $creditCardNumber;
    }
    if (!empty($creditCardExpiryDate)) {
        $updateFields[] = 'credit_card_expiry_date = :credit_card_expiry_date';
        $params[':credit_card_expiry_date'] = $creditCardExpiryDate;
    }

    // If no fields are provided for update, assume it's a "no change" scenario
    // and redirect to success.
    if (empty($updateFields)) {
        header('Location: profile_success.php');
        exit();
    }

    $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // If the update affected rows or no change was needed (meaning current data matches),
    // redirect to success. If user_id was invalid, rowCount would be 0,
    // but the query itself didn't fail.
    header('Location: profile_success.php');
    exit();

} catch (PDOException $e) {
    // Log the error for debugging, but show a generic message to the user
    error_log("Profile update failed: " . $e->getMessage());
    redirectWithError('Failed to update profile. Please try again. ' . $e->getMessage()); // In production, avoid showing raw $e->getMessage()
}
?>