index.html:
html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Preview</title>
</head>
<body>
    <form action="fetch.php" method="POST">
        <label for="url">Enter URL:</label>
        <input type="text" id="url" name="url" required>
        <button type="submit">Fetch Content</button>
    </form>
</body>
</html>


classes/UrlValidator.php:
<?php

class UrlValidator
{
    private $allowedSchemes = ['http', 'https'];
    private $blockedDomains = ['localhost', '127.0.0.1', '0.0.0.0'];
    
    public function validate($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        $parsedUrl = parse_url($url);
        
        if (!isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], $this->allowedSchemes)) {
            return false;
        }
        
        if (!isset($parsedUrl['host'])) {
            return false;
        }
        
        $host = strtolower($parsedUrl['host']);
        
        if (in_array($host, $this->blockedDomains)) {
            return false;
        }
        
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        } else {
            $ip = gethostbyname($host);
            if ($ip === $host) {
                return false;
            }
        }
        
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        
        return true;
    }
}
?>


classes/ContentFetcher.php:
<?php

class ContentFetcher
{
    private $timeout = 10;
    private $maxSize = 1048576;
    private $allowedContentTypes = [
        'text/html',
        'application/xhtml+xml',
        'text/plain'
    ];
    
    public function fetch($url)
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Social Media Preview Bot/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => [$this, 'headerCallback'],
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => [$this, 'progressCallback']
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($content === false || !empty($error)) {
            throw new Exception('Failed to fetch content: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('HTTP error: ' . $httpCode);
        }
        
        if (!$this->isAllowedContentType($contentType)) {
            throw new Exception('Content type not allowed');
        }
        
        return $content;
    }
    
    private function headerCallback($ch, $header)
    {
        if (stripos($header, 'Content-Length:') === 0) {
            $length = (int) substr($header, 16);
            if ($length > $this->maxSize) {
                return -1;
            }
        }
        return strlen($header);
    }
    
    private function progressCallback($resource, $downloadSize, $downloaded, $uploadSize, $uploaded)
    {
        if ($downloaded > $this->maxSize) {
            return 1;
        }
        return 0;
    }
    
    private function isAllowedContentType($contentType)
    {
        $mainType = strtok($contentType, ';');
        return in_array(trim($mainType), $this->allowedContentTypes);
    }
}
?>


classes/ContentSanitizer.php:
<?php

class ContentSanitizer
{
    private $allowedTags = [
        'p', 'br', 'strong', 'em', 'u', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'div', 'span', 'img'
    ];
    
    private $allowedAttributes = [
        'img' => ['src', 'alt', 'width', 'height'],
        'div' => ['class'],
        'span' => ['class']
    ];
    
    public function sanitize($content)
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        
        $dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $this->removeScripts($dom);
        $this->sanitizeTags($dom);
        $this->sanitizeAttributes($dom);
        
        $sanitized = $dom->saveHTML();
        
        $sanitized = preg_replace('/<?xml encoding="UTF-8">/', '', $sanitized);
        
        return $sanitized;
    }
    
    private function removeScripts($dom)
    {
        $scripts = $dom->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $scripts->item(0)->parentNode->removeChild($scripts->item(0));
        }
        
        $styles = $dom->getElementsByTagName('style');
        while ($styles->length > 0) {
            $styles->item(0)->parentNode->removeChild($styles->item(0));
        }
        
        $links = $dom->getElementsByTagName('link');
        for ($i = $links->length - 1; $i >= 0; $i--) {
            $link = $links->item($i);
            if ($link->getAttribute('rel') === 'stylesheet') {
                $link->parentNode->removeChild($link);
            }
        }
    }
    
    private function sanitizeTags($dom)
    {
        $xpath = new DOMXPath($dom);
        $allElements = $xpath->query('//*');
        
        $elementsToRemove = [];
        
        foreach ($allElements as $element) {
            if (!in_array(strtolower($element->tagName), $this->allowedTags)) {
                $elementsToRemove[] = $element;
            }
        }
        
        foreach ($elementsToRemove as $element) {
            if ($element->parentNode) {
                $element->parentNode->removeChild($element);
            }
        }
    }
    
    private function sanitizeAttributes($dom)
    {
        $xpath = new DOMXPath($dom);
        $allElements = $xpath->query('//*[@*]');
        
        foreach ($allElements as $element) {
            $tagName = strtolower($element->tagName);
            $allowedAttrs = isset($this->allowedAttributes[$tagName]) ? $this->allowedAttributes[$tagName] : [];
            
            $attributesToRemove = [];
            
            foreach ($element->attributes as $attr) {
                $attrName = strtolower($attr->name);
                
                if (!in_array($attrName, $allowedAttrs)) {
                    $attributesToRemove[] = $attrName;
                } elseif ($attrName === 'src') {
                    $value = $attr->value;
                    if (!filter_var($value, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//', $value)) {
                        $attributesToRemove[] = $attrName;
                    }
                }
            }
            
            foreach ($attributesToRemove as $attrName) {
                $element->removeAttribute($attrName);
            }
        }
    }
}
?>


handlers/UrlPreviewHandler.php:
<?php

require_once '../classes/UrlValidator.php';
require_once '../classes/ContentFetcher.php';
require_once '../classes/ContentSanitizer.php';

class UrlPreviewHandler
{
    private $validator;
    private $fetcher;
    private $sanitizer;
    
    public function __construct()
    {
        $this->validator = new UrlValidator();
        $this->fetcher = new ContentFetcher();
        $this->sanitizer = new ContentSanitizer();
    }
    
    public function handleRequest($url)
    {
        try {
            if (empty($url)) {
                throw new Exception('URL is required');
            }
            
            $url = filter_var(trim($url), FILTER_SANITIZE_URL);
            
            if (!$this->validator->validate($url)) {
                throw new Exception('Invalid or unsafe URL');
            }
            
            $content = $this->fetcher->fetch($url);
            
            $sanitizedContent = $this->sanitizer->sanitize($content);
            
            return [
                'success' => true,
                'content' => $sanitizedContent,
                'url' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
            ];
        }
    }
}
?>


fetch.php:
<?php

session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'handlers/UrlPreviewHandler.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<h1>Method Not Allowed</h1>';
    exit;
}

$url = $_POST['url'] ?? '';

$handler = new UrlPreviewHandler();
$result = $handler->handleRequest($url);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Preview Result</title>
</head>
<body>
    <div>
        <a href="index.html">← Back to form</a>
    </div>
    
    <?php if ($result['success']): ?>
        <h1>Preview of: <?php echo $result['url']; ?></h1>
        <div>
            <?php echo $result['content']; ?>
        </div>
    <?php else: ?>
        <h1>Error</h1>
        <p><?php echo $result['error']; ?></p>
    <?php endif; ?>
</body>
</html>