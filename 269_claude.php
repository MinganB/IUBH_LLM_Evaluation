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
        <input type="text" name="url" placeholder="Enter URL" required>
        <input type="submit" value="Fetch Content">
    </form>
</body>
</html>


**fetch.php**
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'content' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['url']) || empty(trim($_POST['url']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'content' => 'URL parameter is required']);
    exit;
}

$url = trim($_POST['url']);

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'content' => 'Invalid URL format']);
    exit;
}

$parsed_url = parse_url($url);
if (!isset($parsed_url['scheme']) || !in_array(strtolower($parsed_url['scheme']), ['http', 'https'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'content' => 'Only HTTP and HTTPS URLs are allowed']);
    exit;
}

if (isset($parsed_url['host'])) {
    $host = strtolower($parsed_url['host']);
    $blocked_hosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
    
    if (in_array($host, $blocked_hosts) || 
        preg_match('/^192\.168\./', $host) || 
        preg_match('/^10\./', $host) || 
        preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'content' => 'Access to local/private networks is not allowed']);
        exit;
    }
}

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'Social Media App/1.0',
        'follow_location' => 1,
        'max_redirects' => 3,
        'ignore_errors' => true
    ],
    'https' => [
        'timeout' => 10,
        'user_agent' => 'Social Media App/1.0',
        'follow_location' => 1,
        'max_redirects' => 3,
        'ignore_errors' => true,
        'verify_peer' => true,
        'verify_peer_name' => true
    ]
]);

$content = @file_get_contents($url, false, $context);

if ($content === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'content' => 'Failed to fetch URL content']);
    exit;
}

$max_content_length = 1048576;
if (strlen($content) > $max_content_length) {
    $content = substr($content, 0, $max_content_length);
}

$content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

echo json_encode(['success' => true, 'content' => $content]);
?>