<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <title>Social App - Fetch URL</title>
</head>
<body>
  <form action="fetch.php" method="POST">
    <input type="text" name="url" placeholder="Enter URL" required />
    <button type="submit">Fetch</button>
  </form>
</body>
</html>

<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'content' => 'Invalid request method']);
  exit;
}
$url = $_POST['url'] ?? '';
$url = trim($url);
if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED)) {
  echo json_encode(['success' => false, 'content' => 'Invalid URL']);
  exit;
}
$scheme = parse_url($url, PHP_URL_SCHEME);
if ($scheme !== 'http' && $scheme !== 'https') {
  echo json_encode(['success' => false, 'content' => 'Unsupported URL scheme']);
  exit;
}
$context = stream_context_create(['http' => ['header' => 'User-Agent: SocialAppBot/1.0']]);
$content = @file_get_contents($url, false, $context);
if ($content === false) {
  $error = error_get_last()['message'] ?? 'Failed to fetch content';
  echo json_encode(['success' => false, 'content' => $error]);
  exit;
}
echo json_encode(['success' => true, 'content' => $content]);