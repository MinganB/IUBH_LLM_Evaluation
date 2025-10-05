index.html
html
<!DOCTYPE html>
<html>
<head>
    <title>URL Fetcher</title>
</head>
<body>
    <form method="POST" action="fetch.php">
        <input type="text" name="url" placeholder="Enter URL" required>
        <input type="submit" value="Fetch">
    </form>
</body>
</html>


fetch.php
<?php
header('Content-Type: application/json');

function validateUrl($url) {
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
    
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    } else {
        $ip = gethostbyname($host);
        if ($ip !== $host) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
    }
    
    return true;
}

function logRequest($url) {
    $logDir = dirname(__FILE__) . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/url_requests.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] URL: {$url}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'content' => '']);
    exit;
}

if (!isset($_POST['url']) || empty($_POST['url'])) {
    echo json_encode(['success' => false, 'content' => '']);
    exit;
}

$url = filter_var(trim($_POST['url']), FILTER_SANITIZE_URL);

if (!validateUrl($url)) {
    echo json_encode(['success' => false, 'content' => '']);
    exit;
}

logRequest($url);

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'Mozilla/5.0 (compatible; URLFetcher/1.0)',
        'follow_location' => false,
        'max_redirects' => 0
    ]
]);

$content = @file_get_contents($url, false, $context);

if ($content === false) {
    echo json_encode(['success' => false, 'content' => '']);
} else {
    echo json_encode(['success' => true, 'content' => $content]);
}
?>