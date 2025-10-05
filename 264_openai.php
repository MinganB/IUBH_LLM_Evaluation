<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>URL Fetcher</title>
</head>
<body>
  <form action="fetch.php" method="post">
    <label for="url">URL:</label>
    <input type="text" id="url" name="url" placeholder="https://example.com" required>
    <button type="submit">Fetch</button>
  </form>
</body>
</html>
<?php
// fetch.php
header('Content-Type: application/json');

function isPrivateIP($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $addr = ip2long($ip);
        if ($addr === false) return false;
        if ($addr >= ip2long('10.0.0.0') && $addr <= ip2long('10.255.255.255')) return true;
        if ($addr >= ip2long('172.16.0.0') && $addr <= ip2long('172.31.255.255')) return true;
        if ($addr >= ip2long('192.168.0.0') && $addr <= ip2long('192.168.255.255')) return true;
        if ($addr >= ip2long('127.0.0.0') && $addr <= ip2long('127.255.255.255')) return true;
        if ($addr >= ip2long('169.254.0.0') && $addr <= ip2long('169.254.255.255')) return true;
        return false;
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        if ($ip === '::1') return true;
        $packed = @inet_pton($ip);
        if ($packed === false) return false;
        if ((ord($packed[0]) & 0xFE) === 0xFC) return true;
        if (stripos($ip, 'fe80') === 0) return true;
        return false;
    }
    return false;
}

$LOG_PRIMARY = '/var/log/url_fetch.log';
$logTarget = $LOG_PRIMARY;
$logDir = dirname($logTarget);
if (!is_writable($logDir)) {
    $logTarget = __DIR__ . '/url_fetch.log';
}
function logRequest($url, $logFile) {
    $line = date('Y-m-d H:i:s') . " - " . $url . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

$urlRaw = isset($_POST['url']) ? trim($_POST['url']) : '';
if ($urlRaw === '') {
    echo json_encode(['success' => false, 'content' => 'No URL provided']);
    exit;
}

$parsed = parse_url($urlRaw);
if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
    echo json_encode(['success' => false, 'content' => 'Invalid URL format']);
    exit;
}
$scheme = strtolower($parsed['scheme']);
if ($scheme !== 'http' && $scheme !== 'https') {
    echo json_encode(['success' => false, 'content' => 'Unsupported URL scheme']);
    exit;
}
$host = $parsed['host'];
$host = filter_var($host, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

$ips = [];
if (function_exists('gethostbynamel')) {
    $names = @gethostbynamel($host);
    if (is_array($names)) {
        foreach ($names as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ips[] = $ip;
            }
        }
    }
}
if (empty($ips) && function_exists('dns_get_record')) {
    $records = @dns_get_record($host, DNS_AAAA);
    if (is_array($records)) {
        foreach ($records as $rec) {
            if (isset($rec['ipv6'])) $ips[] = $rec['ipv6'];
            elseif (isset($rec['ipv6addr'])) $ips[] = $rec['ipv6addr'];
        }
    }
}

foreach ($ips as $ip) {
    if (isPrivateIP($ip)) {
        echo json_encode(['success' => false, 'content' => 'Access to private/internal hosts is blocked']);
        exit;
    }
}

logRequest($urlRaw, $logTarget);

$timeout = 10;
$context = stream_context_create([
    'http' => [
        'timeout' => $timeout,
        'header' => "User-Agent: URLFetchModule/1.0\r\n"
    ],
    'https' => [
        'timeout' => $timeout,
        'header' => "User-Agent: URLFetchModule/1.0\r\n"
    ]
]);

$content = @file_get_contents($urlRaw, false, $context);

if ($content === false) {
    echo json_encode(['success' => false, 'content' => 'Failed to retrieve content']);
    exit;
}

echo json_encode(['success' => true, 'content' => $content]);
exit;