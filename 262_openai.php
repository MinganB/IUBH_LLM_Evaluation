<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>URL Fetcher</title>
</head>
<body>
  <form method="post" action="fetch.php">
    <input type="text" name="url" placeholder="Enter URL to fetch" required />
    <button type="submit">Fetch</button>
  </form>
</body>
</html>
<?php
header('Content-Type: application/json; charset=utf-8');
$url = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $url = trim($_POST['url']);
}
if ($url === '') {
    echo json_encode(['success' => false, 'content' => 'No URL provided'], JSON_UNESCAPED_UNICODE);
    exit;
}
$scheme = parse_url($url, PHP_URL_SCHEME);
if ($scheme !== 'http' && $scheme !== 'https') {
    echo json_encode(['success' => false, 'content' => 'Invalid URL scheme'], JSON_UNESCAPED_UNICODE);
    exit;
}
$context = stream_context_create([
    'http' => [
        'timeout' => 30,
        'header' => 'User-Agent: PHP Fetcher'
    ],
    'https' => [
        'timeout' => 30,
        'header' => 'User-Agent: PHP Fetcher'
    ]
]);
$content = @file_get_contents($url, false, $context);
if ($content === false) {
    $error = error_get_last();
    $errMsg = $error['message'] ?? 'Failed to fetch content';
    echo json_encode(['success' => false, 'content' => $errMsg], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['success' => true, 'content' => $content], JSON_UNESCAPED_UNICODE);
?>