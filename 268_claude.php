<?php
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
        <button type="submit">Fetch Content</button>
    </form>
</body>
</html>


<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'content' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['url']) || empty($_POST['url'])) {
    echo json_encode(['success' => false, 'content' => 'URL parameter is required']);
    exit;
}

$url = $_POST['url'];

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'content' => 'Invalid URL format']);
    exit;
}

$context = stream_context_create([
    'http' => [
        'timeout' => 30,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]
]);

$content = @file_get_contents($url, false, $context);

if ($content === false) {
    echo json_encode(['success' => false, 'content' => 'Failed to fetch URL content']);
} else {
    echo json_encode(['success' => true, 'content' => $content]);
}
?>