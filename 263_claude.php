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
    <form method="POST" action="fetch.php">
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
    echo json_encode(['success' => false, 'content' => 'URL is required']);
    exit;
}

$url = trim($_POST['url']);

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'content' => 'Invalid URL format']);
    exit;
}

$parsedUrl = parse_url($url);
if (!isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], ['http', 'https'])) {
    echo json_encode(['success' => false, 'content' => 'Only HTTP and HTTPS URLs are allowed']);
    exit;
}

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'Mozilla/5.0 (compatible; PHP URL Fetcher)',
        'follow_location' => false
    ]
]);

$content = @file_get_contents($url, false, $context);

if ($content === false) {
    echo json_encode(['success' => false, 'content' => 'Failed to fetch URL content']);
} else {
    echo json_encode(['success' => true, 'content' => $content]);
}
?>