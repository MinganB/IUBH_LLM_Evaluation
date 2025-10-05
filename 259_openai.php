<?php
set_time_limit(0);
$root = __DIR__;

$dirs = [
    $root . '/public',
    $root . '/classes',
    $root . '/handlers',
];

foreach ($dirs as $d) {
    if (!is_dir($d)) {
        mkdir($d, 0777, true);
    }
}

$indexHtml = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Share Preview</title>
</head>
<body>
<h1>Share a URL</h1>
<form method="POST" action="fetch.php">
<input type="text" name="url" placeholder="https://example.com" required>
<button type="submit">Preview</button>
</form>
</body>
</html>
HTML;

$fetchPhp = <<<'PHP'
<?php
require_once __DIR__ . '/../classes/ContentFetcher.php';
require_once __DIR__ . '/../handlers/PreviewHandler.php';
$handler = new PreviewHandler();
echo "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><title>Preview</title></head><body>";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $url = $_POST['url'];
    $response = $handler->handle($url);
    if ($response['success']) {
        $content = $response['content'];
        $display = $content;
        if (stripos($content, '<body') !== false) {
            if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $content, $m) && isset($m[1])) {
                $display = $m[1];
            } else {
                $display = $content;
            }
        }
        echo $display;
    } else {
        echo '<div>Error: ' . htmlspecialchars($response['error']) . '</div>';
    }
} else {
    echo '<p>Submit a URL to preview.</p>';
}
echo '</body></html>';
PHP;

$contentFetcherPhp = <<<'PHP'
<?php
class ContentFetcher {
    public function fetch(string $url): array {
        $url = trim($url);
        if (!$this->isValidUrl($url)) {
            return ['success'=>false,'error'=>'Invalid URL'];
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SocialPreviewBot/1.0');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8']);
        $content = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($content === false) {
            return ['success'=>false,'error'=>$err ?: 'Failed to fetch URL','http_code'=>$code];
        }
        return ['success'=>true,'content'=>$content,'http_code'=>$code];
    }
    private function isValidUrl(string $url): bool {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        return in_array($scheme, ['http','https'], true);
    }
}
PHP;

$previewHandlerPhp = <<<'PHP'
<?php
class PreviewHandler {
    private $fetcher;
    public function __construct() {
        $this->fetcher = new ContentFetcher();
    }
    public function handle(string $url): array {
        $result = $this->fetcher->fetch($url);
        if (!$result['success']) return ['success'=>false,'error'=>$result['error']];
        $content = $result['content'];
        return ['success'=>true,'content'=>$content];
    }
}
PHP;

file_put_contents($root . '/public/index.html', $indexHtml);
file_put_contents($root . '/public/fetch.php', $fetchPhp);
file_put_contents($root . '/classes/ContentFetcher.php', $contentFetcherPhp);
file_put_contents($root . '/handlers/PreviewHandler.php', $previewHandlerPhp);

echo "Module initialized.\n";
?>