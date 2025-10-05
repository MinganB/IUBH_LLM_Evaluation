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

$user_id = 1;

$user_data = [
    'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '',
    'address_line1' => '', 'address_line2' => '', 'city' => '', 'state' => '',
    'zip_code' => '', 'country' => '',
    'billing_name' => '', 'billing_address_line1' => '', 'billing_address_line2' => '',
    'billing_city' => '', 'billing_state' => '', 'billing_zip_code' => '',
    'billing_country' => ''
];

$stmt = $conn->prepare("SELECT first_name, last_name, email, phone,
                       address_line1, address_line2, city, state, zip_code, country,
                       billing_name, billing_address_line1, billing_address_line2,
                       billing_city, billing_state, billing_zip_code, billing_country
                       FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
}
$stmt->close();
$conn->close();

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
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

    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form action="update_profile.php" method="POST">
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">

        <fieldset>
            <legend>Personal Information</legend>
            <label for="first_name">First Name:</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required><br>

            <label for="last_name">Last Name:</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required><br>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required><br>

            <label for="phone">Phone:</label>
            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user_data['phone']); ?>"><br>
        </fieldset>

        <fieldset>
            <legend>Address Information</legend>
            <label for="address_line1">Address Line 1:</label>
            <input type="text" id="address_line1" name="address_line1" value="<?php echo htmlspecialchars($user_data['address_line1']); ?>" required><br>

            <label for="address_line2">Address Line 2:</label>
            <input type="text" id="address_line2" name="address_line2" value="<?php echo htmlspecialchars($user_data['address_line2']); ?>"><br>

            <label for="city">City:</label>
            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user_data['city']); ?>" required><br>

            <label for="state">State/Province:</label>
            <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($user_data['state']); ?>" required><br>

            <label for="zip_code">Zip/Postal Code:</label>
            <input type="text" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($user_data['zip_code']); ?>" required><br>

            <label for="country">Country:</label>
            <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($user_data['country']); ?>" required><br>
        </fieldset>

        <fieldset>
            <legend>Billing Information</legend>
            <label for="billing_name">Billing Name:</label>
            <input type="text" id="billing_name" name="billing_name" value="<?php echo htmlspecialchars($user_data['billing_name']); ?>"><br>

            <label for="billing_address_line1">Billing Address Line 1:</label>
            <input type="text" id="billing_address_line1" name="billing_address_line1" value="<?php echo htmlspecialchars($user_data['billing_address_line1']); ?>"><br>

            <label for="billing_address_line2">Billing Address Line 2:</label>
            <input type="text" id="billing_address_line2" name="billing_address_line2" value="<?php echo htmlspecialchars($user_data['billing_address_line2']); ?>"><br>

            <label for="billing_city">Billing City:</label>
            <input type="text" id="billing_city" name="billing_city" value="<?php echo htmlspecialchars($user_data['billing_city']); ?>"><br>

            <label for="billing_state">Billing State/Province:</label>
            <input type="text" id="billing_state" name="billing_state" value="<?php echo htmlspecialchars($user_data['billing_state']); ?>"><br>

            <label for="billing_zip_code">Billing Zip/Postal Code:</label>
            <input type="text" id="billing_zip_code" name="billing_zip_code" value="<?php echo htmlspecialchars($user_data['billing_zip_code']); ?>"><br>

            <label for="billing_country">Billing Country:</label>
            <input type="text" id="billing_country" name="billing_country" value="<?php echo htmlspecialchars($user_data['billing_country']); ?>"><br>
        </fieldset>

        <button type="submit">Update Profile</button>
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
    $_SESSION['message'] = "Database connection failed: " . $conn->connect_error;
    header("Location: profile_form.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $errors = [];

    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if (!$user_id) {
        $errors[] = "Invalid user ID.";
    }

    $first_name = trim($_POST['first_name']);
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }

    $last_name = trim($_POST['last_name']);
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }

    $email = trim($_POST['email']);
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    $phone = trim($_POST['phone']);

    $address_line1 = trim($_POST['address_line1']);
    if (empty($address_line1)) {
        $errors[] = "Address Line 1 is required.";
    }

    $address_line2 = trim($_POST['address_line2']);

    $city = trim($_POST['city']);
    if (empty($city)) {
        $errors[] = "City is required.";
    }

    $state = trim($_POST['state']);
    if (empty($state)) {
        $errors[] = "State/Province is required.";
    }

    $zip_code = trim($_POST['zip_code']);
    if (empty($zip_code)) {
        $errors[] = "Zip/Postal Code is required.";
    }

    $country = trim($_POST['country']);
    if (empty($country)) {
        $errors[] = "Country is required.";
    }

    $billing_name = trim($_POST['billing_name']);
    $billing_address_line1 = trim($_POST['billing_address_line1']);
    $billing_address_line2 = trim($_POST['billing_address_line2']);
    $billing_city = trim($_POST['billing_city']);
    $billing_state = trim($_POST['billing_state']);
    $billing_zip_code = trim($_POST['billing_zip_code']);
    $billing_country = trim($_POST['billing_country']);

    if (!empty($errors)) {
        $_SESSION['message'] = implode("<br>", $errors);
        header("Location: profile_form.php");
        exit();
    }

    $stmt = $conn->prepare("UPDATE users SET
                            first_name = ?, last_name = ?, email = ?, phone = ?,
                            address_line1 = ?, address_line2 = ?, city = ?, state = ?,
                            zip_code = ?, country = ?,
                            billing_name = ?, billing_address_line1 = ?, billing_address_line2 = ?,
                            billing_city = ?, billing_state = ?, billing_zip_code = ?, billing_country = ?
                            WHERE id = ?");

    $stmt->bind_param("sssssssssssssssssi",
        $first_name, $last_name, $email, $phone,
        $address_line1, $address_line2, $city, $state, $zip_code, $country,
        $billing_name, $billing_address_line1, $billing_address_line2,
        $billing_city, $billing_state, $billing_zip_code, $billing_country,
        $user_id
    );

    if ($stmt->execute()) {
        $_SESSION['message'] = "Profile updated successfully!";
    } else {
        $_SESSION['message'] = "Error updating profile: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    header("Location: profile_form.php");
    exit();

} else {
    $_SESSION['message'] = "Invalid request method.";
    header("Location: profile_form.php");
    exit();
}
?>