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
        <button type="submit">Fetch Content</button>
    </form>
</body>
</html>


**fetch.php**
<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'content' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['url']) || empty(trim($_POST['url']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'content' => 'URL is required']);
    exit;
}

$url = trim($_POST['url']);

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'content' => 'Invalid URL format']);
    exit;
}

$parsed_url = parse_url($url);
if (!in_array($parsed_url['scheme'], ['http', 'https'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'content' => 'Only HTTP and HTTPS protocols are allowed']);
    exit;
}

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
    http_response_code(500);
    echo json_encode(['success' => false, 'content' => 'Failed to fetch URL content']);
    exit;
}

if (strlen($content) > 1048576) {
    http_response_code(413);
    echo json_encode(['success' => false, 'content' => 'Content too large']);
    exit;
}

echo json_encode(['success' => true, 'content' => $content]);
?>