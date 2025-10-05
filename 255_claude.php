html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Fetcher</title>
</head>
<body>
    <form action="fetch.php" method="POST">
        <label for="url">Enter URL:</label>
        <input type="text" id="url" name="url" required>
        <button type="submit">Fetch Content</button>
    </form>
</body>
</html>


<?php

class URLFetcher {
    
    private $logFile = 'url_requests.log';
    private $timeout = 10;
    
    public function __construct() {
        $this->ensureLogFileExists();
    }
    
    private function ensureLogFileExists() {
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
            chmod($this->logFile, 0644);
        }
    }
    
    private function sanitizeUrl($url) {
        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);
        return $url;
    }
    
    private function validateUrl($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        $parsedUrl = parse_url($url);
        
        if (!isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], ['http', 'https'])) {
            return false;
        }
        
        if (!isset($parsedUrl['host'])) {
            return false;
        }
        
        return $this->validateHost($parsedUrl['host']);
    }
    
    private function validateHost($host) {
        $ip = gethostbyname($host);
        
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            if ($this->isPrivateIp($ip)) {
                return false;
            }
        }
        
        $blockedHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
        if (in_array(strtolower($host), $blockedHosts)) {
            return false;
        }
        
        return true;
    }
    
    private function isPrivateIp($ip) {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    
    private function logRequest($url, $success = true) {
        $timestamp = date('Y-m-d H:i:s');
        $status = $success ? 'SUCCESS' : 'FAILED';
        $logEntry = "[{$timestamp}] {$status}: {$url}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function fetchContent($url) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'URLFetcher/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_MAXFILESIZE => 10485760
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($content === false || !empty($error)) {
            $this->logRequest($url, false);
            return false;
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            $this->logRequest($url, false);
            return false;
        }
        
        $this->logRequest($url, true);
        return $content;
    }
    
    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->showError('Invalid request method');
        }
        
        if (!isset($_POST['url']) || empty($_POST['url'])) {
            return $this->showError('URL is required');
        }
        
        $url = $this->sanitizeUrl($_POST['url']);
        
        if (!$this->validateUrl($url)) {
            $this->logRequest($url, false);
            return $this->showError('Invalid URL provided');
        }
        
        $content = $this->fetchContent($url);
        
        if ($content === false) {
            return $this->showError('Failed to fetch content');
        }
        
        return $this->showContent($content);
    }
    
    private function showError($message) {
        echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
        echo '<h1>Error</h1>';
        echo '<p>' . htmlspecialchars($message) . '</p>';
        echo '<a href="index.html">Go back</a>';
        echo '</body></html>';
    }
    
    private function showContent($content) {
        echo '<!DOCTYPE html><html><head><title>Fetched Content</title></head><body>';
        echo '<h1>Fetched Content</h1>';
        echo '<div>' . htmlspecialchars($content) . '</div>';
        echo '<br><a href="index.html">Fetch another URL</a>';
        echo '</body></html>';
    }
}

$fetcher = new URLFetcher();
$fetcher->handleRequest();

?>