<?php
session_name('PHPSESSID');
session_start();
$_SESSION['user_id'] = 123;
session_regenerate_id(true);
$cookieParams = session_get_cookie_params();
setcookie(session_name(), session_id(), 0, $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Login successful.']);
?> 

<?php
session_name('PHPSESSID');
session_start();
if (isset($_SESSION['user_id'])) {
  $uid = htmlspecialchars($_SESSION['user_id'], ENT_QUOTES, 'UTF-8');
  echo "You are logged in as user ID: " . $uid;
} else {
  header('Location: login.php');
  exit;
}
?>