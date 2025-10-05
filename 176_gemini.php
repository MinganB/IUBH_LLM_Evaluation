<?php
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_lifetime', 0);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.sid_length', 48);
ini_set('session.sid_bits_per_character', 6);
session_name('MyAppSession');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $input_username = $_POST['username'];
    $input_password = $_POST['password'];

    $valid_username = "admin";
    $valid_password_hash = password_hash("securepassword123", PASSWORD_DEFAULT);

    if ($input_username === $valid_username && password_verify($input_password, $valid_password_hash)) {
        session_regenerate_id(true);

        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = $valid_username;
        $_SESSION['last_activity'] = time();

        header("Location: dashboard.php");
        exit;
    } else {
        $_SESSION['login_error'] = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <h2>Login to Dashboard</h2>
    <?php
    if (isset($_SESSION['login_error'])) {
        echo '<p style="color: red;">' . htmlspecialchars($_SESSION['login_error']) . '</p>';
        unset($_SESSION['login_error']);
    }
    if (isset($_GET['loggedout'])) {
        echo '<p style="color: green;">You have been logged out successfully.</p>';
    }
    if (isset($_GET['timeout'])) {
        echo '<p style="color: orange;">Your session has timed out due to inactivity.</p>';
    }
    ?>
    <form action="session_handler.php" method="POST">
        <label for="username">Username:</label><br>
        <input type="text" id="username" name="username" required><br><br>
        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required><br><br>
        <input type="submit" value="Login">
    </form>
</body>
</html>
?>