<?php
ini_set('display_errors', '0');
header('Content-Type: application/json');

$logPath = __DIR__ . '/../logs/requests.log';
$logDir = dirname($logPath);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
function is_private_ipv4($ip) {
    $l = ip2long($ip);
    if ($l === false) return false;
    $ranges = [
        ['start'=>ip2long('10.0.0.0'), 'end'=>ip2long('10.255.255.255')],
        ['start'=>ip2long('172.16.0.0'), 'end'=>ip2long('172.31.255.255')],
        ['start'=>ip2long('192.168.0.0'), 'end'=>ip2long('192.168.255.255')],
        ['start'=>ip2long('127.0.0.0'), 'end'=>ip2long('127.255.255.255')]
    ];
    foreach ($ranges as $r) {
        if ($l >= $r['start'] && $l <= $r['end']) return true;
    }
    return false;
}
function logEvent($status, $message, $logPath) {
    $line = date('Y-m-d H:i:s') . " - " . $status . " - " . $message . PHP_EOL;
    file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'content' => null]);
    exit;
}
$urlRaw = isset($_POST['url']) ? $_POST['url'] : '';
$urlRaw = is_string($urlRaw) ? trim($urlRaw) : '';
if ($urlRaw === '') {
    logEvent('REQUEST_FAIL', 'Empty URL', $logPath);
    echo json_encode(['success' => false, 'content' => null]);
    exit;
}
$sanitizedUrl = filter_var($urlRaw, FILTER_SANITIZE_URL);
if (filter_var($sanitizedUrl, FILTER_VALIDATE_URL) === false) {
    logEvent('REQUEST_FAIL', 'Invalid URL format', $logPath);
    echo json_encode(['success' => false, 'content' => null]);
    exit;
}
$parts = parse_url($sanitizedUrl);
if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
    logEvent('REQUEST_FAIL', 'Invalid URL structure', $logPath);
    echo json_encode(['success' => false, 'content' => null]);
    exit;
}
$scheme = strtolower($parts['scheme']);
if (!in_array($scheme, ['http', 'https'], true)) {
    logEvent('REQUEST_FAIL', 'Unsupported URL scheme', $logPath);
    echo json_encode(['success' => false, 'content' => null]);
    exit;
}
$host = $parts['host'];
$hostLower = strtolower($host);
if ($hostLower === 'localhost' || $hostLower === '127.0.0.1' || $hostLower === '::1') {
    logEvent('REQUEST_FAIL', 'Disallowed host', $logPath);
    echo json_encode(['success' => false, 'content' => null]);
    exit;
}
$blocked = false;
if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    if (is_private_ipv4($host)) {
        $blocked = true;
    }
} else {
    $resolved = gethostbyname($host);
    if ($resolved && $resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if (is_private_ipv4($resolved)) {
            $blocked = true;
        }
    }
}
if ($blocked) {
    logEvent('REQUEST_FAIL', 'Resolved to private IP', $logPath);
    echo json_encode(['success' => false, 'content' => null]);
    exit;
}
$timeout = 5;
$context = stream_context_create([
    'http' => [
        'timeout' => $timeout,
        'header'  => "User-Agent: SocialPreview/1.0\r\nAccept: */*\r\n"
    ],
    'https' => [
        'timeout' => $timeout,
        'verify_peer' => true,
        'verify_peer_name' => true
    ]
]);
$data = @file_get_contents($sanitizedUrl, false, $context);
if ($data === false) {
    logEvent('REQUEST_FAIL', 'Failed to fetch URL', $logPath);
    echo json_encode(['success' => false, 'content' => null]);
    exit;
}
logEvent('REQUEST_SUCCESS', 'Fetched URL: ' . $sanitizedUrl, $logPath);
echo json_encode(['success' => true, 'content' => $data]);
exit;
?>