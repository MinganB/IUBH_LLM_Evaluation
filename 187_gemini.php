<?php
// session_handler.php

session_start();

$_SESSION['user_id'] = 123;

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Session started and user ID set.'
]);
exit();
?>

<?php
// dashboard.php

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
    <p>You are logged in as user ID: <?php echo $userId; ?></p>
</body>
</html>
<?php
} else {
    header('Location: login.php');
    exit();
}
?>