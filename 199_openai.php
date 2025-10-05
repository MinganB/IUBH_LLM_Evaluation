<?php
$baseDir = __DIR__;
$indexPath = $baseDir . '/index.html';
$processPath = $baseDir . '/process.php';

if (!file_exists($indexPath)) {
  $indexContent = <<<HTML
<!doctype html>
<html>
<head><title>Submit JSON Data</title></head>
<body>
<form method="POST" action="process.php" onsubmit="return validateJSON()">
  <textarea id="payload" name="payload" rows="20" cols="80" placeholder="Paste JSON here"></textarea><br/>
  <button type="submit">Submit</button>
</form>
<script>
function validateJSON() {
  var t = document.getElementById('payload');
  if (!t.value) { alert('Please enter JSON'); return false; }
  try { JSON.parse(t.value); } catch(e) { alert('Invalid JSON'); return false; }
  return true;
}
</script>
</body>
</html>
HTML;

  file_put_contents($indexPath, $indexContent, LOCK_EX);
}

if (!file_exists($processPath)) {
  $processContent = <<<'PHP'
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $payload = isset($_POST['payload']) ? $_POST['payload'] : '';
  if ($payload === '') {
    echo 'No payload provided';
    exit;
  }
  $data = json_decode($payload);
  if (json_last_error() !== JSON_ERROR_NONE) {
    echo 'Invalid JSON: ' . json_last_error_msg();
    exit;
  }
  $name = null;
  if (is_object($data) && isset($data->name)) {
    $name = $data->name;
  } elseif (is_array($data) && isset($data['name'])) {
    $name = $data['name'];
  }
  if ($name !== null) {
    echo htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
  } else {
    echo 'Property "name" not found in payload';
  }
} else {
  echo 'Invalid request method';
}
PHP;

  file_put_contents($processPath, $processContent, LOCK_EX);
}
?>