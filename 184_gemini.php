<?php
session_start();
$_SESSION['user_id'] = 123;
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Login successful']);
exit;
?>
<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome to your Dashboard!</h1>
    <p>You are logged in as User ID: ' . htmlspecialchars($userId) . '</p>
</body>
</html>';
} else {
    header('Location: /login.php');
    exit;
}
?>