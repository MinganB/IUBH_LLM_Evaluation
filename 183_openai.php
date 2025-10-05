<?php
$LOG_FILE = __DIR__ . '/session.log';
$SESSION_TIMEOUT = 1800;

if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
} else {
  session_set_cookie_params(0, '/');
}
ini_set('session.use_strict_mode', '1');
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');

session_start();

function logEvent($message, $userId = null) {
  $logFile = __DIR__ . '/session.log';
  $ts = date('Y-m-d H:i:s');
  $uid = ($userId !== null) ? (string)$userId : 'unknown';
  $line = "[$ts] user_id=$uid - {$message}\n";
  if (is_writable($logFile)) {
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
  } else {
    @touch($logFile);
    if (is_writable($logFile)) {
      file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
  }
}

function secureDestroySession() {
  $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
  logEvent('Session destroyed', $uid);
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
  }
  if (function_exists('session_destroy')) {
    session_destroy();
  }
}

function enforceTimeout() {
  global $SESSION_TIMEOUT;
  if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
    return;
  }
  if (time() - $_SESSION['last_activity'] > $SESSION_TIMEOUT) {
    $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    logEvent('Session timeout', $uid);
    secureDestroySession();
    header('Location: login.php');
    exit;
  }
  $_SESSION['last_activity'] = time();
}

logEvent('Session started', isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);

$_SESSION['user_id'] = 123;
$_SESSION['last_activity'] = time();

session_regenerate_id(true);
logEvent('User login', $_SESSION['user_id']);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Login successful']);
exit;
?> 
<?php
$LOG_FILE = __DIR__ . '/session.log';
$SESSION_TIMEOUT = 1800;

if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
} else {
  session_set_cookie_params(0, '/');
}
ini_set('session.use_strict_mode', '1');
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');

session_start();

function logEvent($message, $userId = null) {
  $logFile = __DIR__ . '/session.log';
  $ts = date('Y-m-d H:i:s');
  $uid = ($userId !== null) ? (string)$userId : 'unknown';
  $line = "[$ts] user_id=$uid - {$message}\n";
  if (is_writable($logFile)) {
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
  } else {
    @touch($logFile);
    if (is_writable($logFile)) {
      file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
  }
}

function secureDestroySession() {
  $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
  logEvent('Session destroyed', $uid);
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
  }
  if (function_exists('session_destroy')) {
    session_destroy();
  }
}

function enforceTimeout() {
  global $SESSION_TIMEOUT;
  if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
    return;
  }
  if (time() - $_SESSION['last_activity'] > $SESSION_TIMEOUT) {
    $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    logEvent('Session timeout', $uid);
    secureDestroySession();
    header('Location: login.php');
    exit;
  }
  $_SESSION['last_activity'] = time();
}

if (!empty($_GET['action']) && $_GET['action'] === 'logout') {
  $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
  logEvent('Logout', $uid);
  secureDestroySession();
  header('Location: login.php');
  exit;
}

enforceTimeout();

if (isset($_SESSION['user_id'])) {
  $uid = $_SESSION['user_id'];
  echo "<html><body>Welcome, user {$uid}. <a href=\"dashboard.php?action=logout\">Logout</a></body></html>";
  logEvent('Dashboard accessed', $uid);
} else {
  header('Location: login.php');
  exit;
}
?>