<?php
declare(strict_types=1);

define('LOG_FILE', __DIR__ . '/logs/processing.log');
define('INDEX_HTML_PATH', __DIR__ . '/index.html');

class PayloadContainer {
    public $requiredProperty;
    public function __construct($value = null) {
        $this->requiredProperty = $value;
    }
}

function ensure_log_dir(): void {
    $dir = dirname(LOG_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
}

function log_event(string $source, string $level, string $message): void {
    $ts = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $line = "[$ts] [{$level}] SOURCE: {$source} - {$message}\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function safe_unserialize(string $str) {
    try {
        $result = @unserialize($str, ["allowed_classes" => [PayloadContainer::class]]);
        if ($result === false && $str !== 'b:0;' && $str !== 'N;') {
            return false;
        }
        return $result;
    } catch (Throwable $e) {
        return false;
    }
}

function ensure_index_html(): void {
    if (!file_exists(INDEX_HTML_PATH)) {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Serialized Data Processor</title></head>
<body>
<h1>Serialized Data Processor</h1>
<form id="dataForm" onsubmit="event.preventDefault(); submitData();">
  <label for="serialized">Serialized Data (PHP serialized string):</label><br/>
  <textarea id="serialized" rows="8" cols="80" placeholder="Paste PHP serialized string here"></textarea><br/>
  <button type="submit">Submit</button>
</form>
<div id="result"></div>
<script>
async function submitData(){
  const text = document.getElementById('serialized').value.trim();
  const payload = JSON.stringify(text);
  try {
     const resp = await fetch('process.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: payload
     });
     const t = await resp.text();
     document.getElementById('result').innerText = t;
  } catch (e){
     document.getElementById('result').innerText = 'Error processing data';
  }
}
</script>
</body>
</html>
HTML;
        file_put_contents(INDEX_HTML_PATH, $html, LOCK_EX);
    }
}

ensure_log_dir();
$sourceIp = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
ensure_index_html();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: index.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$logSource = $sourceIp . ' | ' . $ua;
$raw = trim(file_get_contents('php://input'));

if (empty($raw)) {
    log_event($logSource, 'WARN', 'Empty request body');
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($ct, 'application/json') !== 0) {
    log_event($logSource, 'WARN', 'Unsupported content type');
    http_response_code(400);
    echo 'Invalid content type';
    exit;
}

$decoded = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    log_event($logSource, 'WARN', 'Invalid JSON');
    http_response_code(400);
    echo 'Invalid payload';
    exit;
}

$serialized = null;
if (is_string($decoded)) {
    $serialized = $decoded;
} elseif (is_array($decoded)) {
    if (isset($decoded['serialized'])) {
        $serialized = $decoded['serialized'];
    } elseif (isset($decoded['payload'])) {
        $serialized = $decoded['payload'];
    } else {
        if (count($decoded) === 1) {
            $first = array_values($decoded)[0];
            if (is_string($first)) {
                $serialized = $first;
            }
        }
    }
}

if (!is_string($serialized) || $serialized === '') {
    log_event($logSource, 'WARN', 'Serialized string not found or invalid');
    http_response_code(400);
    echo 'Invalid payload';
    exit;
}

$deserialized = safe_unserialize($serialized);
if ($deserialized === false) {
    log_event($logSource, 'ERROR', 'Deserialization failed');
    http_response_code(400);
    echo 'Invalid data';
    exit;
}

$value = null;
if (is_object($deserialized)) {
    if (property_exists($deserialized, 'requiredProperty')) {
        $value = $deserialized->requiredProperty;
    }
} elseif (is_array($deserialized)) {
    if (isset($deserialized['requiredProperty'])) {
        $value = $deserialized['requiredProperty'];
    }
}

if (is_null($value)) {
    log_event($logSource, 'WARN', 'Required property not found in deserialized data');
    http_response_code(400);
    echo 'Invalid data';
    exit;
}

$display = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
echo "Property value: {$display}";
?>