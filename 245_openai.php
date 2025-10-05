<?php
ini_set('display_errors', '0');
set_time_limit(0);

function is_private_ip($ip){
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) return false;
  $long = ip2long($ip);
  if ($long >= ip2long('127.0.0.0') && $long <= ip2long('127.255.255.255')) return true;
  if ($long >= ip2long('10.0.0.0') && $long <= ip2long('10.255.255.255')) return true;
  if ($long >= ip2long('172.16.0.0') && $long <= ip2long('172.31.255.255')) return true;
  if ($long >= ip2long('192.168.0.0') && $long <= ip2long('192.168.255.255')) return true;
  if ($long >= ip2long('169.254.0.0') && $long <= ip2long('169.254.255.255')) return true;
  return false;
}

function is_valid_url($url){
  if (!is_string($url)) return false;
  $url = filter_var($url, FILTER_SANITIZE_URL);
  if (!$url) return false;
  $parts = parse_url($url);
  if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return false;
  $scheme = strtolower($parts['scheme']);
  if ($scheme !== 'http' && $scheme !== 'https') return false;
  $host = $parts['host'];
  if (preg_match('/\s/', $host)) return false;
  $ips = @dns_get_record($host, DNS_A);
  $ipv4s = [];
  if (is_array($ips)) {
    foreach ($ips as $r){
      if (isset($r['ip'])) $ipv4s[] = $r['ip'];
    }
  }
  foreach ($ipv4s as $ip){
    if (is_private_ip($ip)) return false;
  }
  if (empty($ipv4s)){
    $ip = gethostbyname($host);
    if ($ip && $ip !== $host && is_private_ip($ip)) return false;
  }
  return true;
}

$url = '';
if (isset($_GET['url'])) $url = trim($_GET['url']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) $url = trim($_POST['url']);

if (!is_valid_url($url)) {
  echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Remote URL Fetcher</title></head><body>';
  echo '<form method="get" action="">';
  echo '<input type="text" name="url" placeholder="https://example.com" style="width:60%">';
  echo '<button type="submit">Fetch</button>';
  echo '</form>';
  echo '</body></html>';
  exit;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_USERAGENT, 'RemoteContentProxy/1.0');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: */*']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_ENCODING, '');

$result = curl_exec($ch);
$err = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

if ($result === false) {
  http_response_code(502);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'Error fetching URL: '.$err;
  exit;
}

$headerSize = isset($info['header_size']) ? (int)$info['header_size'] : 0;
$header = substr($result, 0, $headerSize);
$body = substr($result, $headerSize);

$ctype = isset($info['content_type']) ? $info['content_type'] : 'text/html; charset=UTF-8';
header('Content-Type: '.$ctype);
echo $body;
exit;
?>