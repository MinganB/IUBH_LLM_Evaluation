<?php

// session_handler.php

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

$response = ['success' => false, 'message' => 'Invalid request method.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['user_id'] = 123;
    $response = ['success' => true, 'message' => 'Login successful.'];
}

header('Content-Type: application/json');
echo json_encode($response);
exit;

?>
<?php

// dashboard.php

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

if (isset($_SESSION['user_id'])) {
    $userId = htmlspecialchars($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome to the Dashboard!</h1>
    <p>You are logged in as User ID: <?php echo $userId; ?></p>
</body>
</html>
<?php
} else {
    header('Location: /public/login.php');
    exit;
}

?>