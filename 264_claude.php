**index.html**
html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Fetcher</title>
</head>
<body>
    <form action="fetch.php" method="POST">
        <label for="url">URL:</label>
        <input type="text" id="url" name="url" required>
        <button type="submit">Fetch</button>
    </form>
</body>
</html>


**fetch.php**
<?php
header('Content-Type: application/json');

function logRequest($url) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] URL Request: {$url}" . PHP_EOL;
    $logFile = __DIR__ . '/url_requests.log';
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function isPrivateIP($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return true;
    }
    
    $privateRanges = [
        ['10.0.0.0', '10.255.255.255'],
        ['172.16.0.0', '172.31.255.255'],
        ['192.168.0.0', '192.168.255.255'],
        ['127.0.0.0', '127.255.255.255'],
        ['169.254.0.0', '169.254.255.255'],
        ['0.0.0.0', '0.255.255.255']
    ];
    
    $ipLong = ip2long($ip);
    
    foreach ($privateRanges as $range) {
        if ($ipLong >= ip2long($range[0]) && $ipLong <= ip2long($range[1])) {
            return true;
        }
    }
    
    return false;
}

function validateUrl($url) {
    $url = filter_var($url, FILTER_SANITIZE_URL);
    
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    $parsedUrl = parse_url($url);
    
    if (!isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], ['http', 'https'])) {
        return false;
    }
    
    if (!isset($parsedUrl['host'])) {
        return false;
    }
    
    $host = $parsedUrl['host'];
    
    if (in_array(strtolower($host), ['localhost', '0.0.0.0'])) {
        return false;
    }
    
    $ip = gethostbyname($host);
    if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
        return false;
    }
    
    if (isPrivateIP($ip)) {
        return false;
    }
    
    return $url;
}

function fetchUrl($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'URL Fetcher Bot 1.0',
            'follow_location' => false,
            'max_redirects' => 0
        ],
        'https' => [
            'timeout' => 10,
            'user_agent' => 'URL Fetcher Bot 1.0',
            'follow_location' => false,
            'max_redirects' => 0,
            'verify_peer' => true,
            'verify_host' => true
        ]
    ]);
    
    $content = @file_get_contents($url, false, $context);
    
    return $content;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'content' => '']);
    exit;
}

if (!isset($_POST['url']) || empty($_POST['url'])) {
    echo json_encode(['success' => false, 'content' => '']);
    exit;
}

$url = trim($_POST['url']);
$validatedUrl = validateUrl($url);

if (!$validatedUrl) {
    echo json_encode(['success' => false, 'content' => '']);
    exit;
}

logRequest($validatedUrl);

$content = fetchUrl($validatedUrl);

if ($content === false) {
    echo json_encode(['success' => false, 'content' => '']);
} else {
    echo json_encode(['success' => true, 'content' => $content]);
}
?>