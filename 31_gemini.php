<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_db');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed.");
}

if (!isset($_SESSION['user_id'])) {
    // In a production environment, you would redirect to a login page:
    // header('Location: /login.php');
    // exit();
    // For demonstration, we'll set a dummy user ID.
    $_SESSION['user_id'] = 1;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $addressLine1 = trim($_POST['address_line1'] ?? '');
    $addressLine2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zipCode = trim($_POST['zip_code'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');

    if (empty($firstName) || empty($lastName) || empty($email) || empty($addressLine1) ||
        empty($city) || empty($state) || empty($zipCode) || empty($country)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
            $stmt->execute([':email' => $email, ':user_id' => $user_id]);
            if ($stmt->fetch()) {
                $error = "This email is already registered to another account.";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    address_line1 = :address_line1,
                    address_line2 = :address_line2,
                    city = :city,
                    state = :state,
                    zip_code = :zip_code,
                    country = :country,
                    phone_number = :phone_number
                    WHERE id = :id");

                $stmt->execute([
                    ':first_name' => $firstName,
                    ':last_name' => $lastName,
                    ':email' => $email,
                    ':address_line1' => $addressLine1,
                    ':address_line2' => $addressLine2,
                    ':city' => $city,
                    ':state' => $state,
                    ':zip_code' => $zipCode,
                    ':country' => $country,
                    ':phone_number' => $phoneNumber,
                    ':id' => $user_id
                ]);

                if ($stmt->rowCount()) {
                    $message = "Your profile has been updated successfully!";
                } else {
                    $error = "No changes were made or an error occurred.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }

    $_SESSION['message'] = $message;
    $_SESSION['error'] = $error;

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$userData = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $userData = $stmt->fetch();

    if (!$userData) {
        die("User not found.");
    }
} catch (PDOException $e) {
    die("Error fetching user data: " . $e->getMessage());
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

foreach ($userData as $key => $value) {
    $userData[$key] = htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
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

    <?php if (!empty($message)): ?>
        <p style="color: green;"><?php echo $message; ?></p>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <h2>Personal Information</h2>
        <label for="first_name">First Name:</label><br>
        <input type="text" id="first_name" name="first_name" value="<?php echo $userData['first_name'] ?? ''; ?>" required><br><br>

        <label for="last_name">Last Name:</label><br>
        <input type="text" id="last_name" name="last_name" value="<?php echo $userData['last_name'] ?? ''; ?>" required><br><br>

        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" value="<?php echo $userData['email'] ?? ''; ?>" required><br><br>

        <label for="phone_number">Phone Number:</label><br>
        <input type="text" id="phone_number" name="phone_number" value="<?php echo $userData['phone_number'] ?? ''; ?>"><br><br>

        <h2>Billing Details</h2>
        <label for="address_line1">Address Line 1:</label><br>
        <input type="text" id="address_line1" name="address_line1" value="<?php echo $userData['address_line1'] ?? ''; ?>" required><br><br>

        <label for="address_line2">Address Line 2 (Optional):</label><br>
        <input type="text" id="address_line2" name="address_line2" value="<?php echo $userData['address_line2'] ?? ''; ?>"><br><br>

        <label for="city">City:</label><br>
        <input type="text" id="city" name="city" value="<?php echo $userData['city'] ?? ''; ?>" required><br><br>

        <label for="state">State/Province:</label><br>
        <input type="text" id="state" name="state" value="<?php echo $userData['state'] ?? ''; ?>" required><br><br>

        <label for="zip_code">Zip/Postal Code:</label><br>
        <input type="text" id="zip_code" name="zip_code" value="<?php echo $userData['zip_code'] ?? ''; ?>" required><br><br>

        <label for="country">Country:</label><br>
        <input type="text" id="country" name="country" value="<?php echo $userData['country'] ?? ''; ?>" required><br><br>

        <input type="submit" value="Update Profile">
    </form>
</body>
</html>
?>