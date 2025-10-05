<?php
class UrlContentFetcher {
    private $allowedSchemes = ['http', 'https'];
    private $timeout = 10;
    private $maxRedirects = 3;
    private $userAgent = 'SocialMediaApp/1.0';
    private $maxContentLength = 1048576; // 1MB
    
    public function fetchContent($url) {
        if (!$this->validateUrl($url)) {
            throw new InvalidArgumentException('Invalid URL provided');
        }
        
        if (!$this->isAllowedUrl($url)) {
            throw new InvalidArgumentException('URL not allowed');
        }
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'max_redirects' => $this->maxRedirects,
                'user_agent' => $this->userAgent,
                'ignore_errors' => true,
                'header' => "Accept: text/html,application/xhtml+xml\r\n"
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        
        if ($content === false) {
            throw new RuntimeException('Failed to fetch URL content');
        }
        
        if (strlen($content) > $this->maxContentLength) {
            $content = substr($content, 0, $this->maxContentLength);
        }
        
        return $content;
    }
    
    private function validateUrl($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        $parsedUrl = parse_url($url);
        
        if (!$parsedUrl || !isset($parsedUrl['scheme']) || !isset($parsedUrl['host'])) {
            return false;
        }
        
        if (!in_array($parsedUrl['scheme'], $this->allowedSchemes)) {
            return false;
        }
        
        return true;
    }
    
    private function isAllowedUrl($url) {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'];
        
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        } else {
            $ip = gethostbyname($host);
        }
        
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        
        return true;
    }
}


<?php
class UrlPreviewParser {
    public function parseContent($content, $url) {
        $dom = new DOMDocument();
        @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $preview = [
            'url' => $url,
            'title' => $this->getTitle($dom),
            'description' => $this->getDescription($dom),
            'image' => $this->getImage($dom, $url),
            'site_name' => $this->getSiteName($dom)
        ];
        
        return array_map([$this, 'sanitizeOutput'], $preview);
    }
    
    private function getTitle($dom) {
        $ogTitle = $this->getMetaContent($dom, 'property', 'og:title');
        if ($ogTitle) {
            return $ogTitle;
        }
        
        $twitterTitle = $this->getMetaContent($dom, 'name', 'twitter:title');
        if ($twitterTitle) {
            return $twitterTitle;
        }
        
        $titleNodes = $dom->getElementsByTagName('title');
        if ($titleNodes->length > 0) {
            return $titleNodes->item(0)->textContent;
        }
        
        return '';
    }
    
    private function getDescription($dom) {
        $ogDescription = $this->getMetaContent($dom, 'property', 'og:description');
        if ($ogDescription) {
            return $ogDescription;
        }
        
        $twitterDescription = $this->getMetaContent($dom, 'name', 'twitter:description');
        if ($twitterDescription) {
            return $twitterDescription;
        }
        
        $metaDescription = $this->getMetaContent($dom, 'name', 'description');
        if ($metaDescription) {
            return $metaDescription;
        }
        
        return '';
    }
    
    private function getImage($dom, $baseUrl) {
        $ogImage = $this->getMetaContent($dom, 'property', 'og:image');
        if ($ogImage) {
            return $this->resolveUrl($ogImage, $baseUrl);
        }
        
        $twitterImage = $this->getMetaContent($dom, 'name', 'twitter:image');
        if ($twitterImage) {
            return $this->resolveUrl($twitterImage, $baseUrl);
        }
        
        return '';
    }
    
    private function getSiteName($dom) {
        $ogSiteName = $this->getMetaContent($dom, 'property', 'og:site_name');
        if ($ogSiteName) {
            return $ogSiteName;
        }
        
        return '';
    }
    
    private function getMetaContent($dom, $attribute, $value) {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query("//meta[@{$attribute}='{$value}']");
        
        if ($nodes->length > 0) {
            return $nodes->item(0)->getAttribute('content');
        }
        
        return null;
    }
    
    private function resolveUrl($url, $baseUrl) {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        
        $parsedBase = parse_url($baseUrl);
        $base = $parsedBase['scheme'] . '://' . $parsedBase['host'];
        
        if (isset($parsedBase['port'])) {
            $base .= ':' . $parsedBase['port'];
        }
        
        if (strpos($url, '/') === 0) {
            return $base . $url;
        }
        
        return $base . '/' . ltrim($url, '/');
    }
    
    private function sanitizeOutput($value) {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
}


<?php
require_once '../classes/UrlContentFetcher.php';
require_once '../classes/UrlPreviewParser.php';

class UrlPreviewHandler {
    private $fetcher;
    private $parser;
    
    public function __construct() {
        $this->fetcher = new UrlContentFetcher();
        $this->parser = new UrlPreviewParser();
    }
    
    public function handleRequest() {
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
        
        $url = filter_var(trim($input['url']), FILTER_SANITIZE_URL);
        
        try {
            $content = $this->fetcher->fetchContent($url);
            $preview = $this->parser->parseContent($content, $url);
            
            echo json_encode([
                'success' => true,
                'data' => $preview
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch URL preview']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }
}

$handler = new UrlPreviewHandler();
$handler->handleRequest();


<?php
require_once '../handlers/UrlPreviewHandler.php';
?>