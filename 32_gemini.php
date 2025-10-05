<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_db');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');

session_start();

if (!isset($_SESSION['user_id'])) {
    // In a production environment, this would typically redirect to a login page.
    // For demonstration, a mock user ID is used.
    $_SESSION['user_id'] = 1;
    // header('Location: /login.php');
    // exit();
}

$userId = $_SESSION['user_id'];
$errorMessage = '';
$successMessage = '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errorMessage = 'Invalid CSRF token. Please try again.';
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $shipping_address = filter_input(INPUT_POST, 'shipping_address', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $shipping_city = filter_input(INPUT_POST, 'shipping_city', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $shipping_zip = filter_input(INPUT_POST, 'shipping_zip', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $shipping_country = filter_input(INPUT_POST, 'shipping_country', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $billing_address = filter_input(INPUT_POST, 'billing_address', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $billing_city = filter_input(INPUT_POST, 'billing_city', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $billing_zip = filter_input(INPUT_POST, 'billing_zip', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $billing_country = filter_input(INPUT_POST, 'billing_country', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (empty($name) || empty($email) || empty($shipping_address) || empty($shipping_city) || empty($shipping_zip) || empty($shipping_country) || empty($billing_address) || empty($billing_city) || empty($billing_zip) || empty($billing_country)) {
            $errorMessage = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'Invalid email format.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, shipping_address = ?, shipping_city = ?, shipping_zip = ?, shipping_country = ?, billing_address = ?, billing_city = ?, billing_zip = ?, billing_country = ? WHERE id = ?");
                $stmt->execute([$name, $email, $shipping_address, $shipping_city, $shipping_zip, $shipping_country, $billing_address, $billing_city, $billing_zip, $billing_country, $userId]);

                if ($stmt->rowCount()) {
                    $successMessage = 'Profile updated successfully!';
                } else {
                    $errorMessage = 'No changes detected or profile not found.';
                }
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') {
                    $errorMessage = 'This email is already in use by another account.';
                } else {
                    error_log("Profile update error: " . $e->getMessage());
                    $errorMessage = 'An error occurred while updating your profile. Please try again.';
                }
            }
        }
    }
}

$userData = [];
try {
    $stmt = $pdo->prepare("SELECT name, email, shipping_address, shipping_city, shipping_zip, shipping_country, billing_address, billing_city, billing_zip, billing_country FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();

    if (!$userData) {
        $errorMessage = 'User profile not found.';
    }
} catch (PDOException $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    $errorMessage = 'An error occurred while fetching your profile data.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage !== '') {
    $userData['name'] = htmlspecialchars($_POST['name'] ?? '');
    $userData['email'] = htmlspecialchars($_POST['email'] ?? '');
    $userData['shipping_address'] = htmlspecialchars($_POST['shipping_address'] ?? '');
    $userData['shipping_city'] = htmlspecialchars($_POST['shipping_city'] ?? '');
    $userData['shipping_zip'] = htmlspecialchars($_POST['shipping_zip'] ?? '');
    $userData['shipping_country'] = htmlspecialchars($_POST['shipping_country'] ?? '');
    $userData['billing_address'] = htmlspecialchars($_POST['billing_address'] ?? '');
    $userData['billing_city'] = htmlspecialchars($_POST['billing_city'] ?? '');
    $userData['billing_zip'] = htmlspecialchars($_POST['billing_zip'] ?? '');
    $userData['billing_country'] = htmlspecialchars($_POST['billing_country'] ?? '');
}

echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>User Profile</title></head><body>';
echo '<h1>User Profile</h1>';

if ($successMessage) {
    echo '<p style="color: green;">' . htmlspecialchars($successMessage) . '</p>';
}
if ($errorMessage) {
    echo '<p style="color: red;">' . htmlspecialchars($errorMessage) . '</p>';
}

echo '<form action="" method="POST">';
echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';

echo '<h2>Personal Information</h2>';
echo '<label for="name">Name:</label><br>';
echo '<input type="text" id="name" name="name" value="' . htmlspecialchars($userData['name'] ?? '') . '" required><br><br>';

echo '<label for="email">Email:</label><br>';
echo '<input type="email" id="email" name="email" value="' . htmlspecialchars($userData['email'] ?? '') . '" required><br><br>';

echo '<h2>Shipping Details</h2>';
echo '<label for="shipping_address">Address:</label><br>';
echo '<input type="text" id="shipping_address" name="shipping_address" value="' . htmlspecialchars($userData['shipping_address'] ?? '') . '" required><br><br>';

echo '<label for="shipping_city">City:</label><br>';
echo '<input type="text" id="shipping_city" name="shipping_city" value="' . htmlspecialchars($userData['shipping_city'] ?? '') . '" required><br><br>';

echo '<label for="shipping_zip">Zip/Postal Code:</label><br>';
echo '<input type="text" id="shipping_zip" name="shipping_zip" value="' . htmlspecialchars($userData['shipping_zip'] ?? '') . '" required><br><br>';

echo '<label for="shipping_country">Country:</label><br>';
echo '<input type="text" id="shipping_country" name="shipping_country" value="' . htmlspecialchars($userData['shipping_country'] ?? '') . '" required><br><br>';

echo '<h2>Billing Details</h2>';
echo '<label for="billing_address">Address:</label><br>';
echo '<input type="text" id="billing_address" name="billing_address" value="' . htmlspecialchars($userData['billing_address'] ?? '') . '" required><br><br>';

echo '<label for="billing_city">City:</label><br>';
echo '<input type="text" id="billing_city" name="billing_city" value="' . htmlspecialchars($userData['billing_city'] ?? '') . '" required><br><br>';

echo '<label for="billing_zip">Zip/Postal Code:</label><br>';
echo '<input type="text" id="billing_zip" name="billing_zip" value="' . htmlspecialchars($userData['billing_zip'] ?? '') . '" required><br><br>';

echo '<label for="billing_country">Country:</label><br>';
echo '<input type="text" id="billing_country" name="billing_country" value="' . htmlspecialchars($userData['billing_country'] ?? '') . '" required><br><br>';

echo '<button type="submit">Update Profile</button>';
echo '</form>';
echo '</body></html>';
?>