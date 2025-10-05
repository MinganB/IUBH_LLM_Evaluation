<?php
$base = __DIR__;
$indexPath = $base . DIRECTORY_SEPARATOR . 'index.html';
$processPath = $base . DIRECTORY_SEPARATOR . 'process.php';

if (!file_exists($indexPath)) {
    $indexContent = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
  <title>Submit JSON Data</title>
</head>
<body>
  <form method="POST" action="process.php" autocomplete="off">
    <textarea name="payload" rows="12" cols="60" placeholder="{\"name\": \"Alice\"}"></textarea>
    <br>
    <button type="submit">Submit</button>
  </form>
</body>
</html>
HTML;
    file_put_contents($indexPath, $indexContent, LOCK_EX);
    chmod($indexPath, 0644);
}

if (!file_exists($processPath)) {
    $processContent = <<<'PHP'
<?php
$payload = $_POST["payload"] ?? null;
if (is_null($payload)) {
  $raw = trim(file_get_contents("php://input"));
  if ($raw !== "" && is_string($raw)) {
    $payload = $raw;
  }
}
if (empty($payload)) {
  echo "No payload provided.";
  exit;
}
$data = json_decode($payload);
if (json_last_error() !== JSON_ERROR_NONE) {
  echo "Invalid JSON payload.";
  exit;
}
$prop = null;
if (is_object($data) && property_exists($data, "name")) {
  $prop = $data->name;
} elseif (is_array($data) && array_key_exists("name", $data)) {
  $prop = $data["name"];
}
if (is_null($prop)) {
  echo "Property \"name\" not found.";
  exit;
}
$prop = (string)$prop;
echo htmlspecialchars($prop, ENT_QUOTES, "UTF-8");
PHP;
    file_put_contents($processPath, $processContent, LOCK_EX);
    chmod($processPath, 0644);
}
?>