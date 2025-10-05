<?php
session_start();

define('CURRENT_USER_ID', 1);

try {
    $db = new PDO('sqlite:' . __DIR__ . '/profile.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        first_name TEXT,
        last_name TEXT,
        email TEXT UNIQUE,
        phone TEXT,
        address1 TEXT,
        address2 TEXT,
        city TEXT,
        state TEXT,
        postal_code TEXT,
        country TEXT
    )");

    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE id = :id");
    $stmt->bindValue(':id', CURRENT_USER_ID, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO users (id, first_name, last_name, email, phone, address1, address2, city, state, postal_code, country) VALUES (:id, :first_name, :last_name, :email, :phone, :address1, :address2, :city, :state, :postal_code, :country)");
        $stmt->bindValue(':id', CURRENT_USER_ID, PDO::PARAM_INT);
        $stmt->bindValue(':first_name', 'Jane', PDO::PARAM_STR);
        $stmt->bindValue(':last_name', 'Doe', PDO::PARAM_STR);
        $stmt->bindValue(':email', 'jane.doe@example.com', PDO::PARAM_STR);
        $stmt->bindValue(':phone', '123-555-0199', PDO::PARAM_STR);
        $stmt->bindValue(':address1', '456 Oak Avenue', PDO::PARAM_STR);
        $stmt->bindValue(':address2', null, PDO::PARAM_STR);
        $stmt->bindValue(':city', 'Anytown', PDO::PARAM_STR);
        $stmt->bindValue(':state', 'NY', PDO::PARAM_STR);
        $stmt->bindValue(':postal_code', '10001', PDO::PARAM_STR);
        $stmt->bindValue(':country', 'USA', PDO::PARAM_STR);
        $stmt->execute();
    }

} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($token) || $_SESSION['csrf_token'] !== $token) {
        return false;
    }
    return true;
}

function getUserProfile($userId, PDO $db) {
    $stmt = $db->prepare("SELECT first_name, last_name, email, phone, address1, address2, city, state, postal_code, country FROM users WHERE id = :id");
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch();
}

function updateUserProfile($userId, array $data, PDO $db) {
    $stmt = $db->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone, address1 = :address1, address2 = :address2, city = :city, state = :state, postal_code = :postal_code, country = :country WHERE id = :id");

    $stmt->bindValue(':first_name', $data['first_name'], PDO::PARAM_STR);
    $stmt->bindValue(':last_name', $data['last_name'], PDO::PARAM_STR);
    $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
    $stmt->bindValue(':phone', empty($data['phone']) ? null : $data['phone'], PDO::PARAM_STR);
    $stmt->bindValue(':address1', $data['address1'], PDO::PARAM_STR);
    $stmt->bindValue(':address2', empty($data['address2']) ? null : $data['address2'], PDO::PARAM_STR);
    $stmt->bindValue(':city', $data['city'], PDO::PARAM_STR);
    $stmt->bindValue(':state', $data['state'], PDO::PARAM_STR);
    $stmt->bindValue(':postal_code', $data['postal_code'], PDO::PARAM_STR);
    $stmt->bindValue(':country', $data['country'], PDO::PARAM_STR);
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

    return $stmt->execute();
}

$errors = [];
$successMessage = '';
$userData = getUserProfile(CURRENT_USER_ID, $db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
        unset($_SESSION['csrf_token']);
        generateCsrfToken();
    } else {
        $input = [];

        $input['first_name'] = trim($_POST['first_name'] ?? '');
        $input['last_name'] = trim($_POST['last_name'] ?? '');
        $input['email'] = trim($_POST['email'] ?? '');
        $input['phone'] = trim($_POST['phone'] ?? '');

        $input['address1'] = trim($_POST['address1'] ?? '');
        $input['address2'] = trim($_POST['address2'] ?? '');
        $input['city'] = trim($_POST['city'] ?? '');
        $input['state'] = trim($_POST['state'] ?? '');
        $input['postal_code'] = trim($_POST['postal_code'] ?? '');
        $input['country'] = trim($_POST['country'] ?? '');

        if (empty($input['first_name'])) { $errors['first_name'] = 'First Name is required.'; }
        if (empty($input['last_name'])) { $errors['last_name'] = 'Last Name is required.'; }
        if (empty($input['email'])) {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format.';
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :id");
            $stmt->bindValue(':email', $input['email'], PDO::PARAM_STR);
            $stmt->bindValue(':id', CURRENT_USER_ID, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $errors['email'] = 'This email is already in use by another account.';
            }
        }
        if (!empty($input['phone']) && !preg_match('/^\+?\d{1,3}[\s-]?\(?\d{3}\)?[\s-]?\d{3}[\s-]?\d{4}$/', $input['phone'])) {
            $errors['phone'] = 'Invalid phone number format.';
        }
        if (empty($input['address1'])) { $errors['address1'] = 'Address Line 1 is required.'; }
        if (empty($input['city'])) { $errors['city'] = 'City is required.'; }
        if (empty($input['state'])) { $errors['state'] = 'State/Province is required.'; }
        if (empty($input['postal_code'])) { $errors['postal_code'] = 'Postal Code is required.'; }
        if (empty($input['country'])) { $errors['country'] = 'Country is required.'; }

        if (empty($errors)) {
            try {
                if (updateUserProfile(CURRENT_USER_ID, $input, $db)) {
                    $_SESSION['success_message'] = 'Profile updated successfully!';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    $errors[] = 'Failed to update profile. Please try again.';
                }
            } catch (PDOException $e) {
                error_log("Profile update error: " . $e->getMessage());
                $errors[] = 'A database error occurred during update. Please try again.';
            }
        }
    }
}

$userData = getUserProfile(CURRENT_USER_ID, $db);

if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$csrfToken = generateCsrfToken();
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

    <?php if (!empty($successMessage)): ?>
        <div style="color: green; border: 1px solid green; padding: 10px; margin-bottom: 15px;">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div style="color: red; border: 1px solid red; padding: 10px; margin-bottom: 15px;">
            <p>Please correct the following errors:</p>
            <ul>
                <?php foreach ($errors as $field => $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <fieldset>
            <legend>Personal Information</legend>
            <div>
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? $userData['first_name'] ?? ''); ?>" required>
                <?php if (isset($errors['first_name'])): ?><span style="color: red;"><?php echo htmlspecialchars($errors['first_name']); ?></span><?php endif; ?>
            </div>
            <div>
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? $userData['last_name'] ?? ''); ?>" required>
                <?php if (isset($errors['last_name'])): ?><span style="color: red;"><?php echo htmlspecialchars($errors['last_name']); ?></span><?php endif; ?>
            </div>
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $userData['email'] ?? ''); ?>" required>
                <?php if (isset($errors['email'])): ?><span style="color: red;"><?php echo htmlspecialchars($errors['email']); ?></span><?php endif; ?>
            </div>
            <div>
                <label for="phone">Phone Number (optional):</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? $userData['phone'] ?? ''); ?>">
                <?php if (isset($errors['phone'])): ?><span style="color: red;"><?php echo htmlspecialchars($errors['phone']); ?></span><?php endif; ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Billing Details</legend>
            <div>
                <label for="address1">Address Line 1:</label>
                <input type="text" id="address1" name="address1" value="<?php echo htmlspecialchars($_POST['address1'] ?? $userData['address1'] ?? ''); ?>" required>
                <?php if (isset($errors['address1'])): ?><span style="color: red;"><?php echo htmlspecialchars($errors['address1']); ?></span><?php endif; ?>
            </div>
            <div>
                <label for="address2">Address Line 2 (optional):</label>
                <input type="text" id="address2" name="address2" value="<?php echo htmlspecialchars($_POST['address2'] ?? $userData['address2'] ?? ''); ?>">
            </div>
            <div>
                <label for="city">City:</label>
                <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($_POST['city'] ?? $userData['city'] ?? ''); ?>" required>
                <?php if (isset($errors['city'])): ?><span style="color: red;"><?php echo htmlspecialchars($errors['city']); ?></span><?php endif; ?>
            </div>
            <div>
                <label for="state">State/Province:</label>
                <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($_POST['state'] ?? $userData['state'] ?? ''); ?>" required>
                <?php if (isset($errors['state'])): ?><span style="color: red;"><?php echo htmlspecialchars($errors['state']); ?></span><?php endif; ?>
            </div>
            <div>
                <label for="postal_code">Postal Code:</label>
                <input type="text" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($_POST['postal_code'] ?? $userData['postal_code'] ?? ''); ?>" required>
                <?php if (isset($errors['postal_code'])): ?><span style="color: red;"><?php echo htmlspecialchars($errors['postal_code']); ?></span><?php endif; ?>
            </div>
            <div>
                <label for="country">Country:</label>
                <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($_POST['country'] ?? $userData['country'] ?? ''); ?>" required>
                <?php if (isset($errors['country'])): ?><span style="color: red;"><?php echo htmlspecialchars($errors['country']); ?></span><?php endif; ?>
            </div>
        </fieldset>

        <div>
            <button type="submit">Update Profile</button>
        </div>
    </form>
</body>
</html>
?>