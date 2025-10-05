<?php

class SecureUrlFetcher
{
    private $logFile;
    private $timeout;
    private $maxContentLength;
    
    public function __construct($logFile = '/var/log/url_fetcher.log', $timeout = 10, $maxContentLength = 1048576)
    {
        $this->logFile = $logFile;
        $this->timeout = $timeout;
        $this->maxContentLength = $maxContentLength;
    }
    
    public function fetchUrl($userUrl)
    {
        try {
            $sanitizedUrl = $this->sanitizeInput($userUrl);
            
            if (!$this->validateUrlFormat($sanitizedUrl)) {
                $this->logRequest($sanitizedUrl, 'INVALID_FORMAT');
                return ['success' => false, 'error' => 'Invalid URL format'];
            }
            
            if (!$this->validateProtocol($sanitizedUrl)) {
                $this->logRequest($sanitizedUrl, 'INVALID_PROTOCOL');
                return ['success' => false, 'error' => 'Invalid protocol'];
            }
            
            if (!$this->validateHost($sanitizedUrl)) {
                $this->logRequest($sanitizedUrl, 'INVALID_HOST');
                return ['success' => false, 'error' => 'Invalid host'];
            }
            
            $content = $this->performRequest($sanitizedUrl);
            $this->logRequest($sanitizedUrl, 'SUCCESS');
            
            return [
                'success' => true,
                'content' => $content,
                'url' => $sanitizedUrl
            ];
            
        } catch (Exception $e) {
            $this->logRequest($userUrl ?? 'INVALID_INPUT', 'ERROR');
            return ['success' => false, 'error' => 'Request failed'];
        }
    }
    
    private function sanitizeInput($input)
    {
        if (!is_string($input)) {
            throw new InvalidArgumentException('Invalid input type');
        }
        
        $sanitized = filter_var(trim($input), FILTER_SANITIZE_URL);
        
        if ($sanitized === false || empty($sanitized)) {
            throw new InvalidArgumentException('Invalid URL input');
        }
        
        return $sanitized;
    }
    
    private function validateUrlFormat($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    private function validateProtocol($url)
    {
        $parsed = parse_url($url);
        
        if (!isset($parsed['scheme'])) {
            return false;
        }
        
        $allowedProtocols = ['http', 'https'];
        return in_array(strtolower($parsed['scheme']), $allowedProtocols);
    }
    
    private function validateHost($url)
    {
        $parsed = parse_url($url);
        
        if (!isset($parsed['host'])) {
            return false;
        }
        
        $host = $parsed['host'];
        
        if ($this->isPrivateHost($host)) {
            return false;
        }
        
        $ip = gethostbyname($host);
        
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return !$this->isPrivateIp($ip);
        }
        
        return true;
    }
    
    private function isPrivateHost($host)
    {
        $privateHosts = [
            'localhost',
            '127.0.0.1',
            '::1',
            '0.0.0.0'
        ];
        
        return in_array(strtolower($host), $privateHosts);
    }
    
    private function isPrivateIp($ip)
    {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
    
    private function performRequest($url)
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
                'method' => 'GET',
                'header' => [
                    'User-Agent: SocialMediaApp/1.0',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.5',
                    'Accept-Encoding: identity',
                    'Connection: close'
                ],
                'max_redirects' => 3,
                'follow_location' => true
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false
            ]
        ]);
        
        $content = file_get_contents($url, false, $context);
        
        if ($content === false) {
            throw new Exception('Failed to fetch content');
        }
        
        if (strlen($content) > $this->maxContentLength) {
            $content = substr($content, 0, $this->maxContentLength);
        }
        
        return $content;
    }
    
    private function logRequest($url, $status)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] URL: %s | Status: %s | IP: %s\n", 
            $timestamp, 
            $url, 
            $status,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        
        error_log($logEntry, 3, $this->logFile);
    }
    
    public function displayContent($result)
    {
        if (!$result['success']) {
            return '<div class="error">Error: ' . htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8') . '</div>';
        }
        
        $safeContent = htmlspecialchars($result['content'], ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($result['url'], ENT_QUOTES, 'UTF-8');
        
        return '<div class="url-content">' .
               '<div class="url-source">Content from: ' . $safeUrl . '</div>' .
               '<div class="content">' . $safeContent . '</div>' .
               '</div>';
    }
}

class UrlFetcherController
{
    private $fetcher;
    
    public function __construct()
    {
        $this->fetcher = new SecureUrlFetcher();
    }
    
    public function handleRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
            $result = $this->fetcher->fetchUrl($_POST['url']);
            return $this->fetcher->displayContent($result);
        }
        
        return $this->renderForm();
    }
    
    private function renderForm()
    {
        return '<form method="POST" action="">' .
               '<div>' .
               '<label for="url">Enter URL:</label>' .
               '<input type="url" id="url" name="url" required maxlength="2048" placeholder="https://example.com">' .
               '</div>' .
               '<div>' .
               '<button type="submit">Fetch Content</button>' .
               '</div>' .
               '</form>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new UrlFetcherController();
    echo $controller->handleRequest();
}
?>