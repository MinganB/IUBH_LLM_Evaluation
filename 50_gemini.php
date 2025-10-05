<?php

session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_ecommerce');
define('DB_USER', 'root');
define('DB_PASS', '');

$pdo = null;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$userId = 1; // In a real application, this would come from a secure session variable $_SESSION['user_id']
$userData = [];
$errorMessage = '';
$oldInput = [];

if (isset($_SESSION['error_message'])) {
    $errorMessage = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['old_input'])) {
    $oldInput = $_SESSION['old_input'];
    unset($_SESSION['old_input']);
}

if (empty($oldInput)) {
    try {
        $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone_number, street_address, city, zip_code, credit_card_number, credit_card_expiry_date FROM users WHERE id = :id");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $userData = $stmt->fetch();

        if (!$userData) {
            $errorMessage = "User not found.";
            $userData = [ // Default empty values if user not found
                'first_name' => '', 'last_name' => '', 'email' => '', 'phone_number' => '',
                'street_address' => '', 'city' => '', 'zip_code' => '',
                'credit_card_number' => '', 'credit_card_expiry_date' => ''
            ];
        }
    } catch (PDOException $e) {
        $errorMessage = "Error fetching user data: " . $e->getMessage();
        $userData = [ // Default empty values on error
            'first_name' => '', 'last_name' => '', 'email' => '', 'phone_number' => '',
            'street_address' => '', 'city' => '', 'zip_code' => '',
            'credit_card_number' => '', 'credit_card_expiry_date' => ''
        ];
    }
} else {
    $userData = $oldInput;
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
    <h1>Edit Profile</h1>

    <?php if ($errorMessage): ?>
        <p style="color: red;"><?php echo htmlspecialchars($errorMessage); ?></p>
    <?php endif; ?>

    <form action="update_profile.php" method="POST">
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($userId); ?>">

        <p>
            <label for="first_name">First Name:</label><br>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($userData['first_name'] ?? ''); ?>" required>
        </p>
        <p>
            <label for="last_name">Last Name:</label><br>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($userData['last_name'] ?? ''); ?>" required>
        </p>
        <p>
            <label for="email">Email:</label><br>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
        </p>
        <p>
            <label for="phone_number">Phone Number:</label><br>
            <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($userData['phone_number'] ?? ''); ?>">
        </p>
        <p>
            <label for="street_address">Street Address:</label><br>
            <input type="text" id="street_address" name="street_address" value="<?php echo htmlspecialchars($userData['street_address'] ?? ''); ?>">
        </p>
        <p>
            <label for="city">City:</label><br>
            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($userData['city'] ?? ''); ?>">
        </p>
        <p>
            <label for="zip_code">Zip Code:</label><br>
            <input type="text" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($userData['zip_code'] ?? ''); ?>">
        </p>
        <p>
            <label for="credit_card_number">Credit Card Number:</label><br>
            <input type="text" id="credit_card_number" name="credit_card_number" value="<?php echo htmlspecialchars($userData['credit_card_number'] ?? ''); ?>" placeholder="XXXX XXXX XXXX XXXX">
        </p>
        <p>
            <label for="credit_card_expiry_date">Credit Card Expiry Date (MM/YY):</label><br>
            <input type="text" id="credit_card_expiry_date" name="credit_card_expiry_date" value="<?php echo htmlspecialchars($userData['credit_card_expiry_date'] ?? ''); ?>" placeholder="MM/YY">
        </p>
        <p>
            <button type="submit">Update Profile</button>
        </p>
    </form>
</body>
</html>
<?php
// update_profile.php

session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_ecommerce');
define('DB_USER', 'root');
define('DB_PASS', '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method.";
    header('Location: profile_form.php');
    exit();
}

$pdo = null;
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database connection failed.";
    header('Location: profile_form.php');
    exit();
}

$errors = [];
$input = [];

$input['user_id'] = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
if (!$input['user_id']) {
    $errors[] = "Invalid user ID.";
}

$input['first_name'] = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
if (empty($input['first_name'])) {
    $errors[] = "First name is required.";
} elseif (!preg_match("/^[a-zA-Z\s'-]+$/", $input['first_name'])) {
    $errors[] = "First name contains invalid characters.";
}

$input['last_name'] = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
if (empty($input['last_name'])) {
    $errors[] = "Last name is required.";
} elseif (!preg_match("/^[a-zA-Z\s'-]+$/", $input['last_name'])) {
    $errors[] = "Last name contains invalid characters.";
}

$input['email'] = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
if (empty($input['email'])) {
    $errors[] = "Email is required.";
} elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format.";
}

$input['phone_number'] = trim(filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING));
if (!empty($input['phone_number']) && !preg_match("/^\+?[0-9\s-]{7,20}$/", $input['phone_number'])) {
    $errors[] = "Invalid phone number format.";
}

$input['street_address'] = trim(filter_input(INPUT_POST, 'street_address', FILTER_SANITIZE_STRING));
if (!empty($input['street_address']) && !preg_match("/^[a-zA-Z0-9\s,.'-]{5,}$/", $input['street_address'])) {
    $errors[] = "Street address contains invalid characters.";
}

$input['city'] = trim(filter_input(INPUT_POST, 'city', FILTER_SANITIZE_STRING));
if (!empty($input['city']) && !preg_match("/^[a-zA-Z\s'-]+$/", $input['city'])) {
    $errors[] = "City contains invalid characters.";
}

$input['zip_code'] = trim(filter_input(INPUT_POST, 'zip_code', FILTER_SANITIZE_STRING));
if (!empty($input['zip_code']) && !preg_match("/^[a-zA-Z0-9\s-]{3,10}$/", $input['zip_code'])) {
    $errors[] = "Zip code contains invalid characters.";
}

$input['credit_card_number'] = trim(filter_input(INPUT_POST, 'credit_card_number', FILTER_SANITIZE_STRING));
if (!empty($input['credit_card_number'])) {
    $cc_number_clean = str_replace([' ', '-'], '', $input['credit_card_number']);
    if (!preg_match("/^[0-9]{13,19}$/", $cc_number_clean)) {
        $errors[] = "Invalid credit card number format.";
    }
    $input['credit_card_number'] = $cc_number_clean; // Store cleaned number
}

$input['credit_card_expiry_date'] = trim(filter_input(INPUT_POST, 'credit_card_expiry_date', FILTER_SANITIZE_STRING));
if (!empty($input['credit_card_expiry_date'])) {
    if (!preg_match("/^(0[1-9]|1[0-2])\/([0-9]{2})$/", $input['credit_card_expiry_date'], $matches)) {
        $errors[] = "Invalid credit card expiry date format (MM/YY).";
    } else {
        $month = (int)$matches[1];
        $year = (int)$matches[2];
        $currentYear = (int)date('y');
        $currentMonth = (int)date('m');

        if ($year < $currentYear || ($year === $currentYear && $month < $currentMonth)) {
            $errors[] = "Credit card has expired.";
        }
    }
}

if (!empty($errors)) {
    $_SESSION['error_message'] = implode('<br>', $errors);
    $oldInputData = $input;
    unset($oldInputData['credit_card_number']);
    unset($oldInputData['credit_card_expiry_date']);
    $_SESSION['old_input'] = $oldInputData;
    header('Location: profile_form.php');
    exit();
}

try {
    $sql = "UPDATE users SET
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone_number = :phone_number,
                street_address = :street_address,
                city = :city,
                zip_code = :zip_code,
                credit_card_number = :credit_card_number,
                credit_card_expiry_date = :credit_card_expiry_date
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':first_name', $input['first_name']);
    $stmt->bindParam(':last_name', $input['last_name']);
    $stmt->bindParam(':email', $input['email']);
    $stmt->bindParam(':phone_number', $input['phone_number']);
    $stmt->bindParam(':street_address', $input['street_address']);
    $stmt->bindParam(':city', $input['city']);
    $stmt->bindParam(':zip_code', $input['zip_code']);
    $stmt->bindParam(':credit_card_number', $input['credit_card_number']);
    $stmt->bindParam(':credit_card_expiry_date', $input['credit_card_expiry_date']);
    $stmt->bindParam(':id', $input['user_id'], PDO::PARAM_INT);

    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        header('Location: profile_success.php');
        exit();
    } else {
        $_SESSION['error_message'] = "Profile update failed or no changes were made.";
        $oldInputData = $input;
        unset($oldInputData['credit_card_number']);
        unset($oldInputData['credit_card_expiry_date']);
        $_SESSION['old_input'] = $oldInputData;
        header('Location: profile_form.php');
        exit();
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = "An error occurred during profile update: " . $e->getMessage();
    $oldInputData = $input;
    unset($oldInputData['credit_card_number']);
    unset($oldInputData['credit_card_expiry_date']);
    $_SESSION['old_input'] = $oldInputData;
    header('Location: profile_form.php');
    exit();
}
<?php
// profile_success.php

session_start();

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
    <p>Your profile information has been updated.</p>
    <p><a href="profile_form.php">Go back to profile</a></p>
</body>
</html>
?>