<?php
class RemotePreview {
    private $url;
    private $timeout;
    private $userAgent;

    public function __construct($url, $timeout = 15, $userAgent = null) {
        $this->url = $url;
        $this->timeout = $timeout;
        $this->userAgent = $userAgent ?: 'Mozilla/5.0 (RealtimePreview/1.0 PHP)';
    }

    public function fetch() {
        $url = $this->normalizeUrl($this->url);
        if (!$url) {
            return ['success' => false, 'error' => 'Invalid URL'];
        }
        $html = $this->fetchContent($url);
        if ($html === false) {
            return ['success' => false, 'error' => 'Failed to fetch URL'];
        }
        $meta = $this->extractMetadata($html, $url);
        $meta['contentSnippet'] = $this->getContentSnippet($html);
        return array_merge(['success' => true, 'url' => $url], $meta);
    }

    private function normalizeUrl($url) {
        if (empty($url)) return null;
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'http://' . $url;
        }
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function fetchContent($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $content = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($content === false || $code >= 400) {
            return false;
        }
        return $content;
    }

    private function extractMetadata($html, $baseUrl) {
        $title = null;
        $description = null;
        $image = null;
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//meta[@property='og:title']");
        if ($nodes->length > 0) $title = $nodes->item(0)->getAttribute('content');
        if (!$title) {
            $nodes = $xpath->query("//title");
            if ($nodes->length > 0) $title = trim($nodes->item(0)->nodeValue);
        }
        $nodes = $xpath->query("//meta[@property='og:description']");
        if ($nodes->length > 0) $description = $nodes->item(0)->getAttribute('content');
        if (!$description) {
            $nodes = $xpath->query("//meta[@name='description']");
            if ($nodes->length > 0) $description = $nodes->item(0)->getAttribute('content');
        }
        $nodes = $xpath->query("//meta[@property='og:image']");
        if ($nodes->length > 0) $image = $nodes->item(0)->getAttribute('content');
        if (!$image) {
            $nodes = $xpath->query("//img[@src][1]");
            if ($nodes->length > 0) {
                $img = $nodes->item(0)->getAttribute('src');
                $image = $this->resolveUrl($img, $baseUrl);
            }
        }
        if ($image) $image = $this->resolveUrl($image, $baseUrl);
        if ($description && mb_strlen($description) > 300) {
            $description = mb_substr($description, 0, 297) . '...';
        }
        libxml_clear_errors();
        return ['title' => $title, 'description' => $description, 'image' => $image];
    }

    private function resolveUrl($src, $base) {
        if (!$src) return null;
        if (preg_match('#^https?://#i', $src)) return $src;
        if (strpos($src, '//') === 0) return 'http:' . $src;
        $baseParts = parse_url($base);
        $scheme = isset($baseParts['scheme']) ? $baseParts['scheme'] : 'http';
        $host = isset($baseParts['host']) ? $baseParts['host'] : '';
        $basePath = isset($baseParts['path']) ? $baseParts['path'] : '/';
        $dir = rtrim(dirname($basePath), '/');
        if (strpos($src, '/') === 0) {
            $path = $src;
        } else {
            $path = $dir . '/' . $src;
        }
        $segments = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') {
                array_pop($segments);
            } else {
                $segments[] = $seg;
            }
        }
        $normalized = '/' . implode('/', $segments);
        return $scheme . '://' . $host . $normalized;
    }

    private function getContentSnippet($html) {
        $text = strip_tags($html);
        $text = preg_replace('/\s+/u', ' ', $text);
        return mb_substr($text, 0, 250);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!isset($_POST['url'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing url']);
        exit;
    }
    $url = trim($_POST['url']);
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid URL']);
        exit;
    }
    $preview = new RemotePreview($url);
    $result = $preview->fetch();
    echo json_encode($result);
    exit;
}
$endpoint = $_SERVER['PHP_SELF'];
?>
<!DOCTYPE html>
<html>
<head>
<title>Real-Time Page Preview</title>
</head>
<body>
<form id="previewForm" onsubmit="return false;">
  <input type="url" id="urlInput" placeholder="Enter URL to preview" autocomplete="off" />
  <button type="button" id="startPreviewBtn">Preview</button>
</form>
<div id="previewArea"></div>
<script>
async function fetchPreview(url){
  if (!url) return;
  const formData = new FormData();
  formData.append('url', url);
  const res = await fetch('<?php echo htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8'); ?>', {method:'POST', body: formData});
  const data = await res.json();
  if (!data || data.success === false){
     document.getElementById('previewArea').innerHTML = '<p>Error: ' + (data?.error || 'Unable to fetch preview') + '</p>';
     return;
  }
  let html = '';
  if (data.image) html += '<img src="' + escapeHtml(data.image) + '" alt="">';
  html += '<h2>' + escapeHtml(data.title || data.url) + '</h2>';
  if (data.description) html += '<p>' + escapeHtml(data.description) + '</p>';
  if (data.contentSnippet) html += '<p>' + escapeHtml(data.contentSnippet) + '</p>';
  document.getElementById('previewArea').innerHTML = html;
}
function escapeHtml(s){
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
document.getElementById('startPreviewBtn').addEventListener('click', function(){ fetchPreview(document.getElementById('urlInput').value); });
document.getElementById('urlInput').addEventListener('keydown', function(e){
  if (e.key === 'Enter') fetchPreview(this.value);
});
</script>
</body>
</html>
?>