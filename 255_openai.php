<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>URL Fetcher</title>
</head>
<body>
  <form method="post" action="fetch.php" autocomplete="off">
    <label for="target_url">URL:</label>
    <input type="text" id="target_url" name="target_url" required placeholder="https://example.com" />
    <button type="submit">Fetch</button>
  </form>
</body>
</html>

<?php
$logDir = __DIR__ . '/logs';
$logFile = $logDir . '/requests.log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0700, true);
}
function log_request($logFile, $entry) {
    $line = date('Y-m-d H:i:s') . " - " . $entry . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
$raw = isset($_POST['target_url']) ? $_POST['target_url'] : '';
$sanitized = '';
$error = '';
if (is_string($raw)) {
    $sanitized = filter_var(trim($raw), FILTER_SANITIZE_URL);
}
if (!$sanitized || !filter_var($sanitized, FILTER_VALIDATE_URL)) {
    $error = 'Invalid URL';
} else {
    $parts = parse_url($sanitized);
    if (empty($parts['scheme']) || empty($parts['host'])) {
        $error = 'Invalid URL';
    } else {
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            $error = 'Unsupported protocol';
        } else {
            $host = $parts['host'];
            $lowerHost = strtolower($host);
            if ($lowerHost === 'localhost') {
                $error = 'Internal host not allowed';
            } else {
                $ips = [];
                $resolved = @gethostbynamel($host);
                if (is_array($resolved)) {
                    $ips = array_merge($ips, $resolved);
                }
                if (empty($ips)) {
                    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
                    if (is_array($records)) {
                        foreach ($records as $rec) {
                            if (isset($rec['ip'])) $ips[] = $rec['ip'];
                            if (isset($rec['ipv6'])) $ips[] = $rec['ipv6'];
                        }
                    }
                }
                $blocked = false;
                foreach ($ips as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $packed = ip2long($ip);
                        if ($packed === false) continue;
                        $ranges = [
                            ['start' => ip2long('10.0.0.0'), 'end' => ip2long('10.255.255.255')],
                            ['start' => ip2long('172.16.0.0'), 'end' => ip2long('172.31.255.255')],
                            ['start' => ip2long('192.168.0.0'), 'end' => ip2long('192.168.255.255')],
                            ['start' => ip2long('127.0.0.0'), 'end' => ip2long('127.255.255.255')],
                        ];
                        foreach ($ranges as $r) {
                            if ($packed >= $r['start'] && $packed <= $r['end']) {
                                $blocked = true;
                                break 2;
                            }
                        }
                    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        $packed = @inet_pton($ip);
                        if ($packed !== false) {
                            if ($ip === '::1') {
                                $blocked = true;
                                break;
                            }
                            $first = ord($packed[0]);
                            $second = ord($packed[1]);
                            if (($first & 0xFE) == 0xFC) {
                                $blocked = true;
                                break;
                            }
                            if ($first == 0xFE && (($second & 0xC0) == 0x80)) {
                                $blocked = true;
                                break;
                            }
                        }
                    }
                }
                if ($blocked) {
                    $error = 'Access to private networks is blocked';
                }
            }
        }
    }
}
log_request($logFile, 'URL: ' . $sanitized);
if ($error) {
    echo '<!DOCTYPE html><html><head><title>Error</title></head><body><h2>Error</h2><p>' . htmlspecialchars($error) . '</p></body></html>';
    exit;
}
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $sanitized);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_USERAGENT, 'URLFetchModule/1.0');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);
$curl_err = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    echo '<!DOCTYPE html><html><head><title>Error</title></head><body><h2>Error</h2><p>Failed to fetch the URL.</p></body></html>';
    exit;
}
header('Content-Type: text/html; charset=utf-8');
echo $response;
?>