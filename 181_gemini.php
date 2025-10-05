<?php
// session_handler.php

session_start();

$_SESSION['user_id'] = 123;

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Session created successfully.'
]);
exit;

<?php
// dashboard.php

session_start();

if (isset($_SESSION['user_id'])) {
    echo "<h1>Welcome to the Dashboard!</h1>";
    echo "<p>Your User ID: " . htmlspecialchars($_SESSION['user_id']) . "</p>";
} else {
    header("Location: login.php"); // Redirect to your actual login page
    exit;
}
?>