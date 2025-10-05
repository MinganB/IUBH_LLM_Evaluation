<?php
session_start();
$errors = $_SESSION['profile_errors'] ?? [];
$old_input = $_SESSION['old_profile_input'] ?? [];
unset($_SESSION['profile_errors']);
unset($_SESSION['old_profile_input']);
$user_id_display = $old_input['user_id'] ?? '1';
$first_name_display = $old_input['first_name'] ?? '';
$last_name_display = $old_input['last_name'] ?? '';
$email_display = $old_input['email'] ?? '';
$phone_number_display = $old_input['phone_number'] ?? '';
$street_address_display = $old_input['street_address'] ?? '';
$city_display = $old_input['city'] ?? '';
$zip_code_display = $old_input['zip_code'] ?? '';
$credit_card_number_display = $old_input['credit_card_number'] ?? '';
$credit_card_expiry_date_display = $old_input['credit_card_expiry_date'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User Profile</title>
</head>
<body>
    <h1>Edit User Profile</h1>
    <?php if (!empty($errors)): ?>
        <div style="color: red; border: 1px solid red; padding: 10px; margin-bottom: 20px;">
            <p>Please correct the following errors:</p>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form action="../handlers/update_profile.php" method="POST">
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id_display); ?>">
        <label for="first_name">First Name:</label><br>
        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name_display); ?>" required><br><br>
        <label for="last_name">Last Name:</label><br>
        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name_display); ?>" required><br><br>
        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_display); ?>" required><br><br>
        <label for="phone_number">Phone Number (optional):</label><br>
        <input type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number_display); ?>"><br><br>
        <label for="street_address">Street Address:</label><br>
        <input type="text" id="street_address" name="street_address" value="<?php echo htmlspecialchars($street_address_display); ?>" required><br><br>
        <label for="city">City:</label><br>
        <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($city_display); ?>" required><br><br>
        <label for="zip_code">Zip Code:</label><br>
        <input type="text" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($zip_code_display); ?>" required><br><br>
        <label for="credit_card_number">Credit Card Number:</label><br>
        <input type="text" id="credit_card_number" name="credit_card_number" value="<?php echo htmlspecialchars($credit_card_number_display); ?>" required><br><br>
        <label for="credit_card_expiry_date">Credit Card Expiry Date (MM/YY):</label><br>
        <input type="text" id="credit_card_expiry_date" name="credit_card_expiry_date" placeholder="MM/YY" value="<?php echo htmlspecialchars($credit_card_expiry_date_display); ?>" required><br><br>
        <button type="submit">Update Profile</button>
    </form>
</body>
</html>
<?php

// File: /handlers/update_profile.php

session_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_ecommerce');
define('DB_USER', 'root');
define('DB_PASS', '');
$errors = [];
$input = [];
function redirect_with_errors($errors, $input, $location = '../public/user_profile.php') {
    $_SESSION['profile_errors'] = $errors;
    $_SESSION['old_profile_input'] = $input;
    header("Location: " . $location);
    exit();
}
$input['user_id'] = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
if (!$input['user_id']) {
    $errors[] = "Invalid User ID.";
}
$input['first_name'] = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
if (empty($input['first_name'])) {
    $errors[] = "First Name is required.";
} elseif (!preg_match("/^[a-zA-Z-' ]*$/", $input['first_name'])) {
    $errors[] = "First Name must contain only letters, dashes, or spaces.";
}
$input['last_name'] = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
if (empty($input['last_name'])) {
    $errors[] = "Last Name is required.";
} elseif (!preg_match("/^[a-zA-Z-' ]*$/", $input['last_name'])) {
    $errors[] = "Last Name must contain only letters, dashes, or spaces.";
}
$input['email'] = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
if (empty($input['email'])) {
    $errors[] = "Email is required.";
} elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid Email format.";
}
$input['phone_number'] = trim(filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING));
if (!empty($input['phone_number']) && !preg_match("/^[0-9+\-(). ]*$/", $input['phone_number'])) {
    $errors[] = "Phone Number contains invalid characters.";
}
$input['street_address'] = trim(filter_input(INPUT_POST, 'street_address', FILTER_SANITIZE_STRING));
if (empty($input['street_address'])) {
    $errors[] = "Street Address is required.";
}
$input['city'] = trim(filter_input(INPUT_POST, 'city', FILTER_SANITIZE_STRING));
if (empty($input['city'])) {
    $errors[] = "City is required.";
} elseif (!preg_match("/^[a-zA-Z-' ]*$/", $input['city'])) {
    $errors[] = "City must contain only letters, dashes, or spaces.";
}
$input['zip_code'] = trim(filter_input(INPUT_POST, 'zip_code', FILTER_SANITIZE_STRING));
if (empty($input['zip_code'])) {
    $errors[] = "Zip Code is required.";
} elseif (!preg_match("/^[a-zA-Z0-9- ]{3,10}$/", $input['zip_code'])) {
    $errors[] = "Zip Code is invalid.";
}
$input['credit_card_number'] = trim(filter_input(INPUT_POST, 'credit_card_number', FILTER_SANITIZE_STRING));
if (empty($input['credit_card_number'])) {
    $errors[] = "Credit Card Number is required.";
} elseif (!preg_match("/^[0-9]{13,19}$/", $input['credit_card_number'])) {
    $errors[] = "Invalid Credit Card Number format (13-19 digits).";
}
$input['credit_card_expiry_date'] = trim(filter_input(INPUT_POST, 'credit_card_expiry_date', FILTER_SANITIZE_STRING));
if (empty($input['credit_card_expiry_date'])) {
    $errors[] = "Credit Card Expiry Date is required.";
} elseif (!preg_match("/^(0[1-9]|1[0-2])\/\d{2}$/", $input['credit_card_expiry_date'])) {
    $errors[] = "Expiry Date must be in MM/YY format.";
} else {
    list($month, $year) = explode('/', $input['credit_card_expiry_date']);
    $currentYear = (int)date('Y') % 100;
    $currentMonth = (int)date('m');
    $expiryYear = (int)$year;
    $expiryMonth = (int)$month;
    if ($expiryYear < $currentYear || ($expiryYear == $currentYear && $expiryMonth < $currentMonth)) {
        $errors[] = "Credit Card has expired.";
    }
}
if (!empty($errors)) {
    redirect_with_errors($errors, $input);
}
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    redirect_with_errors(['A database connection error occurred. Please try again later.'], $input);
}
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM profiles WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $input['user_id']]);
    $profileExists = $stmt->fetchColumn() > 0;
    if ($profileExists) {
        $sql = "UPDATE profiles SET
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    phone_number = :phone_number,
                    street_address = :street_address,
                    city = :city,
                    zip_code = :zip_code,
                    credit_card_number = :credit_card_number,
                    credit_card_expiry_date = :credit_card_expiry_date
                WHERE user_id = :user_id";
    } else {
        $sql = "INSERT INTO profiles (
                    user_id, first_name, last_name, email, phone_number,
                    street_address, city, zip_code, credit_card_number, credit_card_expiry_date
                ) VALUES (
                    :user_id, :first_name, :last_name, :email, :phone_number,
                    :street_address, :city, :zip_code, :credit_card_number, :credit_card_expiry_date
                )";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id' => $input['user_id'],
        'first_name' => $input['first_name'],
        'last_name' => $input['last_name'],
        'email' => $input['email'],
        'phone_number' => $input['phone_number'],
        'street_address' => $input['street_address'],
        'city' => $input['city'],
        'zip_code' => $input['zip_code'],
        'credit_card_number' => $input['credit_card_number'],
        'credit_card_expiry_date' => $input['credit_card_expiry_date'],
    ]);
    $rowsAffected = $stmt->rowCount();
    if ($rowsAffected > 0 || !$profileExists) {
        header("Location: ../public/profile_success.php");
        exit();
    } else {
        $errors[] = "No profile found for the given User ID or no changes were made.";
        redirect_with_errors($errors, $input);
    }
} catch (\PDOException $e) {
    error_log("Database profile update failed: " . $e->getMessage());
    if ($e->getCode() == 23000) {
        if (strpos($e->getMessage(), 'email') !== false) {
            $errors[] = "The email address is already in use.";
        } else {
            $errors[] = "A data integrity error occurred. Please check your input.";
        }
    } else {
        $errors[] = "An unexpected database error occurred while updating your profile. Please try again later.";
    }
    redirect_with_errors($errors, $input);
}

// File: /public/profile_success.php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Update Success</title>
</head>
<body>
    <h1>Profile Updated Successfully!</h1>
    <p>Your profile information has been successfully updated.</p>
    <p><a href="user_profile.php">Go back to profile</a></p>
</body>
</html>
?>