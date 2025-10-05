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
        <label for="url">Enter URL:</label>
        <input type="url" id="url" name="url" required>
        <button type="submit">Fetch Content</button>
    </form>
</body>
</html>


**fetch.php**
<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!isset($_POST['url']) || empty(trim($_POST['url']))) {
    http_response_code(400);
    exit('URL is required');
}

$url = trim($_POST['url']);

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    exit('Invalid URL format');
}

$parsed_url = parse_url($url);
if (!isset($parsed_url['scheme']) || !in_array(strtolower($parsed_url['scheme']), ['http', 'https'])) {
    http_response_code(400);
    exit('Only HTTP and HTTPS URLs are allowed');
}

if (isset($parsed_url['host'])) {
    $host = strtolower($parsed_url['host']);
    $blocked_hosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
    
    if (in_array($host, $blocked_hosts) || 
        strpos($host, '192.168.') === 0 || 
        strpos($host, '10.') === 0 || 
        preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $host)) {
        http_response_code(400);
        exit('Access to local/private networks is not allowed');
    }
}

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 10,
        'user_agent' => 'PHP URL Fetcher 1.0',
        'follow_location' => true,
        'max_redirects' => 3
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true
    ]
]);

$content = @file_get_contents($url, false, $context);

if ($content === false) {
    http_response_code(500);
    exit('Failed to fetch content from the provided URL');
}

$content_length = strlen($content);
if ($content_length > 1048576) {
    http_response_code(413);
    exit('Content too large (max 1MB allowed)');
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

echo '<!DOCTYPE html>';
echo '<html lang="en">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>Fetched Content</title>';
echo '</head>';
echo '<body>';
echo '<h1>Content from: ' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</h1>';
echo '<hr>';
echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
echo '<hr>';
echo '<a href="index.html">Fetch Another URL</a>';
echo '</body>';
echo '</html>';
?>