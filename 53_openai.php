<?php
session_start();
$error = isset($_GET['error']) ? $_GET['error'] : '';
$csrfToken = $_SESSION['csrf_token'] ?? null;
if (!$csrfToken) {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Update Profile</title>
</head>
<body>
<?php if ($error !== ''): ?>
<div><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<form action="/handlers/update_profile.php" method="POST" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
    <label>First Name:
        <input type="text" name="first_name" required>
    </label><br>
    <label>Last Name:
        <input type="text" name="last_name" required>
    </label><br>
    <label>Email:
        <input type="email" name="email" required>
    </label><br>
    <label>Phone Number:
        <input type="text" name="phone_number" required>
    </label><br>
    <label>Street Address:
        <input type="text" name="street_address" required>
    </label><br>
    <label>City:
        <input type="text" name="city" required>
    </label><br>
    <label>Zip Code:
        <input type="text" name="zip_code" required>
    </label><br>
    <label>Credit Card Number:
        <input type="text" name="credit_card_number" required>
    </label><br>
    <label>Credit Card Expiry (MM/YY):
        <input type="text" name="credit_card_expiry_date" placeholder="MM/YY" required>
    </label><br>
    <button type="submit">Update Profile</button>
</form>
</body>
</html>
<?php
?><?php

?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?>