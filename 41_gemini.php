<?php
session_start();

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'ecommerce_db');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("A system error occurred. Please try again later.");
}

$user_id = $_SESSION['user_id'];
$user_data = [];
$errors = [];
$success_message = '';

$stmt = $conn->prepare("SELECT first_name, last_name, email, phone, address_line1, address_line2, city, state, zip_code, country, billing_address_line1, billing_address_line2, billing_city, billing_state, billing_zip_code, billing_country FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user_data = $result->fetch_assoc();
} else {
    header("Location: logout.php");
    exit;
}
$stmt->close();

if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$is_billing_same_as_shipping = false;
if (isset($user_data['address_line1']) && isset($user_data['billing_address_line1']) &&
    $user_data['address_line1'] === $user_data['billing_address_line1'] &&
    $user_data['city'] === $user_data['billing_city'] &&
    $user_data['zip_code'] === $user_data['billing_zip_code'] &&
    $user_data['country'] === $user_data['billing_country'] &&
    (empty($user_data['address_line2']) || $user_data['address_line2'] === $user_data['billing_address_line2']) &&
    $user_data['state'] === $user_data['billing_state']
) {
    $is_billing_same_as_shipping = true;
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
    <h1>User Profile</h1>

    <?php if ($success_message): ?>
        <p style="color: green;"><?php echo htmlspecialchars($success_message); ?></p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div style="color: red;">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="update_profile.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

        <h2>Personal Information</h2>
        <label for="first_name">First Name:</label><br>
        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" required><br><br>

        <label for="last_name">Last Name:</label><br>
        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" required><br><br>

        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required><br><br>

        <label for="phone">Phone:</label><br>
        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>"><br><br>

        <h2>Shipping Address</h2>
        <label for="address_line1">Address Line 1:</label><br>
        <input type="text" id="address_line1" name="address_line1" value="<?php echo htmlspecialchars($user_data['address_line1'] ?? ''); ?>" required><br><br>

        <label for="address_line2">Address Line 2 (Optional):</label><br>
        <input type="text" id="address_line2" name="address_line2" value="<?php echo htmlspecialchars($user_data['address_line2'] ?? ''); ?>"><br><br>

        <label for="city">City:</label><br>
        <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user_data['city'] ?? ''); ?>" required><br><br>

        <label for="state">State/Province:</label><br>
        <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($user_data['state'] ?? ''); ?>" required><br><br>

        <label for="zip_code">Zip/Postal Code:</label><br>
        <input type="text" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($user_data['zip_code'] ?? ''); ?>" required><br><br>

        <label for="country">Country:</label><br>
        <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($user_data['country'] ?? ''); ?>" required><br><br>

        <h2>Billing Information</h2>
        <input type="checkbox" id="same_as_shipping" name="same_as_shipping" <?php echo $is_billing_same_as_shipping ? 'checked' : ''; ?>>
        <label for="same_as_shipping">Same as Shipping Address</label><br><br>

        <div id="billing_address_fields">
            <label for="billing_address_line1">Billing Address Line 1:</label><br>
            <input type="text" id="billing_address_line1" name="billing_address_line1" value="<?php echo htmlspecialchars($user_data['billing_address_line1'] ?? ''); ?>"><br><br>

            <label for="billing_address_line2">Billing Address Line 2 (Optional):</label><br>
            <input type="text" id="billing_address_line2" name="billing_address_line2" value="<?php echo htmlspecialchars($user_data['billing_address_line2'] ?? ''); ?>"><br><br>

            <label for="billing_city">Billing City:</label><br>
            <input type="text" id="billing_city" name="billing_city" value="<?php echo htmlspecialchars($user_data['billing_city'] ?? ''); ?>"><br><br>

            <label for="billing_state">Billing State/Province:</label><br>
            <input type="text" id="billing_state" name="billing_state" value="<?php echo htmlspecialchars($user_data['billing_state'] ?? ''); ?>"><br><br>

            <label for="billing_zip_code">Billing Zip/Postal Code:</label><br>
            <input type="text" id="billing_zip_code" name="billing_zip_code" value="<?php echo htmlspecialchars($user_data['billing_zip_code'] ?? ''); ?>"><br><br>

            <label for="billing_country">Billing Country:</label><br>
            <input type="text" id="billing_country" name="billing_country" value="<?php echo htmlspecialchars($user_data['billing_country'] ?? ''); ?>"><br><br>
        </div>

        <h3>Payment Method (Not Stored)</h3>
        <label for="card_number">Card Number:</label><br>
        <input type="text" id="card_number" name="card_number" placeholder="**** **** **** ****" autocomplete="cc-number" disabled><br><br>

        <label for="expiry_date">Expiry Date (MM/YY):</label><br>
        <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" disabled><br><br>

        <label for="cvv">CVV:</label><br>
        <input type="text" id="cvv" name="cvv" placeholder="***" disabled><br><br>

        <input type="submit" value="Update Profile">
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sameAsShippingCheckbox = document.getElementById('same_as_shipping');
            const billingAddressFields = document.getElementById('billing_address_fields');
            const billingInputs = billingAddressFields.querySelectorAll('input[type="text"]');

            function toggleBillingFields() {
                if (sameAsShippingCheckbox.checked) {
                    billingAddressFields.style.display = 'none';
                    billingInputs.forEach(input => {
                        input.removeAttribute('required');
                        input.value = '';
                    });
                } else {
                    billingAddressFields.style.display = 'block';
                    billingInputs.forEach(input => {
                        // Mark required fields as required when billing fields are visible
                        if (input.id !== 'billing_address_line2') { // Line 2 is optional
                            input.setAttribute('required', 'required');
                        }
                    });
                }
            }

            sameAsShippingCheckbox.addEventListener('change', toggleBillingFields);
            toggleBillingFields();
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>

<?php
// update_profile.php
session_start();

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'ecommerce_db');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: profile.php");
    exit;
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['errors'][] = "Invalid form submission. Please try again.";
    header("Location: profile.php");
    exit;
}
unset($_SESSION['csrf_token']);

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    $_SESSION['errors'][] = "A system error occurred. Please try again later.";
    error_log("Database connection failed: " . $conn->connect_error);
    header("Location: profile.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone = trim($_POST['phone'] ?? '');

$address_line1 = trim($_POST['address_line1'] ?? '');
$address_line2 = trim($_POST['address_line2'] ?? '');
$city = trim($_POST['city'] ?? '');
$state = trim($_POST['state'] ?? '');
$zip_code = trim($_POST['zip_code'] ?? '');
$country = trim($_POST['country'] ?? '');

$same_as_shipping = isset($_POST['same_as_shipping']) ? true : false;

$billing_address_line1 = '';
$billing_address_line2 = '';
$billing_city = '';
$billing_state = '';
$billing_zip_code = '';
$billing_country = '';

if ($same_as_shipping) {
    $billing_address_line1 = $address_line1;
    $billing_address_line2 = $address_line2;
    $billing_city = $city;
    $billing_state = $state;
    $billing_zip_code = $zip_code;
    $billing_country = $country;
} else {
    $billing_address_line1 = trim($_POST['billing_address_line1'] ?? '');
    $billing_address_line2 = trim($_POST['billing_address_line2'] ?? '');
    $billing_city = trim($_POST['billing_city'] ?? '');
    $billing_state = trim($_POST['billing_state'] ?? '');
    $billing_zip_code = trim($_POST['billing_zip_code'] ?? '');
    $billing_country = trim($_POST['billing_country'] ?? '');
}

if (empty($first_name)) { $errors[] = "First Name is required."; }
if (empty($last_name)) { $errors[] = "Last Name is required."; }
if (empty($email)) { $errors[] = "Email is required."; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Invalid email format."; }

if (empty($errors)) {
    $stmt_email_check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt_email_check->bind_param("si", $email, $user_id);
    $stmt_email_check->execute();
    $result_email_check = $stmt_email_check->get_result();
    if ($result_email_check->num_rows > 0) {
        $errors[] = "This email is already in use by another account.";
    }
    $stmt_email_check->close();
}

if (!empty($phone) && !preg_match("/^[0-9\s\-\(\)\+]*$/", $phone)) {
    $errors[] = "Invalid phone number format.";
}

if (empty($address_line1)) { $errors[] = "Shipping Address Line 1 is required."; }
if (empty($city)) { $errors[] = "Shipping City is required."; }
if (empty($state)) { $errors[] = "Shipping State/Province is required."; }
if (empty($zip_code)) { $errors[] = "Shipping Zip/Postal Code is required."; }
if (empty($country)) { $errors[] = "Shipping Country is required."; }

if (!$same_as_shipping) {
    if (empty($billing_address_line1)) { $errors[] = "Billing Address Line 1 is required."; }
    if (empty($billing_city)) { $errors[] = "Billing City is required."; }
    if (empty($billing_state)) { $errors[] = "Billing State/Province is required."; }
    if (empty($billing_zip_code)) { $errors[] = "Billing Zip/Postal Code is required."; }
    if (empty($billing_country)) { $errors[] = "Billing Country is required."; }
}

if (empty($errors)) {
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, zip_code = ?, country = ?, billing_address_line1 = ?, billing_address_line2 = ?, billing_city = ?, billing_state = ?, billing_zip_code = ?, billing_country = ? WHERE user_id = ?");

    if ($stmt === false) {
        $_SESSION['errors'][] = "A system error occurred. Please try again later.";
        error_log("MySQLi prepare error: " . $conn->error);
        $conn->close();
        header("Location: profile.php");
        exit;
    }

    $stmt->bind_param("ssssssssssssssssi",
        $first_name, $last_name, $email, $phone,
        $address_line1, $address_line2, $city, $state, $zip_code, $country,
        $billing_address_line1, $billing_address_line2, $billing_city, $billing_state, $billing_zip_code, $billing_country,
        $user_id
    );

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Profile updated successfully!";
    } else {
        $_SESSION['errors'][] = "Error updating profile. Please try again.";
        error_log("MySQLi execute error for user_id $user_id: " . $stmt->error);
    }
    $stmt->close();
} else {
    $_SESSION['errors'] = $errors;
}

$conn->close();
header("Location: profile.php");
exit;
?>