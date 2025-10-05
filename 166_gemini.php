<?php
session_set_cookie_params(3600, '/', '', isset($_SERVER["HTTPS"]), true);
session_start();

$error_message = '';

$valid_username = 'admin';
$hashed_password = password_hash('password123', PASSWORD_DEFAULT);

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];

    if ($username === $valid_username && password_verify($password, $hashed_password)) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = $username;

        header('Location: dashboard.php');
        exit;
    } else {
        $error_message = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Dashboard</title>
</head>
<body>
    <h2>Login to Dashboard</h2>
    <?php if ($error_message): ?>
        <p style="color: red;"><?php echo $error_message; ?></p>
    <?php endif; ?>
    <form action="login.php" method="POST">
        <div>
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div>
            <button type="submit">Login</button>
        </div>
    </form>
</body>
</html>
<?php
session_set_cookie_params(3600, '/', '', isset($_SERVER["HTTPS"]), true);
session_start();

if (!isset($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/', '', isset($_SERVER["HTTPS"]), true);

    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <h2>Welcome to the Dashboard, <?php echo htmlspecialchars($username); ?>!</h2>
    <p>This is your secure content.</p>
    <ul>
        <li><a href="#">Dashboard Link 1</a></li>
        <li><a href="#">Dashboard Link 2</a></li>
        <li><a href="#">Dashboard Link 3</a></li>
    </ul>
    <p><a href="logout.php">Logout</a></p>
</body>
</html>
<?php
session_set_cookie_params(3600, '/', '', isset($_SERVER["HTTPS"]), true);
session_start();

$_SESSION = array();

session_destroy();

setcookie(session_name(), '', time() - 3600, '/', '', isset($_SERVER["HTTPS"]), true);

header('Location: login.php');
exit;
?>