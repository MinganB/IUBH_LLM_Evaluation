<?php

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_lifetime', 0);

define('LOG_FILE', __DIR__ . '/session_events.log');
define('SESSION_TIMEOUT_SECONDS', 1800);

function logSessionEvent($userId, $eventMessage) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = sprintf("[%s] User ID: %s - %s\n", $timestamp, $userId, $eventMessage);
    error_log($logEntry, 3, LOG_FILE);
}

session_start();

session_regenerate_id(true);

$userId = 123;
$_SESSION['user_id'] = $userId;
$_SESSION['CREATED'] = time();

logSessionEvent($userId, "Session created and user logged in.");

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Login successful. Redirecting to dashboard...'
]);
exit();
?>

<?php

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_lifetime', 0);

define('LOG_FILE', __DIR__ . '/session_events.log');
define('SESSION_TIMEOUT_SECONDS', 1800);
define('LOGIN_PAGE', 'session_handler.php');

function logSessionEvent($userId, $eventMessage) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = sprintf("[%s] User ID: %s - %s\n", $timestamp, $userId, $eventMessage);
    error_log($logEntry, 3, LOG_FILE);
}

function destroySessionAndRedirect($userId, $reason) {
    logSessionEvent($userId, "Session terminated: " . $reason);

    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();

    header('Location: ' . LOGIN_PAGE);
    exit();
}

session_start();

$currentUserId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : "N/A";

if (isset($_SESSION['CREATED']) && (time() - $_SESSION['CREATED'] > SESSION_TIMEOUT_SECONDS)) {
    destroySessionAndRedirect($currentUserId, "Absolute timeout reached");
}

if (!isset($_SESSION['user_id'])) {
    destroySessionAndRedirect($currentUserId, "Unauthorized access (user_id not set)");
}

$userId = $_SESSION['user_id'];
logSessionEvent($userId, "Dashboard accessed.");

if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    destroySessionAndRedirect($userId, "User initiated logout");
}
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
    <p>Your User ID is: <?php echo htmlspecialchars($userId); ?></p>
    <p>This session will expire after <?php echo SESSION_TIMEOUT_SECONDS / 60; ?> minutes of absolute time.</p>
    <p><a href="?logout=true">Logout</a></p>
</body>
</html>
<?php exit(); ?>