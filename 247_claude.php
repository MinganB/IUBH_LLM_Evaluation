<?php

class RemoteContentFetcher
{
    private $timeout;
    private $maxRedirects;
    private $userAgent;
    private $allowedDomains;
    private $blockedDomains;
    private $maxContentLength;
    
    public function __construct()
    {
        $this->timeout = 10;
        $this->maxRedirects = 3;
        $this->userAgent = 'SocialMediaApp/1.0';
        $this->allowedDomains = [];
        $this->blockedDomains = [
            'localhost',
            '127.0.0.1',
            '0.0.0.0',
            '10.',
            '172.',
            '192.168.'
        ];
        $this->maxContentLength = 1048576;
    }
    
    public function fetchContent($url)
    {
        if (!$this->isValidUrl($url)) {
            return ['error' => 'Invalid URL provided'];
        }
        
        if (!$this->isDomainAllowed($url)) {
            return ['error' => 'Domain not allowed'];
        }
        
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, $this->maxRedirects);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_RANGE, '0-' . ($this->maxContentLength - 1));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        if (curl_error($ch)) {
            curl_close($ch);
            return ['error' => 'Failed to fetch content'];
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return ['error' => 'HTTP Error: ' . $httpCode];
        }
        
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        if (strlen($body) > $this->maxContentLength) {
            return ['error' => 'Content too large'];
        }
        
        return [
            'success' => true,
            'content' => $body,
            'contentType' => $contentType,
            'headers' => $this->parseHeaders($headers),
            'url' => $url
        ];
    }
    
    private function isValidUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        $parsed = parse_url($url);
        
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
            return false;
        }
        
        if (!isset($parsed['host'])) {
            return false;
        }
        
        return true;
    }
    
    private function isDomainAllowed($url)
    {
        $parsed = parse_url($url);
        $host = strtolower($parsed['host']);
        
        foreach ($this->blockedDomains as $blocked) {
            if (strpos($host, $blocked) === 0) {
                return false;
            }
        }
        
        if (!empty($this->allowedDomains)) {
            foreach ($this->allowedDomains as $allowed) {
                if (strpos($host, $allowed) !== false) {
                    return true;
                }
            }
            return false;
        }
        
        return true;
    }
    
    private function parseHeaders($headerString)
    {
        $headers = [];
        $lines = explode("\r\n", $headerString);
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        
        return $headers;
    }
    
    public function extractMetadata($content, $contentType)
    {
        $metadata = [
            'title' => '',
            'description' => '',
            'image' => '',
            'type' => 'website'
        ];
        
        if (strpos($contentType, 'text/html') !== false) {
            $metadata = $this->extractHtmlMetadata($content);
        }
        
        return $metadata;
    }
    
    private function extractHtmlMetadata($html)
    {
        $metadata = [
            'title' => '',
            'description' => '',
            'image' => '',
            'type' => 'website'
        ];
        
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        
        $xpath = new DOMXPath($dom);
        
        $titleNodes = $xpath->query('//title');
        if ($titleNodes->length > 0) {
            $metadata['title'] = trim($titleNodes->item(0)->textContent);
        }
        
        $ogTitle = $xpath->query('//meta[@property="og:title"]/@content');
        if ($ogTitle->length > 0) {
            $metadata['title'] = $ogTitle->item(0)->value;
        }
        
        $description = $xpath->query('//meta[@name="description"]/@content');
        if ($description->length > 0) {
            $metadata['description'] = $description->item(0)->value;
        }
        
        $ogDescription = $xpath->query('//meta[@property="og:description"]/@content');
        if ($ogDescription->length > 0) {
            $metadata['description'] = $ogDescription->item(0)->value;
        }
        
        $ogImage = $xpath->query('//meta[@property="og:image"]/@content');
        if ($ogImage->length > 0) {
            $metadata['image'] = $ogImage->item(0)->value;
        }
        
        $ogType = $xpath->query('//meta[@property="og:type"]/@content');
        if ($ogType->length > 0) {
            $metadata['type'] = $ogType->item(0)->value;
        }
        
        return $metadata;
    }
}

class ContentDisplayer
{
    private $fetcher;
    
    public function __construct()
    {
        $this->fetcher = new RemoteContentFetcher();
    }
    
    public function displayContent($url)
    {
        $result = $this->fetcher->fetchContent($url);
        
        if (isset($result['error'])) {
            return $this->renderError($result['error']);
        }
        
        $metadata = $this->fetcher->extractMetadata($result['content'], $result['contentType']);
        
        return $this->renderContent($result, $metadata);
    }
    
    private function renderError($error)
    {
        return '<div class="content-error">Error: ' . htmlspecialchars($error) . '</div>';
    }
    
    private function renderContent($result, $metadata)
    {
        $html = '<div class="remote-content">';
        
        if (!empty($metadata['image'])) {
            $html .= '<div class="content-image">';
            $html .= '<img src="' . htmlspecialchars($metadata['image']) . '" alt="Content Image" />';
            $html .= '</div>';
        }
        
        if (!empty($metadata['title'])) {
            $html .= '<div class="content-title">';
            $html .= '<h3>' . htmlspecialchars($metadata['title']) . '</h3>';
            $html .= '</div>';
        }
        
        if (!empty($metadata['description'])) {
            $html .= '<div class="content-description">';
            $html .= '<p>' . htmlspecialchars($metadata['description']) . '</p>';
            $html .= '</div>';
        }
        
        $html .= '<div class="content-url">';
        $html .= '<a href="' . htmlspecialchars($result['url']) . '" target="_blank" rel="noopener noreferrer">';
        $html .= htmlspecialchars($result['url']);
        $html .= '</a>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    public function displayPreview($url)
    {
        $result = $this->fetcher->fetchContent($url);
        
        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }
        
        $metadata = $this->fetcher->extractMetadata($result['content'], $result['contentType']);
        
        return [
            'success' => true,
            'title' => $metadata['title'],
            'description' => $metadata['description'],
            'image' => $metadata['image'],
            'type' => $metadata['type'],
            'url' => $result['url']
        ];
    }
}

function handleUrlPreview()
{
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['url']) || empty($input['url'])) {
        http_response_code(400);
        echo json_encode(['error' => 'URL is required']);
        return;
    }
    
    $displayer = new ContentDisplayer();
    $result = $displayer->displayPreview($input['url']);
    
    echo json_encode($result);
}

function handleUrlDisplay()
{
    if (!isset($_GET['url']) || empty($_GET['url'])) {
        echo '<div class="error">No URL provided</div>';
        return;
    }
    
    $displayer = new ContentDisplayer();
    echo $displayer->displayContent($_GET['url']);
}

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'preview':
            handleUrlPreview();
            break;
        case 'display':
            handleUrlDisplay();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}
?>