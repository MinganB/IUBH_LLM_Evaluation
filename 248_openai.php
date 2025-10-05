<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
$MAX_CONTENT_SIZE = 2 * 1024 * 1024;
$ALLOWED_HOSTS = [];

if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$error = '';
$content = '';
$url = '';

function is_valid_url($url) {
  if (empty($url)) return false;
  if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
  $parts = parse_url($url);
  if (!isset($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http','https'], true)) return false;
  if (!isset($parts['host'])) return false;
  return true;
}
function host_in_allowlist($host, $allowed) {
  if (empty($allowed)) return true;
  foreach ($allowed as $h) {
    if (strcasecmp($host, $h) === 0) return true;
  }
  return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $url = isset($_POST['url']) ? trim($_POST['url']) : '';
  $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
  if (!$token || $token !== $_SESSION['csrf_token']) {
    $error = 'Invalid CSRF token';
  } else if (!is_valid_url($url)) {
    $error = 'Invalid URL';
  } else {
    $host = parse_url($url, PHP_URL_HOST);
    if (!host_in_allowlist($host, $ALLOWED_HOSTS)) {
      $error = 'Host not allowed';
    } else {
      if (!function_exists('curl_version')) {
        $error = 'cURL not available';
      } else {
        $headCh = curl_init();
        curl_setopt($headCh, CURLOPT_URL, $url);
        curl_setopt($headCh, CURLOPT_NOBODY, true);
        curl_setopt($headCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($headCh, CURLOPT_HEADER, true);
        curl_setopt($headCh, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($headCh, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($headCh, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($headCh, CURLOPT_TIMEOUT, 15);
        curl_setopt($headCh, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($headCh, CURLOPT_USERAGENT, 'SocialApp/1.0');
        $headers = curl_exec($headCh);
        $httpCode = curl_getinfo($headCh, CURLINFO_HTTP_CODE);
        curl_close($headCh);
        $contentType = '';
        $contentLength = 0;
        if ($headers) {
          foreach (explode("\r\n", $headers) as $line) {
            if (stripos($line, 'Content-Type:') === 0) {
              $contentType = trim(substr($line, 13));
            } elseif (stripos($line, 'Content-Length:') === 0) {
              $val = trim(substr($line, 15));
              if ($val !== '' && is_numeric($val)) $contentLength = (int)$val;
            }
          }
        }
        if ($httpCode >= 200 && $httpCode < 400) {
          if ($contentLength > 0 && $contentLength > $MAX_CONTENT_SIZE) {
            $error = 'Content size exceeds limit';
          } else {
            $getCh = curl_init();
            curl_setopt($getCh, CURLOPT_URL, $url);
            curl_setopt($getCh, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($getCh, CURLOPT_HEADER, false);
            curl_setopt($getCh, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($getCh, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($getCh, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($getCh, CURLOPT_TIMEOUT, 25);
            curl_setopt($getCh, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($getCh, CURLOPT_USERAGENT, 'SocialApp/1.0');
            $data = curl_exec($getCh);
            $errNo = curl_errno($getCh);
            $errMsg = curl_error($getCh);
            curl_close($getCh);
            if ($errNo) {
              $error = 'Fetch error: '.$errMsg;
            } else {
              if ($data === false) {
                $error = 'Failed to fetch content';
              } else {
                if (strlen($data) > $MAX_CONTENT_SIZE) {
                  $error = 'Content size exceeds limit';
                } else {
                  $content = $data;
                }
              }
            }
          }
        } else {
          $error = 'HTTP error: '.$httpCode;
        }
      }
    }
  }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Remote Content Fetch</title>
</head>
<body>
<form method="post" action="">
<input type="text" name="url" placeholder="https://example.com" value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
<button type="submit">Fetch</button>
</form>

<?php if ($error): ?>
<p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php if ($content): ?>
<h3>Fetched Content</h3>
<pre><?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?></pre>
<?php endif; ?>

</body>
</html>
?>