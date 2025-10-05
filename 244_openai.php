<?php
$enteredUrl = '';
$errors = [];
$resultHTML = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['url'])) {
  $url = isset($_POST['url']) ? $_POST['url'] : (isset($_GET['url']) ? $_GET['url'] : '');
  $enteredUrl = $url;
  if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    $errors[] = 'Please provide a valid URL.';
  } else {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'RemoteFetch/1.0');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($response === false) {
      $errors[] = 'Unable to retrieve the content from the URL.';
    } else if ($httpCode < 200 || $httpCode >= 400) {
      $errors[] = 'Remote server returned an error: '.$httpCode;
    } else {
      $isHTML = false;
      if ($contentType) {
        $ct = strtolower($contentType);
        if (strpos($ct, 'text/html') !== false || strpos($ct, 'application/xhtml+xml') !== false) {
          $isHTML = true;
        }
      }
      if ($isHTML) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($response, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
          $toRemoveScript = [];
          foreach ($body->getElementsByTagName('script') as $node) $toRemoveScript[] = $node;
          foreach ($toRemoveScript as $node) if ($node->parentNode) $node->parentNode->removeChild($node);
          $toRemoveStyle = [];
          foreach ($body->getElementsByTagName('style') as $node) $toRemoveStyle[] = $node;
          foreach ($toRemoveStyle as $node) if ($node->parentNode) $node->parentNode->removeChild($node);
          $inner = '';
          foreach ($body->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
          }
          $resultHTML = $inner;
        } else {
          $resultHTML = $response;
        }
        libxml_clear_errors();
        $resultHTML = $resultHTML ?? '';
      } else {
        $resultHTML = '<p>Content type: '.htmlspecialchars($contentType).'.</p>';
        if (strpos($contentType, 'image/') === 0) {
          $resultHTML .= '<img src="'.htmlspecialchars($url).'" alt="Remote image" />';
        } else {
          $resultHTML .= '<iframe src="'.htmlspecialchars($url).'" width="100%" height="600" frameborder="0"></iframe>';
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Remote URL Fetcher</title>
</head>
<body>
<h1>Remote URL Fetcher</h1>
<form method="post" action="">
  <input type="url" name="url" placeholder="https://example.com" required value="<?php echo htmlspecialchars($enteredUrl ?? ''); ?>">
  <button type="submit">Fetch</button>
</form>
<?php if (!empty($errors)) { ?>
  <div>
  <?php foreach ($errors as $e) { echo htmlspecialchars($e).'<br>'; } ?>
  </div>
<?php } ?>
<?php if (isset($resultHTML)) { ?>
  <div id="remote-content">
    <?php echo $resultHTML; ?>
  </div>
<?php } ?>
</body>
</html>
?>