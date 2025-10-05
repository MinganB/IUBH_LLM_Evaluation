<?php
session_start();

$user_id = 1; // This should come from an authenticated user's session in a real application

// Pre-fill form data if available (e.g., after an error)
$first_name = $_SESSION['form_data']['first_name'] ?? '';
$last_name = $_SESSION['form_data']['last_name'] ?? '';
$email = $_SESSION['form_data']['email'] ?? '';
$phone_number = $_SESSION['form_data']['phone_number'] ?? '';
$street_address = $_SESSION['form_data']['street_address'] ?? '';
$city = $_SESSION['form_data']['city'] ?? '';
$zip_code = $_SESSION['form_data']['zip_code'] ?? '';
$credit_card_number = $_SESSION['form_data']['credit_card_number'] ?? '';
$credit_card_expiry_date = $_SESSION['form_data']['credit_card_expiry_date'] ?? '';

unset($_SESSION['form_data']);

if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
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

    <?php if (isset($success_message)): ?>
        <p><?php echo htmlspecialchars($success_message); ?></p>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <form action="update_profile.php" method="POST">
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">

        <label for="first_name">First Name:</label><br>
        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required><br><br>

        <label for="last_name">Last Name:</label><br>
        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required><br><br>

        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required><br><br>

        <label for="phone_number">Phone Number:</label><br>
        <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>" placeholder="e.g., 123-456-7890" required><br><br>

        <label for="street_address">Street Address:</label><br>
        <input type="text" id="street_address" name="street_address" value="<?php echo htmlspecialchars($street_address); ?>" required><br><br>

        <label for="city">City:</label><br>
        <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($city); ?>" required><br><br>

        <label for="zip_code">Zip Code:</label><br>
        <input type="text" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($zip_code); ?>" placeholder="e.g., 12345 or 12345-6789" required><br><br>

        <label for="credit_card_number">Credit Card Number:</label><br>
        <input type="text" id="credit_card_number" name="credit_card_number" value="<?php echo htmlspecialchars($credit_card_number); ?>" placeholder="e.g., XXXX-XXXX-XXXX-XXXX" required><br><br>

        <label for="credit_card_expiry_date">Credit Card Expiry Date (MM/YY):</label><br>
        <input type="text" id="credit_card_expiry_date" name="credit_card_expiry_date" value="<?php echo htmlspecialchars($credit_card_expiry_date); ?>" placeholder="MM/YY" required><br><br>

        <input type="submit" value="Update Profile">
    </form>
</body>
</html>
<?php
session_start();

$_SESSION['form_data'] = $_POST;

$db_host = 'localhost';
$db_name = 'db_ecommerce';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database connection failed: ' . $e->getMessage();
    header('Location: profile_form.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if (!$user_id || $user_id <= 0) {
        $errors[] = 'Invalid user ID.';
    }

    $first_name = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    if (empty($first_name)) {
        $errors[] = 'First Name is required.';
    }

    $last_name = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    if (empty($last_name)) {
        $errors[] = 'Last Name is required.';
    }

    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }

    $phone_number = trim(filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    if (!preg_match('/^\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}$/', $phone_number)) {
        $errors[] = 'Invalid phone number format.';
    }

    $street_address = trim(filter_input(INPUT_POST, 'street_address', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    if (empty($street_address)) {
        $errors[] = 'Street Address is required.';
    }

    $city = trim(filter_input(INPUT_POST, 'city', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    if (empty($city)) {
        $errors[] = 'City is required.';
    }

    $zip_code = trim(filter_input(INPUT_POST, 'zip_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    if (!preg_match('/^\d{5}(?:[-\s]\d{4})?$/', $zip_code)) {
        $errors[] = 'Invalid zip code format.';
    }

    $credit_card_number = str_replace([' ', '-'], '', trim(filter_input(INPUT_POST, 'credit_card_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS)));
    if (!preg_match('/^\d{13,19}$/', $credit_card_number)) {
        $errors[] = 'Invalid credit card number.';
    }

    $credit_card_expiry_date = trim(filter_input(INPUT_POST, 'credit_card_expiry_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    if (!preg_match('/^(0[1-9]|1[0-2])\/?([0-9]{2})$/', $credit_card_expiry_date, $matches)) {
        $errors[] = 'Invalid credit card expiry date format (MM/YY).';
    } else {
        $month = (int)$matches[1];
        $year = (int)$matches[2];
        $current_year = (int)date('y');
        $current_month = (int)date('m');

        if ($year < $current_year || ($year === $current_year && $month < $current_month)) {
            $errors[] = 'Credit card has expired.';
        }
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        header('Location: profile_form.php');
        exit();
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE users SET
            first_name = :first_name,
            last_name = :last_name,
            email = :email,
            phone_number = :phone_number,
            street_address = :street_address,
            city = :city,
            zip_code = :zip_code,
            credit_card_number = :credit_card_number,
            credit_card_expiry_date = :credit_card_expiry_date
            WHERE user_id = :user_id"
        );

        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':street_address', $street_address);
        $stmt->bindParam(':city', $city);
        $stmt->bindParam(':zip_code', $zip_code);
        $stmt->bindParam(':credit_card_number', $credit_card_number);
        $stmt->bindParam(':credit_card_expiry_date', $credit_card_expiry_date);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            unset($_SESSION['form_data']); // Clear form data on success
            $_SESSION['success'] = 'Profile updated successfully!';
            header('Location: profile_success.php');
            exit();
        } else {
            $_SESSION['error'] = 'Profile update failed. No changes detected or user not found.';
            header('Location: profile_form.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        header('Location: profile_form.php');
        exit();
    }
} else {
    header('Location: profile_form.php');
    exit();
}
<?php
session_start();

if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
} else {
    // If user somehow lands here without a success message, redirect to profile form
    header('Location: profile_form.php');
    exit();
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
    <p><?php echo htmlspecialchars($success_message); ?></p>
    <p><a href="profile_form.php">Go back to profile</a></p>
</body>
</html>
?>