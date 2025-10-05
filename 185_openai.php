<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
$_SESSION['user_id'] = 123;
session_regenerate_id(true);
$cookieParams = session_get_cookie_params();
setcookie(session_name(), session_id(), time() + 3600, $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
echo json_encode(['success' => true, 'message' => 'Login successful.']);
?> 

<?php
session_start();
if (isset($_SESSION['user_id'])) {
  $userId = htmlspecialchars($_SESSION['user_id'], ENT_QUOTES, 'UTF-8');
  echo "Welcome, user ID: {$userId}";
  exit;
} else {
  header('Location: login.php');
  exit;
}
?>