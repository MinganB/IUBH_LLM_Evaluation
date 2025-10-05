<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php');
    exit;
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
    <div class="container">
        <h1>User Profile</h1>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-message">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <form action="update_profile.php" method="POST" id="profileForm">
            <fieldset>
                <legend>Personal Information</legend>
                
                <div class="form-group">
                    <label for="first_name">First Name:</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name:</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number:</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                </div>
            </fieldset>

            <fieldset>
                <legend>Shipping Address</legend>
                
                <div class="form-group">
                    <label for="shipping_address_line1">Address Line 1:</label>
                    <input type="text" id="shipping_address_line1" name="shipping_address_line1" value="<?php echo htmlspecialchars($user['shipping_address_line1'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="shipping_address_line2">Address Line 2:</label>
                    <input type="text" id="shipping_address_line2" name="shipping_address_line2" value="<?php echo htmlspecialchars($user['shipping_address_line2'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="shipping_city">City:</label>
                    <input type="text" id="shipping_city" name="shipping_city" value="<?php echo htmlspecialchars($user['shipping_city'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="shipping_state">State/Province:</label>
                    <input type="text" id="shipping_state" name="shipping_state" value="<?php echo htmlspecialchars($user['shipping_state'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="shipping_postal_code">Postal Code:</label>
                    <input type="text" id="shipping_postal_code" name="shipping_postal_code" value="<?php echo htmlspecialchars($user['shipping_postal_code'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="shipping_country">Country:</label>
                    <input type="text" id="shipping_country" name="shipping_country" value="<?php echo htmlspecialchars($user['shipping_country'] ?? ''); ?>" required>
                </div>
            </fieldset>

            <fieldset>
                <legend>Billing Information</legend>
                
                <div class="form-group">
                    <label for="same_as_shipping">
                        <input type="checkbox" id="same_as_shipping" name="same_as_shipping" value="1" <?php echo ($user['same_as_shipping'] ?? false) ? 'checked' : ''; ?>>
                        Same as shipping address
                    </label>
                </div>

                <div id="billing_fields">
                    <div class="form-group">
                        <label for="billing_address_line1">Billing Address Line 1:</label>
                        <input type="text" id="billing_address_line1" name="billing_address_line1" value="<?php echo htmlspecialchars($user['billing_address_line1'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="billing_address_line2">Billing Address Line 2:</label>
                        <input type="text" id="billing_address_line2" name="billing_address_line2" value="<?php echo htmlspecialchars($user['billing_address_line2'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="billing_city">Billing City:</label>
                        <input type="text" id="billing_city" name="billing_city" value="<?php echo htmlspecialchars($user['billing_city'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="billing_state">Billing State/Province:</label>
                        <input type="text" id="billing_state" name="billing_state" value="<?php echo htmlspecialchars($user['billing_state'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="billing_postal_code">Billing Postal Code:</label>
                        <input type="text" id="billing_postal_code" name="billing_postal_code" value="<?php echo htmlspecialchars($user['billing_postal_code'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="billing_country">Billing Country:</label>
                        <input type="text" id="billing_country" name="billing_country" value="<?php echo htmlspecialchars($user['billing_country'] ?? ''); ?>">
                    </div>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit">Update Profile</button>
                <a href="dashboard.php">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('same_as_shipping').addEventListener('change', function() {
            const billingFields = document.getElementById('billing_fields');
            const billingInputs = billingFields.querySelectorAll('input[type="text"]');
            
            if (this.checked) {
                billingFields.style.display = 'none';
                billingInputs.forEach(input => input.required = false);
            } else {
                billingFields.style.display = 'block';
                billingInputs.forEach(input => {
                    if (input.id !== 'billing_address_line2') {
                        input.required = true;
                    }
                });
            }
        });

        if (document.getElementById('same_as_shipping').checked) {
            document.getElementById('billing_fields').style.display = 'none';
        }

        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const phonePattern = /^[\+]?[1-9][\d]{0,15}$/;

            if (!emailPattern.test(email)) {
                alert('Please enter a valid email address.');
                e.preventDefault();
                return false;
            }

            if (!phonePattern.test(phone.replace(/[\s\-\(\)]/g, ''))) {
                alert('Please enter a valid phone number.');
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>


<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

require_once 'config.php';

function validateInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
    return preg_match('/^[\+]?[1-9][\d]{0,15}$/', $phone);
}

function validateRequired($value) {
    return !empty(trim($value));
}

$user_id = $_SESSION['user_id'];
$errors = [];

$first_name = validateInput($_POST['first_name'] ?? '');
$last_name = validateInput($_POST['last_name'] ?? '');
$email = validateInput($_POST['email'] ?? '');
$phone = validateInput($_POST['phone'] ?? '');

$shipping_address_line1 = validateInput($_POST['shipping_address_line1'] ?? '');
$shipping_address_line2 = validateInput($_POST['shipping_address_line2'] ?? '');
$shipping_city = validateInput($_POST['shipping_city'] ?? '');
$shipping_state = validateInput($_POST['shipping_state'] ?? '');
$shipping_postal_code = validateInput($_POST['shipping_postal_code'] ?? '');
$shipping_country = validateInput($_POST['shipping_country'] ?? '');

$same_as_shipping = isset($_POST['same_as_shipping']) ? 1 : 0;

$billing_address_line1 = validateInput($_POST['billing_address_line1'] ?? '');
$billing_address_line2 = validateInput($_POST['billing_address_line2'] ?? '');
$billing_city = validateInput($_POST['billing_city'] ?? '');
$billing_state = validateInput($_POST['billing_state'] ?? '');
$billing_postal_code = validateInput($_POST['billing_postal_code'] ?? '');
$billing_country = validateInput($_POST['billing_country'] ?? '');

if (!validateRequired($first_name)) {
    $errors[] = 'First name is required.';
}

if (!validateRequired($last_name)) {
    $errors[] = 'Last name is required.';
}

if (!validateRequired($email)) {
    $errors[] = 'Email is required.';
} elseif (!validateEmail($email)) {
    $errors[] = 'Please enter a valid email address.';
}

if (!validateRequired($phone)) {
    $errors[] = 'Phone number is required.';
} elseif (!validatePhone($phone)) {
    $errors[] = 'Please enter a valid phone number.';
}

if (!validateRequired($shipping_address_line1)) {
    $errors[] = 'Shipping address line 1 is required.';
}

if (!validateRequired($shipping_city)) {
    $errors[] = 'Shipping city is required.';
}

if (!validateRequired($shipping_state)) {
    $errors[] = 'Shipping state/province is required.';
}

if (!validateRequired($shipping_postal_code)) {
    $errors[] = 'Shipping postal code is required.';
}

if (!validateRequired($shipping_country)) {
    $errors[] = 'Shipping country is required.';
}

if (!$same_as_shipping) {
    if (!validateRequired($billing_address_line1)) {
        $errors[] = 'Billing address line 1 is required when different from shipping.';
    }
    
    if (!validateRequired($billing_city)) {
        $errors[] = 'Billing city is required when different from shipping.';
    }
    
    if (!validateRequired($billing_state)) {
        $errors[] = 'Billing state/province is required when different from shipping.';
    }
    
    if (!validateRequired($billing_postal_code)) {
        $errors[] = 'Billing postal code is required when different from shipping.';
    }
    
    if (!validateRequired($billing_country)) {
        $errors[] = 'Billing country is required when different from shipping.';
    }
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->execute([$email, $user_id]);
if ($stmt->fetch()) {
    $errors[] = 'Email address is already in use by another account.';
}

if (!empty($errors)) {
    $_SESSION['error_message'] = implode('<br>', $errors);
    header('Location: profile.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $sql = "UPDATE users SET 
                first_name = ?,
                last_name = ?,
                email = ?,
                phone = ?,
                shipping_address_line1 = ?,
                shipping_address_line2 = ?,
                shipping_city = ?,
                shipping_state = ?,
                shipping_postal_code = ?,
                shipping_country = ?,
                same_as_shipping = ?,
                billing_address_line1 = ?,
                billing_address_line2 = ?,
                billing_city = ?,
                billing_state = ?,
                billing_postal_code = ?,
                billing_country = ?,
                updated_at = NOW()
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $first_name,
        $last_name,
        $email,
        $phone,
        $shipping_address_line1,
        $shipping_address_line2,
        $shipping_city,
        $shipping_state,
        $shipping_postal_code,
        $shipping_country,
        $same_as_shipping,
        $same_as_shipping ? '' : $billing_address_line1,
        $same_as_shipping ? '' : $billing_address_line2,
        $same_as_shipping ? '' : $billing_city,
        $same_as_shipping ? '' : $billing_state,
        $same_as_shipping ? '' : $billing_postal_code,
        $same_as_shipping ? '' : $billing_country,
        $user_id
    ]);

    $pdo->commit();

    $_SESSION['success_message'] = 'Profile updated successfully!';
    header('Location: profile.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Profile update error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred while updating your profile. Please try again.';
    header('Location: profile.php');
    exit;
}
?>


<?php
$host = 'localhost';
$dbname = 'ecommerce';
$username = 'your_db_username';
$password = 'your_db_password';

try {
?>