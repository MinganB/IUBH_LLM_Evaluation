<?php
set_time_limit(30);
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0700, true); }
$logFile = $logDir . '/requests.log';

function log_request($url, $logFile) {
  $ts = date('Y-m-d H:i:s');
  $entry = $ts . " - " . $url;
  @file_put_contents($logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
}
function is_ipv4_private($ip) {
  $long = ip2long($ip);
  if ($long === false) return false;
  return (
    ($long >= ip2long('10.0.0.0') && $long <= ip2long('10.255.255.255')) ||
    ($long >= ip2long('127.0.0.0') && $long <= ip2long('127.255.255.255')) ||
    ($long >= ip2long('172.16.0.0') && $long <= ip2long('172.31.255.255')) ||
    ($long >= ip2long('192.168.0.0') && $long <= ip2long('192.168.255.255')) ||
    ($long >= ip2long('0.0.0.0') && $long <= ip2long('0.255.255.255')) ||
    ($long >= ip2long('169.254.0.0') && $long <= ip2long('169.254.255.255'))
  );
}
function is_private_ip($ip) {
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return is_ipv4_private($ip);
  }
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    $packed = @inet_pton($ip);
    if ($packed === false) return false;
    $first = ord($packed[0]);
    $second = ord($packed[1]);
    if ($ip === '::1' || inet_ntop($packed) === '::1') {
      return true;
    }
    if ($first == 0xFC || $first == 0xFD) return true;
    if ($first == 0xFE && ( ($second & 0xC0) == 0x80 )) return true;
    return false;
  }
  return false;
}
function is_valid_url_and_host($url) {
  if (!is_string($url)) return false;
  $url = trim($url);
  if ($url === '') return false;
  if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
  $parts = parse_url($url);
  if (!$parts || empty($parts['host'])) return false;
  $host = $parts['host'];
  if (strtolower($host) === 'localhost') return false;
  $ips = @gethostbynamel($host);
  if ($ips && count($ips) > 0) {
    foreach ($ips as $ip) {
      if (is_private_ip($ip)) return false;
    }
  } else {
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    if (is_array($records)) {
      foreach ($records as $rec) {
        $addr = isset($rec['ip']) ? $rec['ip'] : (isset($rec['ipv6']) ? $rec['ipv6'] : '');
        if ($addr && is_private_ip($addr)) return false;
      }
    }
  }
  return true;
}
function fetch_content($url) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($ch, CURLOPT_TIMEOUT, 20);
  curl_setopt($ch, CURLOPT_USERAGENT, 'FetchService/1.0');
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
  $content = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($content === false) {
    return ['success' => false, 'error' => $err ?: 'Request failed'];
  }
  return ['success' => true, 'content' => $content, 'code' => $code];
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST' && isset($_POST['target_url'])) {
  $url = trim($_POST['target_url']);
  if (!is_valid_url_and_host($url)) {
    http_response_code(400);
    echo "<!DOCTYPE html><html><head><title>Invalid URL</title></head><body><h2>Invalid or disallowed URL</h2><form method=\"post\" action=\"fetch.php\"><input type=\"text\" name=\"target_url\" placeholder=\"Enter URL\" required><button type=\"submit\">Fetch</button></form></body></html>";
    exit;
  }
  $logURL = $url;
  log_request($logURL, $logFile);
  $result = fetch_content($url);
  if (!$result['success']) {
    http_response_code(502);
    echo "<!DOCTYPE html><html><head><title>Error fetching content</title></head><body><h2>Unable to fetch content</h2><p>Please try again later.</p><form method=\"post\" action=\"fetch.php\"><input type=\"text\" name=\"target_url\" placeholder=\"Enter URL\" required><button type=\"submit\">Fetch</button></form></body></html>";
    exit;
  }
  header('Content-Type: text/html; charset=utf-8');
  echo $result['content'];
  exit;
}
echo "<!DOCTYPE html><html><head><title>URL Fetch</title></head><body>";
echo "<h1>Fetch remote content</h1>";
echo "<form method=\"post\" action=\"fetch.php\">";
echo "<input type=\"text\" name=\"target_url\" placeholder=\"https://example.com\" size=\"60\" required>";
echo "<button type=\"submit\">Fetch</button>";
echo "</form>";
echo "</body></html>";
?>