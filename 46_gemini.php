<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_ecommerce');
define('DB_USER', 'root');
define('DB_PASS', '');

$user_id = $_GET['id'] ?? null;
$error_message = '';
$form_data = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone_number' => '',
    'street_address' => '',
    'city' => '',
    'zip_code' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $form_data = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone_number' => trim($_POST['phone_number'] ?? ''),
        'street_address' => trim($_POST['street_address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'zip_code' => trim($_POST['zip_code'] ?? ''),
        'credit_card_number' => trim($_POST['credit_card_number'] ?? ''),
        'credit_card_expiry_date' => trim($_POST['credit_card_expiry_date'] ?? ''),
    ];

    $errors = [];

    if (!is_numeric($user_id) || $user_id <= 0) {
        $errors[] = 'Invalid user ID.';
    }
    if (empty($form_data['first_name'])) {
        $errors[] = 'First name is required.';
    }
    if (empty($form_data['last_name'])) {
        $errors[] = 'Last name is required.';
    }
    if (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
    if (empty($form_data['street_address'])) {
        $errors[] = 'Street address is required.';
    }
    if (empty($form_data['city'])) {
        $errors[] = 'City is required.';
    }
    if (!preg_match('/^\d{5}(?:[-\s]\d{4})?$/', $form_data['zip_code'])) {
        $errors[] = 'Invalid zip code format.';
    }
    if (!empty($form_data['phone_number']) && !preg_match('/^[0-9\s\-\(\)]+$/', $form_data['phone_number'])) {
        $errors[] = 'Invalid phone number format.';
    }

    $cc_number_for_db = null;
    $cc_expiry_for_db = null;

    if (!empty($form_data['credit_card_number'])) {
        $cc_number_for_db = str_replace([' ', '-'], '', $form_data['credit_card_number']);
        if (!preg_match('/^\d{13,19}$/', $cc_number_for_db)) {
            $errors[] = 'Invalid credit card number.';
        }
        if (empty($form_data['credit_card_expiry_date']) || !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $form_data['credit_card_expiry_date'])) {
            $errors[] = 'Invalid credit card expiry date format (MM/YY).';
        } else {
            list($exp_month, $exp_year_short) = explode('/', $form_data['credit_card_expiry_date']);
            $current_year_full = (int) date('Y');
            $current_month = (int) date('m');
            $exp_year_full = (int) ('20' . $exp_year_short);

            if ($exp_year_full < $current_year_full || ($exp_year_full == $current_year_full && (int)$exp_month < $current_month)) {
                $errors[] = 'Credit card has expired.';
            } else {
                $cc_expiry_for_db = $form_data['credit_card_expiry_date'];
            }
        }
    } else {
        if (!empty($form_data['credit_card_expiry_date'])) {
            $errors[] = 'Credit card number is required if expiry date is provided.';
        }
    }

    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
        header('Location: update_profile.php?id=' . urlencode($user_id) . '&error=' . urlencode($error_message) . '&error_fields=' . urlencode(base64_encode(json_encode($form_data))));
        exit;
    }

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
        
        $params = [
            ':first_name' => $form_data['first_name'],
            ':last_name' => $form_data['last_name'],
            ':email' => $form_data['email'],
            ':phone_number' => !empty($form_data['phone_number']) ? $form_data['phone_number'] : null,
            ':street_address' => $form_data['street_address'],
            ':city' => $form_data['city'],
            ':zip_code' => $form_data['zip_code'],
            ':credit_card_number' => $cc_number_for_db,
            ':credit_card_expiry_date' => $cc_expiry_for_db,
            ':id' => $user_id
        ];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            header('Location: profile_success.php');
            exit;
        } else {
            header('Location: update_profile.php?id=' . urlencode($user_id) . '&error=' . urlencode('Profile update failed or no changes were made.'));
            exit;
        }

    } catch (PDOException $e) {
        header('Location: update_profile.php?id=' . urlencode($user_id) . '&error=' . urlencode('Database error: Could not update profile.'));
        exit;
    }
} 
else {
    $user_id = $_GET['id'] ?? null;
    $error_message = $_GET['error'] ?? '';

    if (!is_numeric($user_id) || $user_id <= 0) {
        $error_message = 'Invalid user ID provided.';
        $user_id = null;
    }

    if ($user_id !== null) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone_number, street_address, city, zip_code FROM users WHERE id = :id");
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $fetched_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fetched_data) {
                $form_data = $fetched_data;
            } else {
                $error_message = 'User not found.';
                $user_id = null;
            }

        } catch (PDOException $e) {
            $error_message = 'Database error: Could not load profile.';
            $user_id = null;
        }
    }

    if (!empty($_GET['error_fields'])) {
        $repop_data = json_decode(base64_decode($_GET['error_fields']), true);
        if ($repop_data) {
            $form_data = array_merge($form_data, $repop_data);
        }
    }
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

    <?php if (!empty($error_message)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <?php if ($user_id !== null): ?>
    <form action="update_profile.php" method="POST">
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">

        <label for="first_name">First Name:</label><br>
        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>" required><br><br>

        <label for="last_name">Last Name:</label><br>
        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>" required><br><br>

        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required><br><br>

        <label for="phone_number">Phone Number:</label><br>
        <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($form_data['phone_number'] ?? ''); ?>"><br><br>

        <label for="street_address">Street Address:</label><br>
        <input type="text" id="street_address" name="street_address" value="<?php echo htmlspecialchars($form_data['street_address'] ?? ''); ?>" required><br><br>

        <label for="city">City:</label><br>
        <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($form_data['city'] ?? ''); ?>" required><br><br>

        <label for="zip_code">Zip Code:</label><br>
        <input type="text" id="zip_code" name="zip_code" value="<?php echo htmlspecialchars($form_data['zip_code'] ?? ''); ?>" required><br><br>

        <label for="credit_card_number">Credit Card Number:</label><br>
        <input type="text" id="credit_card_number" name="credit_card_number" placeholder="Enter new card number" autocomplete="off" value=""><br><br>

        <label for="credit_card_expiry_date">Credit Card Expiry Date (MM/YY):</label><br>
        <input type="text" id="credit_card_expiry_date" name="credit_card_expiry_date" placeholder="MM/YY" autocomplete="off" value=""><br><br>

        <input type="submit" value="Update Profile">
    </form>
    <?php else: ?>
        <p>Unable to load profile form.</p>
    <?php endif; ?>
</body>
</html>
?>