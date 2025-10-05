<?php
session_start();
$_SESSION['user_id'] = 123;
setcookie('PHPSESSID', session_id(), 0, '/');
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Login successful']);
exit;
?><?php
session_start();
if (isset($_SESSION['user_id'])) {
  echo 'Welcome, user ' . htmlspecialchars($_SESSION['user_id'], ENT_QUOTES, 'UTF-8');
} else {
  header('Location: login.php');
  exit;
}
?>