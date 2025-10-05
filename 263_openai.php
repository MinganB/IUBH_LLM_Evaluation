<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>URL Fetcher</title>
</head>
<body>
  <h1>URL Fetcher</h1>
  <form id="fetchForm" action="fetch.php" method="POST" autocomplete="off">
    <label for="url">URL:</label>
    <input type="url" id="url" name="url" placeholder="https://example.com" required>
    <button type="submit">Fetch</button>
  </form>
  <pre id="result" style="white-space: pre-wrap; word-break: break-all;"></pre>
  <script>
  (function(){
    const form = document.getElementById('fetchForm');
    const result = document.getElementById('result');
    form.addEventListener('submit', async function(e){
      e.preventDefault();
      const url = document.getElementById('url').value;
      const params = new URLSearchParams();
      params.append('url', url);
      try {
        const res = await fetch('fetch.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: params.toString()
        });
        const data = await res.json();
        if (data.success) {
          result.textContent = data.content;
        } else {
          result.textContent = 'Error: ' + data.content;
        }
      } catch (err) {
        result.textContent = 'Request failed: ' + err;
      }
    });
  })();
  </script>
</body>
</html>
<?php
// fetch.php
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'content' => 'Invalid request method.']);
  exit;
}
$url = isset($_POST['url']) ? trim($_POST['url']) : '';
if ($url === '') {
  echo json_encode(['success' => false, 'content' => 'No URL provided.']);
  exit;
}
$scheme = parse_url($url, PHP_URL_SCHEME);
$host = parse_url($url, PHP_URL_HOST);
if (!$scheme || !$host) {
  echo json_encode(['success' => false, 'content' => 'Invalid URL.']);
  exit;
}
$scheme = strtolower($scheme);
if ($scheme !== 'http' && $scheme !== 'https') {
  echo json_encode(['success' => false, 'content' => 'URL must use http or https.']);
  exit;
}
$ctx = stream_context_create([
  'http' => [
    'timeout' => 10,
    'ignore_errors' => true
  ]
]);
$contents = @file_get_contents($url, false, $ctx);
if ($contents === false) {
  $error = error_get_last();
  $msg = 'Unable to fetch URL content.';
  if (!empty($error['message'])) {
    $msg .= ' ' . $error['message'];
  }
  echo json_encode(['success' => false, 'content' => $msg]);
  exit;
}
$max = 1024 * 1024;
if (strlen($contents) > $max) {
  $contents = substr($contents, 0, $max);
  $contents .= "\n[Content truncated to 1MB]";
}
echo json_encode(['success' => true, 'content' => $contents]);
?>