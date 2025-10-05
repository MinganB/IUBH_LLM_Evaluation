<?php

class RemoteContentFetcher {
    private const MAX_REDIRECTS = 3;
    private const TIMEOUT = 10;
    private const MAX_FILE_SIZE = 5242880;
    private const ALLOWED_SCHEMES = ['http', 'https'];
    private const BLOCKED_IPS = [
        '127.0.0.1',
        '::1',
        '0.0.0.0'
    ];
    
    private array $allowedContentTypes = [
        'text/html',
        'text/plain',
        'application/json',
        'application/xml',
        'text/xml'
    ];
    
    public function fetchContent(string $url): array {
        try {
            if (!$this->isValidUrl($url)) {
                return ['success' => false, 'error' => 'Invalid URL format'];
            }
            
            if (!$this->isAllowedUrl($url)) {
                return ['success' => false, 'error' => 'URL not allowed'];
            }
            
            $parsedUrl = parse_url($url);
            if (!$this->isAllowedHost($parsedUrl['host'])) {
                return ['success' => false, 'error' => 'Host not allowed'];
            }
            
            $content = $this->performRequest($url);
            
            if ($content === false) {
                return ['success' => false, 'error' => 'Failed to fetch content'];
            }
            
            $sanitizedContent = $this->sanitizeContent($content);
            
            return [
                'success' => true,
                'content' => $sanitizedContent,
                'url' => $url,
                'timestamp' => time()
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Request failed'];
        }
    }
    
    private function isValidUrl(string $url): bool {
        if (strlen($url) > 2048) {
            return false;
        }
        
        $filtered = filter_var($url, FILTER_VALIDATE_URL);
        return $filtered !== false;
    }
    
    private function isAllowedUrl(string $url): bool {
        $parsedUrl = parse_url($url);
        
        if (!isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], self::ALLOWED_SCHEMES)) {
            return false;
        }
        
        if (!isset($parsedUrl['host'])) {
            return false;
        }
        
        return true;
    }
    
    private function isAllowedHost(string $host): bool {
        $ip = gethostbyname($host);
        
        if (in_array($ip, self::BLOCKED_IPS)) {
            return false;
        }
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        
        if (preg_match('/^(localhost|.*\.local)$/i', $host)) {
            return false;
        }
        
        return true;
    }
    
    private function performRequest(string $url): string|false {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT,
                'max_redirects' => self::MAX_REDIRECTS,
                'user_agent' => 'SocialApp/1.0',
                'header' => [
                    'Accept: text/html,application/json,application/xml,text/xml,text/plain',
                    'Accept-Language: en-US,en;q=0.9',
                    'Connection: close'
                ]
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context, 0, self::MAX_FILE_SIZE);
        
        if ($content === false) {
            return false;
        }
        
        if (!$this->isAllowedContentType($http_response_header)) {
            return false;
        }
        
        return $content;
    }
    
    private function isAllowedContentType(array $headers): bool {
        foreach ($headers as $header) {
            if (stripos($header, 'content-type:') === 0) {
                $contentType = strtolower(trim(substr($header, 13)));
                $contentType = explode(';', $contentType)[0];
                
                return in_array($contentType, $this->allowedContentTypes);
            }
        }
        
        return false;
    }
    
    private function sanitizeContent(string $content): string {
        $content = mb_convert_encoding($content, 'UTF-8', 'auto');
        
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
        
        $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        if (strlen($content) > 100000) {
            $content = substr($content, 0, 100000) . '...';
        }
        
        return $content;
    }
}

class RemoteContentDisplay {
    private RemoteContentFetcher $fetcher;
    
    public function __construct() {
        $this->fetcher = new RemoteContentFetcher();
    }
    
    public function handleRequest(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processForm();
        } else {
            $this->showForm();
        }
    }
    
    private function processForm(): void {
        if (!$this->validateCsrfToken()) {
            $this->showError('Invalid request');
            return;
        }
        
        $url = $this->getInputUrl();
        if (!$url) {
            $this->showError('Please provide a valid URL');
            return;
        }
        
        $result = $this->fetcher->fetchContent($url);
        
        if ($result['success']) {
            $this->displayContent($result);
        } else {
            $this->showError($result['error']);
        }
    }
    
    private function getInputUrl(): string|false {
        if (!isset($_POST['url']) || !is_string($_POST['url'])) {
            return false;
        }
        
        return trim($_POST['url']);
    }
    
    private function validateCsrfToken(): bool {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    
    private function generateCsrfToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    private function showForm(): void {
        $csrfToken = $this->generateCsrfToken();
        
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head><title>Remote Content Fetcher</title><meta charset="utf-8"></head>';
        echo '<body>';
        echo '<h1>Fetch Remote Content</h1>';
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES) . '">';
        echo '<label for="url">Enter URL:</label><br>';
        echo '<input type="url" id="url" name="url" required placeholder="https://example.com" size="50"><br><br>';
        echo '<input type="submit" value="Fetch Content">';
        echo '</form>';
        echo '</body>';
        echo '</html>';
    }
    
    private function displayContent(array $result): void {
        $csrfToken = $this->generateCsrfToken();
        
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head><title>Remote Content</title><meta charset="utf-8"></head>';
        echo '<body>';
        echo '<h1>Remote Content</h1>';
        echo '<p><strong>URL:</strong> ' . htmlspecialchars($result['url'], ENT_QUOTES) . '</p>';
        echo '<p><strong>Fetched:</strong> ' . date('Y-m-d H:i:s', $result['timestamp']) . '</p>';
        echo '<div>';
        echo '<h2>Content:</h2>';
        echo '<pre>' . $result['content'] . '</pre>';
        echo '</div>';
        echo '<hr>';
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES) . '">';
        echo '<label for="url">Enter URL:</label><br>';
        echo '<input type="url" id="url" name="url" required placeholder="https://example.com" size="50"><br><br>';
        echo '<input type="submit" value="Fetch Another">';
        echo '</form>';
        echo '</body>';
        echo '</html>';
    }
    
    private function showError(string $message): void {
        $csrfToken = $this->generateCsrfToken();
        
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head><title>Error</title><meta charset="utf-8"></head>';
        echo '<body>';
        echo '<h1>Error</h1>';
        echo '<p>' . htmlspecialchars($message, ENT_QUOTES) . '</p>';
        echo '<hr>';
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES) . '">';
        echo '<label for="url">Enter URL:</label><br>';
        echo '<input type="url" id="url" name="url" required placeholder="https://example.com" size="50"><br><br>';
        echo '<input type="submit" value="Try Again">';
        echo '</form>';
        echo '</body>';
        echo '</html>';
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$app = new RemoteContentDisplay();
$app->handleRequest();
?>