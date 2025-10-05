<?php
// classes/UrlFetcher.php

class UrlFetcher
{
    private $timeout;
    private $maxRedirects;
    private $userAgent;
    
    public function __construct($timeout = 10, $maxRedirects = 5)
    {
        $this->timeout = $timeout;
        $this->maxRedirects = $maxRedirects;
        $this->userAgent = 'Mozilla/5.0 (compatible; SocialMediaBot/1.0)';
    }
    
    public function fetch($url)
    {
        if (!$this->isValidUrl($url)) {
            throw new InvalidArgumentException('Invalid URL provided');
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => $this->maxRedirects,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Accept-Encoding: gzip, deflate',
                'Connection: keep-alive',
            ]
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($content === false || !empty($error)) {
            throw new RuntimeException('Failed to fetch URL: ' . $error);
        }
        
        if ($httpCode >= 400) {
            throw new RuntimeException('HTTP Error: ' . $httpCode);
        }
        
        return $content;
    }
    
    private function isValidUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && 
               (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0);
    }
}


<?php
// classes/ContentParser.php

class ContentParser
{
    public function parseContent($html, $url)
    {
        $data = [
            'url' => $url,
            'title' => '',
            'description' => '',
            'image' => '',
            'site_name' => '',
            'type' => 'website'
        ];
        
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new DOMXPath($dom);
        
        $data['title'] = $this->extractTitle($xpath);
        $data['description'] = $this->extractDescription($xpath);
        $data['image'] = $this->extractImage($xpath, $url);
        $data['site_name'] = $this->extractSiteName($xpath, $url);
        $data['type'] = $this->extractType($xpath);
        
        return $data;
    }
    
    private function extractTitle($xpath)
    {
        $queries = [
            '//meta[@property="og:title"]/@content',
            '//meta[@name="twitter:title"]/@content',
            '//title/text()',
            '//h1/text()'
        ];
        
        return $this->getFirstMatch($xpath, $queries);
    }
    
    private function extractDescription($xpath)
    {
        $queries = [
            '//meta[@property="og:description"]/@content',
            '//meta[@name="twitter:description"]/@content',
            '//meta[@name="description"]/@content'
        ];
        
        $description = $this->getFirstMatch($xpath, $queries);
        return substr($description, 0, 300);
    }
    
    private function extractImage($xpath, $baseUrl)
    {
        $queries = [
            '//meta[@property="og:image"]/@content',
            '//meta[@name="twitter:image"]/@content',
            '//meta[@name="twitter:image:src"]/@content'
        ];
        
        $image = $this->getFirstMatch($xpath, $queries);
        return $this->resolveUrl($image, $baseUrl);
    }
    
    private function extractSiteName($xpath, $url)
    {
        $queries = [
            '//meta[@property="og:site_name"]/@content'
        ];
        
        $siteName = $this->getFirstMatch($xpath, $queries);
        if (empty($siteName)) {
            $parsedUrl = parse_url($url);
            $siteName = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
        }
        
        return $siteName;
    }
    
    private function extractType($xpath)
    {
        $queries = [
            '//meta[@property="og:type"]/@content'
        ];
        
        $type = $this->getFirstMatch($xpath, $queries);
        return !empty($type) ? $type : 'website';
    }
    
    private function getFirstMatch($xpath, $queries)
    {
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes && $nodes->length > 0) {
                $value = trim($nodes->item(0)->nodeValue);
                if (!empty($value)) {
                    return $value;
                }
            }
        }
        return '';
    }
    
    private function resolveUrl($url, $baseUrl)
    {
        if (empty($url)) {
            return '';
        }
        
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        
        $parsedBase = parse_url($baseUrl);
        if (strpos($url, '//') === 0) {
            return $parsedBase['scheme'] . ':' . $url;
        }
        
        if (strpos($url, '/') === 0) {
            return $parsedBase['scheme'] . '://' . $parsedBase['host'] . $url;
        }
        
        return $parsedBase['scheme'] . '://' . $parsedBase['host'] . '/' . ltrim($url, '/');
    }
}


<?php
// classes/PreviewCache.php

class PreviewCache
{
    private $cacheDir;
    private $ttl;
    
    public function __construct($cacheDir = 'cache/previews', $ttl = 3600)
    {
        $this->cacheDir = $cacheDir;
        $this->ttl = $ttl;
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function get($url)
    {
        $key = $this->getCacheKey($url);
        $file = $this->cacheDir . '/' . $key . '.json';
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        if (!$data || (time() - $data['timestamp']) > $this->ttl) {
            unlink($file);
            return null;
        }
        
        return $data['content'];
    }
    
    public function set($url, $data)
    {
        $key = $this->getCacheKey($url);
        $file = $this->cacheDir . '/' . $key . '.json';
        
        $cacheData = [
            'timestamp' => time(),
            'content' => $data
        ];
        
        file_put_contents($file, json_encode($cacheData));
    }
    
    private function getCacheKey($url)
    {
        return md5($url);
    }
    
    public function cleanup()
    {
        $files = glob($this->cacheDir . '/*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && (time() - $data['timestamp']) > $this->ttl) {
                unlink($file);
            }
        }
    }
}


<?php
// classes/PreviewService.php

class PreviewService
{
    private $fetcher;
    private $parser;
    private $cache;
    
    public function __construct()
    {
        $this->fetcher = new UrlFetcher();
        $this->parser = new ContentParser();
        $this->cache = new PreviewCache();
    }
    
    public function getPreview($url)
    {
        try {
            $cached = $this->cache->get($url);
            if ($cached !== null) {
                return $cached;
            }
            
            $html = $this->fetcher->fetch($url);
            $preview = $this->parser->parseContent($html, $url);
            
            $this->cache->set($url, $preview);
            
            return $preview;
        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'url' => $url
            ];
        }
    }
    
    public function validateUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && 
               (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0);
    }
}


<?php
// handlers/PreviewHandler.php

require_once '../classes/UrlFetcher.php';
require_once '../classes/ContentParser.php';
require_once '../classes/PreviewCache.php';
require_once '../classes/PreviewService.php';

class PreviewHandler
{
    private $service;
    
    public function __construct()
    {
        $this->service = new PreviewService();
    }
    
    public function handleRequest()
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
        
        $url = trim($input['url']);
        
        if (!$this->service->validateUrl($url)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid URL format']);
            return;
        }
        
        $preview = $this->service->getPreview($url);
        
        if (isset($preview['error'])) {
            http_response_code(400);
            echo json_encode($preview);
            return;
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $preview
        ]);
    }
}

$handler = new PreviewHandler();
$handler->handleRequest();


<?php
// public/api/preview.php

require_once '../../handlers/PreviewHandler.php';


<?php
// public/preview-display.php

function displayPreview($previewData)
{
    if (isset($previewData['error'])) {
        return '<div class="preview-error">Unable to load preview: ' . htmlspecialchars($previewData['message']) . '</div>';
    }
    
    $html = '<div class="url-preview">';
    
    if (!empty($previewData['image'])) {
        $html .= '<div class="preview-image">';
        $html .= '<img src="' . htmlspecialchars($previewData['image']) . '" alt="Preview image" onerror="this.style.display=\'none\'">';
        $html .= '</div>';
    }
    
    $html .= '<div class="preview-content">';
    
    if (!empty($previewData['title'])) {
        $html .= '<h3 class="preview-title">' . htmlspecialchars($previewData['title']) . '</h3>';
    }
    
    if (!empty($previewData['description'])) {
        $html .= '<p class="preview-description">' . htmlspecialchars($previewData['description']) . '</p>';
    }
    
    if (!empty($previewData['site_name'])) {
        $html .= '<div class="preview-site">' . htmlspecialchars($previewData['site_name']) . '</div>';
    }
    
    $html .= '<div class="preview-url">';
    $html .= '<a href="' . htmlspecialchars($previewData['url']) . '" target="_blank" rel="noopener noreferrer">';
    $html .= htmlspecialchars($previewData['url']);
    $html .= '</a>';
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

function fetchAndDisplayPreview($url)
{
    require_once '../classes/PreviewService.php';
    
    $service = new PreviewService();
    $preview = $service->getPreview($url);
    
    return displayPreview($preview);
}
?>