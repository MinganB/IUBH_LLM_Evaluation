<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Post URL</title>
</head>
<body>
  <form method="post" action="fetch.php">
    <input type="url" name="url" placeholder="https://example.com" required>
    <button type="submit">Fetch</button>
  </form>
</body>
</html>

<?php
$url = $_POST['url'] ?? '';
if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
  http_response_code(400);
  echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invalid URL</title></head><body>';
  echo '<h2>Invalid URL</h2>';
  echo '</body></html>';
  exit;
}
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'SocialApp/1.0');
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($response === false) {
  http_response_code(502);
  echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body>';
  echo '<h2>Failed to fetch URL</h2>';
  echo '<p>Unable to retrieve content from the provided URL.</p>';
  echo '</body></html>';
  exit;
}
if ($httpCode >= 200 && $httpCode < 300) {
  header('Content-Type: text/html; charset=utf-8');
  echo $response;
} else {
  http_response_code($httpCode);
  echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>HTTP Error</title></head><body>';
  echo '<h2>HTTP Error ' . htmlspecialchars((string)$httpCode) . '</h2>';
  echo '</body></html>';
}
?>