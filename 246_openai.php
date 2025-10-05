<?php
date_default_timezone_set('UTC');

define('LOG_DIR', __DIR__ . '/logs');
define('LOG_FILE', LOG_DIR . '/url_requests.log');

function ensureLogDir() {
    if (!is_dir(LOG_DIR)) {
        @mkdir(LOG_DIR, 0770, true);
    }
}

function isPrivateIp($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            $a = intval($parts[0] ?? 0);
            $b = intval($parts[1] ?? 0);
            if ($a === 10) return true;
            if ($a === 127) return true;
            if ($a === 172 && $b >= 16 && $b <= 31) return true;
            if ($a === 192 && $b === 168) return true;
        }
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $ip6 = strtolower($ip);
        if ($ip6 === '::1') return true;
        if (strpos($ip6, 'fe80') === 0) return true;
        if (strpos($ip6, 'fc') === 0 || strpos($ip6, 'fd') === 0) return true;
    }
    return false;
}

function maskCredentialsInUrl($url) {
    $parts = parse_url($url);
    if (!$parts) return $url;
    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = isset($parts['path']) ? $parts['path'] : '';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    return $scheme . $host . $port . $path . $query;
}

function logRequest($urlForLog, $status, $message = '') {
    $logFile = LOG_FILE;
    $line = date('Y-m-d H:i:s') . " | " . $status . " | " . $urlForLog;
    if ($message !== '') {
        $line .= " | " . $message;
    }
    $line .= PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function dnsGetIPs($host) {
    $ips = [];
    $records = @dns_get_record($host, DNS_A);
    if (is_array($records)) {
        foreach ($records as $rec) {
            if (isset($rec['ip'])) $ips[] = $rec['ip'];
        }
    }
    $records6 = @dns_get_record($host, DNS_AAAA);
    if (is_array($records6)) {
        foreach ($records6 as $rec) {
            if (isset($rec['ipv6'])) $ips[] = $rec['ipv6'];
        }
    }
    if (empty($ips)) {
        $resolved = @gethostbynamel($host);
        if (is_array($resolved)) {
            foreach ($resolved as $ip) {
                $ips[] = $ip;
            }
        }
    }
    $ips = array_values(array_unique($ips, SORT_STRING));
    return $ips;
}

function validateUrl($url) {
    if (!is_string($url) || trim($url) === '') return false;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $parts = parse_url($url);
    if (!$parts) return false;
    $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
    if (!in_array($scheme, ['http', 'https'], true)) return false;
    if (empty($parts['host'])) return false;
    $host = $parts['host'];

    $ips = dnsGetIPs($host);
    if (empty($ips)) {
        $resolved = @gethostbynamel($host);
        if (is_array($resolved)) {
            foreach ($resolved as $ip) {
                $ips[] = $ip;
            }
        }
    }
    if (empty($ips)) {
        $ip = @gethostbyname($host);
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) $ips[] = $ip;
    }
    $ips = array_values(array_unique($ips, SORT_STRING));

    foreach ($ips as $ip) {
        if (isPrivateIp($ip)) return false;
    }
    return $url;
}

function fetchContent($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: SecureRemoteFetch/1.0']);
    $content = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($content === false) {
        return ['success' => false, 'http_code' => $code ?? 0, 'error' => $err ?? 'request_failed'];
    }
    return ['success' => true, 'content' => $content, 'http_code' => $code];
}

function buildLogSafeUrl($url) {
    $parts = parse_url($url);
    if (!$parts) return $url;
    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = isset($parts['path']) ? $parts['path'] : '';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    return $scheme . $host . $port . $path . $query;
}

ensureLogDir();

$displayContent = null;
$errorMessage = '';
$processedUrlForDisplay = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawUrl = isset($_POST['url']) ? $_POST['url'] : '';
    $sanitized = trim($rawUrl);
    $sanitized = filter_var($sanitized, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $validated = validateUrl($sanitized);
    if ($validated) {
        $processedUrlForDisplay = $validated;
        $logUrl = buildLogSafeUrl($validated);
        logRequest(maskCredentialsInUrl($logUrl), 'START');
        $result = fetchContent($validated);
        if ($result['success']) {
            $displayContent = $result['content'];
            $httpCode = $result['http_code'];
            logRequest(maskCredentialsInUrl($logUrl), 'FINISH', 'HTTP_CODE=' . $httpCode);
        } else {
            logRequest(maskCredentialsInUrl($logUrl), 'FINISH', 'ERROR:' . ($result['error'] ?? 'unknown'));
            $errorMessage = 'Failed to fetch content.';
        }
    } else {
        $errorMessage = 'Invalid URL or blocked by policy.';
    }
}
?>
<!doctype html>
<html>
<head>
    <title>Remote URL Fetcher</title>
</head>
<body>
    <h1>Remote URL Fetcher</h1>
    <?php if ($errorMessage && empty($displayContent)): ?>
        <p><?php echo htmlspecialchars($errorMessage); ?></p>
    <?php endif; ?>
    <form method="post" action="">
        <label for="url">URL:</label>
        <input type="text" id="url" name="url" size="80" value="<?php echo htmlspecialchars($processedUrlForDisplay); ?>">
        <button type="submit">Fetch</button>
    </form>
    <?php if ($displayContent !== null): ?>
        <h2>Fetched Content</h2>
        <div>
            <?php echo $displayContent; ?>
        </div>
    <?php endif; ?>
</body>
</html>
?>