html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile</title>
</head>
<body>
    <form action="update_profile.php" method="POST">
        <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id'] ?? ''; ?>">
        
        <label for="first_name">First Name:</label>
        <input type="text" id="first_name" name="first_name" required>
        
        <label for="last_name">Last Name:</label>
        <input type="text" id="last_name" name="last_name" required>
        
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        
        <label for="phone_number">Phone Number:</label>
        <input type="tel" id="phone_number" name="phone_number" required>
        
        <label for="street_address">Street Address:</label>
        <input type="text" id="street_address" name="street_address" required>
        
        <label for="city">City:</label>
        <input type="text" id="city" name="city" required>
        
        <label for="zip_code">Zip Code:</label>
        <input type="text" id="zip_code" name="zip_code" required>
        
        <label for="credit_card_number">Credit Card Number:</label>
        <input type="text" id="credit_card_number" name="credit_card_number" required>
        
        <label for="credit_card_expiry_date">Credit Card Expiry Date:</label>
        <input type="text" id="credit_card_expiry_date" name="credit_card_expiry_date" placeholder="MM/YY" required>
        
        <button type="submit">Update Profile</button>
    </form>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="error">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>
</body>
</html>


<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile_form.php');
    exit();
}

$host = 'localhost';
$dbname = 'db_ecommerce';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header('Location: profile_form.php?error=' . urlencode('Database connection failed'));
    exit();
}

$user_id = $_POST['user_id'] ?? null;
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$street_address = trim($_POST['street_address'] ?? '');
$city = trim($_POST['city'] ?? '');
$zip_code = trim($_POST['zip_code'] ?? '');
$credit_card_number = trim($_POST['credit_card_number'] ?? '');
$credit_card_expiry_date = trim($_POST['credit_card_expiry_date'] ?? '');

if (empty($user_id) || !is_numeric($user_id)) {
    header('Location: profile_form.php?error=' . urlencode('Invalid user ID'));
    exit();
}

if (empty($first_name) || strlen($first_name) > 50) {
    header('Location: profile_form.php?error=' . urlencode('First name is required and must be less than 50 characters'));
    exit();
}

if (empty($last_name) || strlen($last_name) > 50) {
    header('Location: profile_form.php?error=' . urlencode('Last name is required and must be less than 50 characters'));
    exit();
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
    header('Location: profile_form.php?error=' . urlencode('Valid email is required and must be less than 100 characters'));
    exit();
}

if (empty($phone_number) || !preg_match('/^[\d\-\+\(\)\s]{10,20}$/', $phone_number)) {
    header('Location: profile_form.php?error=' . urlencode('Valid phone number is required'));
    exit();
}

if (empty($street_address) || strlen($street_address) > 200) {
    header('Location: profile_form.php?error=' . urlencode('Street address is required and must be less than 200 characters'));
    exit();
}

if (empty($city) || strlen($city) > 50) {
    header('Location: profile_form.php?error=' . urlencode('City is required and must be less than 50 characters'));
    exit();
}

if (empty($zip_code) || !preg_match('/^[\d\-A-Za-z\s]{3,10}$/', $zip_code)) {
    header('Location: profile_form.php?error=' . urlencode('Valid zip code is required'));
    exit();
}

if (empty($credit_card_number) || !preg_match('/^\d{13,19}$/', preg_replace('/\s|-/', '', $credit_card_number))) {
    header('Location: profile_form.php?error=' . urlencode('Valid credit card number is required'));
    exit();
}

if (empty($credit_card_expiry_date) || !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $credit_card_expiry_date)) {
    header('Location: profile_form.php?error=' . urlencode('Valid expiry date is required (MM/YY format)'));
    exit();
}

$credit_card_number_clean = preg_replace('/\s|-/', '', $credit_card_number);

try {
    $stmt = $pdo->prepare("UPDATE users SET 
        first_name = ?, 
        last_name = ?, 
        email = ?, 
        phone_number = ?, 
        street_address = ?, 
        city = ?, 
        zip_code = ?, 
        credit_card_number = ?, 
        credit_card_expiry_date = ? 
        WHERE id = ?");
    
    $result = $stmt->execute([
        $first_name,
        $last_name,
        $email,
        $phone_number,
        $street_address,
        $city,
        $zip_code,
        $credit_card_number_clean,
        $credit_card_expiry_date,
        $user_id
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        header('Location: profile_success.php');
        exit();
    } else {
        header('Location: profile_form.php?error=' . urlencode('Update failed or no changes were made'));
        exit();
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        header('Location: profile_form.php?error=' . urlencode('Email already exists'));
    } else {
        header('Location: profile_form.php?error=' . urlencode('Update failed'));
    }
    exit();
}
?>


<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Updated Successfully</title>
</head>
<body>
    <h1>Profile Updated Successfully</h1>
    <p>Your profile has been updated successfully.</p>
    <a href="profile_form.php">Back to Profile</a>
</body>
</html>