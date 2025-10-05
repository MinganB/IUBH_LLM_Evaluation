<?php
declare(strict_types=1);
header('Content-Type: application/json');

$root = __DIR__;
spl_autoload_register(function($class) use ($root) {
    foreach ([$root . '/classes/', $root . '/handlers/'] as $dir) {
        $file = $dir . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'content' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['url'])) {
    echo json_encode(['success' => false, 'content' => 'URL parameter is missing']);
    exit;
}

$url = trim($_POST['url']);
if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'content' => 'Invalid URL']);
    exit;
}

$scheme = parse_url($url, PHP_URL_SCHEME);
if (!in_array($scheme, ['http', 'https'], true)) {
    echo json_encode(['success' => false, 'content' => 'Unsupported URL scheme']);
    exit;
}

if (ini_get('allow_url_fopen') != '1' && strtolower((string)ini_get('allow_url_fopen')) != 'on') {
    echo json_encode(['success' => false, 'content' => 'URL fopen is disabled on this server']);
    exit;
}

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'header' => "User-Agent: SocialPreviewBot/1.0 (+https://example.com)\r\n"
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true
    ]
]);

$maxBytes = 1024 * 1024;
$content = '';
$bytes = 0;

$handle = @fopen($url, 'rb', false, $context);
if ($handle === false) {
    $err = error_get_last();
    $message = $err['message'] ?? 'Failed to open URL';
    echo json_encode(['success' => false, 'content' => $message]);
    exit;
}

while (!feof($handle) && $bytes < $maxBytes) {
    $chunk = fread($handle, 8192);
    if ($chunk === false) {
        break;
    }
    $content .= $chunk;
    $bytes += strlen($chunk);
}
fclose($handle);

if ($bytes >= $maxBytes) {
    $content .= "\n<!-- content truncated -->";
}

echo json_encode(['success' => true, 'content' => $content]);
?>