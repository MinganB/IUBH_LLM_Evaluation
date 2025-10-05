<!DOCTYPE html>
<html>
<head>
  <title>Share Preview</title>
</head>
<body>
  <form method="POST" action="/handlers/fetch.php" autocomplete="off" novalidate>
    <label for="url">URL</label>
    <input type="text" id="url" name="url" placeholder="https://example.com" required />
    <button type="submit">Preview</button>
  </form>
</body>
</html>

<?php
require_once __DIR__ . '/../classes/UrlValidator.php';
require_once __DIR__ . '/../classes/HttpFetcher.php';
require_once __DIR__ . '/../classes/Logger.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Method Not Allowed';
  exit;
}
$rawUrl = isset($_POST['url']) ? $_POST['url'] : '';
$url = UrlValidator::sanitizeAndValidate($rawUrl);
if (!$url) {
  http_response_code(400);
  echo '<p>Invalid URL. Please provide a valid http(s) URL.</p>';
  exit;
}
Logger::log($url);
$fetcher = new HttpFetcher();
$result = $fetcher->fetch($url, 6);
if (!$result['success']) {
  http_response_code(502);
  echo '<p>Could not fetch content from the provided URL.</p>';
  exit;
}
echo $result['body'];
?>

<?php
class UrlValidator {
  public static function sanitizeAndValidate($url) {
    if (!is_string($url)) return false;
    $url = trim($url);
    $url = filter_var($url, FILTER_SANITIZE_URL);
    if ($url === false || $url === '') return false;
    if (!filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) return false;
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return false;
    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http','https'])) return false;
    $host = $parts['host'];
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
      if (self::isPrivateIP($host)) return false;
    }
    return $url;
  }

  private static function isPrivateIP($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      $long = ip2long($ip);
      if ($long === false) return true;
      $privateRanges = [
        ['start' => ip2long('10.0.0.0'), 'mask' => ip2long('255.0.0.0')],
        ['start' => ip2long('172.16.0.0'), 'mask' => ip2long('255.240.0.0')],
        ['start' => ip2long('192.168.0.0'), 'mask' => ip2long('255.255.0.0')],
        ['start' => ip2long('127.0.0.0'), 'mask' => ip2long('255.0.0.0')],
        ['start' => ip2long('169.254.0.0'), 'mask' => ip2long('255.255.0.0')],
      ];
      foreach ($privateRanges as $range) {
        if (($long & $range['mask']) === $range['start']) {
          return true;
        }
      }
      return false;
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
      $packed = inet_pton($ip);
      if ($packed === false) return true;
      $first2 = unpack('n', substr($packed, 0, 2))[1];
      if (($first2 & 0xffc0) === 0xfe80) return true;
      if (($first2 & 0xff80) === 0xfc00 || (($first2 & 0xff80) === 0xfd00)) return true;
      $loopback = str_repeat("\0", 15) . "\x01";
      if ($packed === $loopback) return true;
      return false;
    }
    return true;
  }
}
?>

<?php
class HttpFetcher {
  public function fetch($url, $timeout = 5) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, max(1, (int)$timeout));
    curl_setopt($ch, CURLOPT_TIMEOUT, max(2, (int)$timeout));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'SocialPreviewBot/1.0');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false) {
      return ['success' => false, 'error' => 'Unable to fetch content'];
    }
    if ($code >= 400) {
      return ['success' => false, 'error' => 'HTTP error code ' . $code];
    }
    return ['success' => true, 'body' => $response];
  }
}
?>

<?php
class Logger {
  private static $logFile = __DIR__ . '/../logs/url_requests.log';
  public static function log($url) {
    $dir = dirname(self::$logFile);
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $url . PHP_EOL;
    file_put_contents(self::$logFile, $entry, FILE_APPEND | LOCK_EX);
  }
}
?>