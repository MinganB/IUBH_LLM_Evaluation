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

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!isset($_POST['url']) || empty(trim($_POST['url']))) {
    http_response_code(400);
    exit('URL is required');
}

$url = filter_var(trim($_POST['url']), FILTER_VALIDATE_URL);
if (!$url) {
    http_response_code(400);
    exit('Invalid URL format');
}

$parsed_url = parse_url($url);
if (!$parsed_url || !isset($parsed_url['scheme']) || !in_array(strtolower($parsed_url['scheme']), ['http', 'https'])) {
    http_response_code(400);
    exit('Only HTTP and HTTPS URLs are allowed');
}

if (isset($parsed_url['host'])) {
    $host = strtolower($parsed_url['host']);
    $blocked_hosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
    
    if (in_array($host, $blocked_hosts) || 
        preg_match('/^10\./', $host) || 
        preg_match('/^192\.168\./', $host) || 
        preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host)) {
        http_response_code(403);
        exit('Access to local/private networks is forbidden');
    }
}

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 10,
        'max_redirects' => 3,
        'user_agent' => 'Social Media App Fetcher 1.0',
        'ignore_errors' => true
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false
    ]
]);

$content = @file_get_contents($url, false, $context);

if ($content === false) {
    http_response_code(500);
    exit('Failed to fetch content from the provided URL');
}

$content_length = strlen($content);
if ($content_length > 5242880) {
    http_response_code(413);
    exit('Content too large (max 5MB allowed)');
}

$allowed_types = ['text/html', 'text/plain', 'application/json', 'text/xml', 'application/xml'];
$content_type = 'text/plain';

if (isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (stripos($header, 'content-type:') === 0) {
            $header_value = trim(substr($header, 13));
            $content_type = strtok($header_value, ';');
            break;
        }
    }
}

if (!in_array(strtolower($content_type), $allowed_types)) {
    http_response_code(415);
    exit('Unsupported content type');
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fetched Content</title>
</head>
<body>
    <h1>Content from: <?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?></h1>
    <div>
        <strong>Content Type:</strong> <?php echo htmlspecialchars($content_type, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <div>
        <strong>Content Length:</strong> <?php echo number_format($content_length); ?> bytes
    </div>
    <hr>
    <div>
        <?php
        if (strpos($content_type, 'text/html') === 0) {
            echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        } else {
            echo '<pre>' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</pre>';
        }
        ?>
    </div>
    <hr>
    <a href="index.html">Fetch Another URL</a>
</body>
</html>