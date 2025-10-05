<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Social App - Fetch URL</title>
</head>
<body>
<form method="post" action="fetch.php">
  <input type="text" name="url" placeholder="Enter URL (http:// or https://)" required />
  <button type="submit">Fetch</button>
</form>
</body>
</html>
<?php
// fetch.php

header('Content-Type: application/json; charset=utf-8');

function isPrivateIp($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        if (count($parts) >= 2) {
            $first = intval($parts[0]);
            $second = intval($parts[1]);
            if ($first === 10) return true;
            if ($first === 127) return true;
            if ($first === 172 && $second >= 16 && $second <= 31) return true;
            if ($first === 192 && $second === 168) return true;
        }
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $packed = inet_pton($ip);
        if ($packed !== false) {
            if ($ip === '::1') return true;
            $first = ord($packed[0]);
            if ($first === 0xFC || $first === 0xFD) return true;
        }
    }
    return false;
}

function logRequest($url, $path) {
    $line = date('Y-m-d H:i:s') . " - " . $url . PHP_EOL;
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0770, true);
}
$logPath = $logDir . '/url_requests.log';

$rawUrl = isset($_POST['url']) ? $_POST['url'] : '';
$rawUrl = trim($rawUrl);
$sanitizedUrl = filter_var($rawUrl, FILTER_SANITIZE_URL);

logRequest($sanitizedUrl, $logPath);

$response = ['success' => false, 'content' => ''];

if (empty($sanitizedUrl) || !filter_var($sanitizedUrl, FILTER_VALIDATE_URL)) {
    echo json_encode($response);
    exit;
}

$scheme = parse_url($sanitizedUrl, PHP_URL_SCHEME);
$host = parse_url($sanitizedUrl, PHP_URL_HOST);
if (!in_array(strtolower($scheme), ['http', 'https'], true) || empty($host)) {
    echo json_encode($response);
    exit;
}

$privateDetected = false;
$recordsA = dns_get_record($host, DNS_A);
if (is_array($recordsA)) {
    foreach ($recordsA as $rec) {
        if (isset($rec['ip']) && isPrivateIp($rec['ip'])) {
            $privateDetected = true;
            break;
        }
    }
}
if (!$privateDetected) {
    $recordsAaaa = dns_get_record($host, DNS_AAAA);
    if (is_array($recordsAaaa)) {
        foreach ($recordsAaaa as $rec) {
            if (isset($rec['ipv6']) && isPrivateIp($rec['ipv6'])) {
                $privateDetected = true;
                break;
            }
        }
    }
}

if ($privateDetected) {
    $response['content'] = 'Access to private/internal IP addresses is blocked';
    echo json_encode($response);
    exit;
}

$ctx = stream_context_create([
    'http' => [
        'timeout' => 10,
        'header'  => "User-Agent: SocialAppFetcher/1.0\r\n"
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true
    ]
]);

$content = @file_get_contents($sanitizedUrl, false, $ctx);
if ($content === false) {
    echo json_encode($response);
    exit;
}

$response['success'] = true;
$response['content'] = $content;
echo json_encode($response);
exit;
?>