<?php

class SecureURLFetcher
{
    private $logFile;
    private $timeout;
    private $maxRedirects;
    
    public function __construct($logFile = 'url_requests.log', $timeout = 10, $maxRedirects = 3)
    {
        $this->logFile = $logFile;
        $this->timeout = $timeout;
        $this->maxRedirects = $maxRedirects;
    }
    
    public function fetchURL($url)
    {
        try {
            $sanitizedUrl = $this->sanitizeInput($url);
            
            if (!$this->validateURL($sanitizedUrl)) {
                $this->logRequest($sanitizedUrl, 'INVALID_URL');
                return ['success' => false, 'error' => 'Invalid URL format'];
            }
            
            if (!$this->validateProtocol($sanitizedUrl)) {
                $this->logRequest($sanitizedUrl, 'INVALID_PROTOCOL');
                return ['success' => false, 'error' => 'Invalid protocol'];
            }
            
            if (!$this->validateHost($sanitizedUrl)) {
                $this->logRequest($sanitizedUrl, 'BLOCKED_HOST');
                return ['success' => false, 'error' => 'Access to this host is not allowed'];
            }
            
            $content = $this->performRequest($sanitizedUrl);
            
            if ($content === false) {
                $this->logRequest($sanitizedUrl, 'REQUEST_FAILED');
                return ['success' => false, 'error' => 'Failed to fetch content'];
            }
            
            $this->logRequest($sanitizedUrl, 'SUCCESS');
            return ['success' => true, 'content' => $content];
            
        } catch (Exception $e) {
            $this->logRequest($url ?? 'INVALID_INPUT', 'EXCEPTION');
            return ['success' => false, 'error' => 'An error occurred while processing the request'];
        }
    }
    
    private function sanitizeInput($input)
    {
        if (!is_string($input)) {
            throw new InvalidArgumentException('URL must be a string');
        }
        
        $sanitized = filter_var(trim($input), FILTER_SANITIZE_URL);
        
        if ($sanitized === false || empty($sanitized)) {
            throw new InvalidArgumentException('Invalid input provided');
        }
        
        return $sanitized;
    }
    
    private function validateURL($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    private function validateProtocol($url)
    {
        $parsed = parse_url($url);
        
        if (!isset($parsed['scheme'])) {
            return false;
        }
        
        $allowedSchemes = ['http', 'https'];
        return in_array(strtolower($parsed['scheme']), $allowedSchemes, true);
    }
    
    private function validateHost($url)
    {
        $parsed = parse_url($url);
        
        if (!isset($parsed['host'])) {
            return false;
        }
        
        $host = strtolower($parsed['host']);
        
        if ($this->isPrivateHost($host)) {
            return false;
        }
        
        $ip = gethostbyname($host);
        
        if ($ip === $host) {
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                return !$this->isPrivateIP($host);
            }
            return true;
        }
        
        return !$this->isPrivateIP($ip);
    }
    
    private function isPrivateHost($host)
    {
        $privateHosts = [
            'localhost',
            'localhost.localdomain',
            '127.0.0.1',
            '::1',
            '0.0.0.0'
        ];
        
        return in_array($host, $privateHosts, true);
    }
    
    private function isPrivateIP($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }
        
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
                'max_redirects' => $this->maxRedirects,
                'user_agent' => 'SecureURLFetcher/1.0',
                'follow_location' => true,
                'method' => 'GET'
            ],
            'https' => [
                'timeout' => $this->timeout,
                'max_redirects' => $this->maxRedirects,
                'user_agent' => 'SecureURLFetcher/1.0',
                'follow_location' => true,
                'method' => 'GET',
                'verify_peer' => true,
                'verify_host' => true
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        
        return $content;
    }
    
    private function logRequest($url, $status)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] URL: %s | Status: %s\n", $timestamp, $url, $status);
        
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function displayContent($result)
    {
        if ($result['success']) {
            echo htmlspecialchars($result['content'], ENT_QUOTES, 'UTF-8');
        } else {
            echo '<div>Error: ' . htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8') . '</div>';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $fetcher = new SecureURLFetcher();
    $result = $fetcher->fetchURL($_POST['url']);
    $fetcher->displayContent($result);
} else {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Secure URL Fetcher</title>
    </head>
    <body>
        <form method="post">
            <label for="url">Enter URL:</label>
            <input type="url" id="url" name="url" required>
            <button type="submit">Fetch Content</button>
        </form>
    </body>
    </html>
    <?php
}
?>