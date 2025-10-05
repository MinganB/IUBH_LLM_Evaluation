<?php
session_start();

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'your_db_username');
define('DB_PASSWORD', 'your_db_password');
define('DB_NAME', 'your_db_name');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_data = [];
$error_message = '';
$success_message = '';

$stmt = $conn->prepare("SELECT first_name, last_name, email, phone, address_line1, address_line2, city, state_province, postal_code, country, billing_address_line1, billing_address_line2, billing_city, billing_state_province, billing_postal_code, billing_country FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user_data = $result->fetch_assoc();
} else {
    $error_message = "User data not found.";
}
$stmt->close();

if (isset($_SESSION['message'])) {
    if ($_SESSION['message_type'] === 'success') {
        $success_message = $_SESSION['message'];
    } else {
        $error_message = $_SESSION['message'];
    }
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

$conn->close();
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

    <?php if (!empty($success_message)): ?>
        <p style="color: green;"><?php echo htmlspecialchars($success_message); ?></p>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <form action="update_profile.php" method="POST">
        <h2>Personal Information</h2>
        <p>
            <label for="first_name">First Name:</label><br>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" required>
        </p>
        <p>
            <label for="last_name">Last Name:</label><br>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" required>
        </p>
        <p>
            <label for="email">Email:</label><br>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
        </p>
        <p>
            <label for="phone">Phone:</label><br>
            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
        </p>

        <h2>Shipping Address</h2>
        <p>
            <label for="address_line1">Address Line 1:</label><br>
            <input type="text" id="address_line1" name="address_line1" value="<?php echo htmlspecialchars($user_data['address_line1'] ?? ''); ?>" required>
        </p>
        <p>
            <label for="address_line2">Address Line 2 (Optional):</label><br>
            <input type="text" id="address_line2" name="address_line2" value="<?php echo htmlspecialchars($user_data['address_line2'] ?? ''); ?>">
        </p>
        <p>
            <label for="city">City:</label><br>
            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user_data['city'] ?? ''); ?>" required>
        </p>
        <p>
            <label for="state_province">State/Province:</label><br>
            <input type="text" id="state_province" name="state_province" value="<?php echo htmlspecialchars($user_data['state_province'] ?? ''); ?>" required>
        </p>
        <p>
            <label for="postal_code">Postal Code:</label><br>
            <input type="text" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($user_data['postal_code'] ?? ''); ?>" required>
        </p>
        <p>
            <label for="country">Country:</label><br>
            <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($user_data['country'] ?? ''); ?>" required>
        </p>

        <h2>Billing Information (Optional - if different from Shipping)</h2>
        <p>
            <label for="billing_address_line1">Billing Address Line 1:</label><br>
            <input type="text" id="billing_address_line1" name="billing_address_line1" value="<?php echo htmlspecialchars($user_data['billing_address_line1'] ?? ''); ?>">
        </p>
        <p>
            <label for="billing_address_line2">Billing Address Line 2 (Optional):</label><br>
            <input type="text" id="billing_address_line2" name="billing_address_line2" value="<?php echo htmlspecialchars($user_data['billing_address_line2'] ?? ''); ?>">
        </p>
        <p>
            <label for="billing_city">Billing City:</label><br>
            <input type="text" id="billing_city" name="billing_city" value="<?php echo htmlspecialchars($user_data['billing_city'] ?? ''); ?>">
        </p>
        <p>
            <label for="billing_state_province">Billing State/Province:</label><br>
            <input type="text" id="billing_state_province" name="billing_state_province" value="<?php echo htmlspecialchars($user_data['billing_state_province'] ?? ''); ?>">
        </p>
        <p>
            <label for="billing_postal_code">Billing Postal Code:</label><br>
            <input type="text" id="billing_postal_code" name="billing_postal_code" value="<?php echo htmlspecialchars($user_data['billing_postal_code'] ?? ''); ?>">
        </p>
        <p>
            <label for="billing_country">Billing Country:</label><br>
            <input type="text" id="billing_country" name="billing_country" value="<?php echo htmlspecialchars($user_data['billing_country'] ?? ''); ?>">
        </p>

        <p>
            <button type="submit">Update Profile</button>
        </p>
    </form>
</body>
</html>
<?php
session_start();

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'your_db_username');
define('DB_PASSWORD', 'your_db_password');
define('DB_NAME', 'your_db_name');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "You must be logged in to update your profile.";
    $_SESSION['message_type'] = "error";
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $address_line1 = filter_input(INPUT_POST, 'address_line1', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $address_line2 = filter_input(INPUT_POST, 'address_line2', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $state_province = filter_input(INPUT_POST, 'state_province', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $postal_code = filter_input(INPUT_POST, 'postal_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $country = filter_input(INPUT_POST, 'country', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $billing_address_line1 = filter_input(INPUT_POST, 'billing_address_line1', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $billing_address_line2 = filter_input(INPUT_POST, 'billing_address_line2', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $billing_city = filter_input(INPUT_POST, 'billing_city', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $billing_state_province = filter_input(INPUT_POST, 'billing_state_province', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $billing_postal_code = filter_input(INPUT_POST, 'billing_postal_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $billing_country = filter_input(INPUT_POST, 'billing_country', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (empty($first_name)) $errors[] = "First name is required.";
    if (empty($last_name)) $errors[] = "Last name is required.";
    if (empty($email)) $errors[] = "Email is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (empty($address_line1)) $errors[] = "Address Line 1 is required.";
    if (empty($city)) $errors[] = "City is required.";
    if (empty($state_province)) $errors[] = "State/Province is required.";
    if (empty($postal_code)) $errors[] = "Postal Code is required.";
    if (empty($country)) $errors[] = "Country is required.";

    if (empty($errors)) {
        $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt_check_email->bind_param("si", $email, $user_id);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();
        if ($stmt_check_email->num_rows > 0) {
            $errors[] = "This email is already registered to another account.";
        }
        $stmt_check_email->close();
    }

    if (empty($errors)) {
        $sql = "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, address_line1=?, address_line2=?, city=?, state_province=?, postal_code=?, country=?, billing_address_line1=?, billing_address_line2=?, billing_city=?, billing_state_province=?, billing_postal_code=?, billing_country=? WHERE id = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param(
                "ssssssssssssssssi",
                $first_name, $last_name, $email, $phone,
                $address_line1, $address_line2, $city, $state_province, $postal_code, $country,
                $billing_address_line1, $billing_address_line2, $billing_city, $billing_state_province, $billing_postal_code, $billing_country,
                $user_id
            );

            if ($stmt->execute()) {
                $_SESSION['message'] = "Profile updated successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error updating profile: " . $stmt->error;
                $_SESSION['message_type'] = "error";
            }
            $stmt->close();
        } else {
            $_SESSION['message'] = "Database error preparing statement.";
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = implode("<br>", $errors);
        $_SESSION['message_type'] = "error";
    }
} else {
    $_SESSION['message'] = "Invalid request method.";
    $_SESSION['message_type'] = "error";
}

$conn->close();
header("Location: profile.php");
exit;
?>