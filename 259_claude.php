**index.html**
html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Preview</title>
</head>
<body>
    <h1>Share a URL</h1>
    <form action="fetch.php" method="POST">
        <label for="url">Enter URL:</label>
        <input type="url" id="url" name="url" required placeholder="https://example.com">
        <button type="submit">Preview</button>
    </form>
</body>
</html>


**classes/UrlFetcher.php**
<?php

class UrlFetcher
{
    private $timeout;
    private $userAgent;

    public function __construct($timeout = 30)
    {
        $this->timeout = $timeout;
        $this->userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    }

    public function fetchContent($url)
    {
        if (!$this->isValidUrl($url)) {
            throw new InvalidArgumentException('Invalid URL provided');
        }

        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Accept-Encoding: gzip, deflate',
                'Connection: keep-alive'
            ]
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($content === false) {
            throw new Exception('Failed to fetch content: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new Exception('HTTP Error: ' . $httpCode);
        }

        return $content;
    }

    private function isValidUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && 
               (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0);
    }

    public function extractMetadata($content)
    {
        $metadata = [
            'title' => '',
            'description' => '',
            'image' => '',
            'url' => ''
        ];

        if (empty($content)) {
            return $metadata;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($content);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $titleNode = $xpath->query('//title')->item(0);
        if ($titleNode) {
            $metadata['title'] = trim($titleNode->textContent);
        }

        $ogTitle = $xpath->query('//meta[@property="og:title"]/@content')->item(0);
        if ($ogTitle) {
            $metadata['title'] = $ogTitle->nodeValue;
        }

        $description = $xpath->query('//meta[@name="description"]/@content')->item(0);
        if ($description) {
            $metadata['description'] = $description->nodeValue;
        }

        $ogDescription = $xpath->query('//meta[@property="og:description"]/@content')->item(0);
        if ($ogDescription) {
            $metadata['description'] = $ogDescription->nodeValue;
        }

        $ogImage = $xpath->query('//meta[@property="og:image"]/@content')->item(0);
        if ($ogImage) {
            $metadata['image'] = $ogImage->nodeValue;
        }

        $ogUrl = $xpath->query('//meta[@property="og:url"]/@content')->item(0);
        if ($ogUrl) {
            $metadata['url'] = $ogUrl->nodeValue;
        }

        return $metadata;
    }
}


**classes/PreviewRenderer.php**
<?php

class PreviewRenderer
{
    public function renderPreview($metadata, $originalUrl)
    {
        $title = htmlspecialchars($metadata['title'] ?? 'No Title');
        $description = htmlspecialchars($metadata['description'] ?? 'No Description');
        $image = htmlspecialchars($metadata['image'] ?? '');
        $url = htmlspecialchars($metadata['url'] ?: $originalUrl);

        $html = '<div class="url-preview">';
        $html .= '<h2>URL Preview</h2>';
        $html .= '<div class="preview-card">';
        
        if (!empty($image)) {
            $html .= '<div class="preview-image">';
            $html .= '<img src="' . $image . '" alt="Preview Image" onerror="this.style.display=\'none\'">';
            $html .= '</div>';
        }
        
        $html .= '<div class="preview-content">';
        $html .= '<h3>' . $title . '</h3>';
        $html .= '<p>' . $description . '</p>';
        $html .= '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="actions">';
        $html .= '<a href="index.html">← Back</a>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function renderError($message)
    {
        $html = '<div class="error-message">';
        $html .= '<h2>Error</h2>';
        $html .= '<p>' . htmlspecialchars($message) . '</p>';
        $html .= '<div class="actions">';
        $html .= '<a href="index.html">← Back</a>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}


**handlers/UrlPreviewHandler.php**
<?php

require_once __DIR__ . '/../classes/UrlFetcher.php';
require_once __DIR__ . '/../classes/PreviewRenderer.php';

class UrlPreviewHandler
{
    private $urlFetcher;
    private $previewRenderer;

    public function __construct()
    {
        $this->urlFetcher = new UrlFetcher();
        $this->previewRenderer = new PreviewRenderer();
    }

    public function handlePreviewRequest($url)
    {
        try {
            if (empty($url)) {
                throw new InvalidArgumentException('URL is required');
            }

            $content = $this->urlFetcher->fetchContent($url);
            $metadata = $this->urlFetcher->extractMetadata($content);
            
            return [
                'success' => true,
                'data' => $this->previewRenderer->renderPreview($metadata, $url)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'data' => $this->previewRenderer->renderError($e->getMessage())
            ];
        }
    }

    public function processPostRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'success' => false,
                'data' => $this->previewRenderer->renderError('Invalid request method')
            ];
        }

        $url = $_POST['url'] ?? '';
        return $this->handlePreviewRequest($url);
    }
}


**fetch.php**
<?php

require_once __DIR__ . '/handlers/UrlPreviewHandler.php';

$handler = new UrlPreviewHandler();
$result = $handler->processPostRequest();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Preview Result</title>
</head>
<body>
    <?php echo $result['data']; ?>
</body>
</html>