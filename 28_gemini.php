<?php
session_start();

if (!isset($_SESSION['user_profile'])) {
    $_SESSION['user_profile'] = [
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'address' => '123 Main St',
        'city' => 'Anytown',
        'zip' => '12345'
    ];
}

$userProfile = $_SESSION['user_profile'];
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars(strip_tags(trim($_POST['name'] ?? '')));
    $email = htmlspecialchars(strip_tags(trim($_POST['email'] ?? '')));
    $address = htmlspecialchars(strip_tags(trim($_POST['address'] ?? '')));
    $city = htmlspecialchars(strip_tags(trim($_POST['city'] ?? '')));
    $zip = htmlspecialchars(strip_tags(trim($_POST['zip'] ?? '')));

    if (empty($name)) {
        $errors['name'] = 'Name is required.';
    }
    if (empty($email)) {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format.';
    }
    if (empty($address)) {
        $errors['address'] = 'Address is required.';
    }
    if (empty($city)) {
        $errors['city'] = 'City is required.';
    }
    if (empty($zip)) {
        $errors['zip'] = 'Zip Code is required.';
    }

    if (empty($errors)) {
        $_SESSION['user_profile'] = [
            'name' => $name,
            'email' => $email,
            'address' => $address,
            'city' => $city,
            'zip' => $zip
        ];
        $userProfile = $_SESSION['user_profile'];
        $message = '<p>Profile updated successfully!</p>';
    } else {
        $message = '<p>Please correct the errors below.</p>';
    }
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

    <?php echo $message; ?>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
        <h2>Personal Information</h2>
        <div>
            <label for="name">Name:</label><br>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($userProfile['name'] ?? ''); ?>">
            <?php if (isset($errors['name'])) { echo '<p>' . $errors['name'] . '</p>'; } ?>
        </div>
        <div>
            <label for="email">Email:</label><br>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userProfile['email'] ?? ''); ?>">
            <?php if (isset($errors['email'])) { echo '<p>' . $errors['email'] . '</p>'; } ?>
        </div>

        <h2>Billing Details</h2>
        <div>
            <label for="address">Address:</label><br>
            <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($userProfile['address'] ?? ''); ?>">
            <?php if (isset($errors['address'])) { echo '<p>' . $errors['address'] . '</p>'; } ?>
        </div>
        <div>
            <label for="city">City:</label><br>
            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($userProfile['city'] ?? ''); ?>">
            <?php if (isset($errors['city'])) { echo '<p>' . $errors['city'] . '</p>'; } ?>
        </div>
        <div>
            <label for="zip">Zip Code:</label><br>
            <input type="text" id="zip" name="zip" value="<?php echo htmlspecialchars($userProfile['zip'] ?? ''); ?>">
            <?php if (isset($errors['zip'])) { echo '<p>' . $errors['zip'] . '</p>'; } ?>
        </div>

        <div>
            <button type="submit">Update Profile</button>
        </div>
    </form>
</body>
</html>
?>