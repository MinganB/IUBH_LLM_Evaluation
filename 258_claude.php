index.html
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


fetch.php
<?php
class URLFetcher {
    private $logFile;
    private $timeout;
    
    public function __construct() {
        $this->logFile = __DIR__ . '/logs/url_requests.log';
        $this->timeout = 10;
        $this->ensureLogDirectory();
    }
    
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
    }
    
    private function logRequest($url, $success = true, $error = null) {
        $timestamp = date('Y-m-d H:i:s');
        $status = $success ? 'SUCCESS' : 'FAILED';
        $errorMsg = $error ? " - Error: $error" : '';
        $logEntry = "[$timestamp] $status - URL: $url$errorMsg" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function validateURL($url) {
        $url = filter_var(trim($url), FILTER_SANITIZE_URL);
        
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
        
        return $url;
    }
    
    private function isPrivateIP($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }
        
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    
    private function validateHost($host) {
        $ip = gethostbyname($host);
        
        if ($this->isPrivateIP($ip)) {
            return false;
        }
        
        $blockedHosts = [
            'localhost',
            '127.0.0.1',
            '0.0.0.0',
            '::1',
            'metadata.google.internal',
            '169.254.169.254'
        ];
        
        if (in_array(strtolower($host), $blockedHosts)) {
            return false;
        }
        
        if (preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|192\.168\.)/', $ip)) {
            return false;
        }
        
        return true;
    }
    
    public function fetchURL($url) {
        $validatedURL = $this->validateURL($url);
        
        if (!$validatedURL) {
            $this->logRequest($url, false, 'Invalid URL format');
            return ['success' => false, 'error' => 'Invalid URL format'];
        }
        
        $parsedUrl = parse_url($validatedURL);
        
        if (!$this->validateHost($parsedUrl['host'])) {
            $this->logRequest($validatedURL, false, 'Blocked host');
            return ['success' => false, 'error' => 'Access to this host is not allowed'];
        }
        
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
                'method' => 'GET',
                'user_agent' => 'URLFetcher/1.0',
                'follow_location' => false,
                'max_redirects' => 0
            ]
        ]);
        
        $content = @file_get_contents($validatedURL, false, $context);
        
        if ($content === false) {
            $this->logRequest($validatedURL, false, 'Failed to fetch content');
            return ['success' => false, 'error' => 'Failed to fetch content from the provided URL'];
        }
        
        $this->logRequest($validatedURL, true);
        return ['success' => true, 'content' => $content];
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

if (!isset($_POST['url']) || empty($_POST['url'])) {
    echo '<p>Error: URL is required</p>';
    echo '<a href="index.html">Go back</a>';
    exit;
}

$fetcher = new URLFetcher();
$result = $fetcher->fetchURL($_POST['url']);

if (!$result['success']) {
    echo '<p>Error: ' . htmlspecialchars($result['error']) . '</p>';
    echo '<a href="index.html">Go back</a>';
} else {
    echo '<h2>Fetched Content:</h2>';
    echo '<div>' . htmlspecialchars($result['content']) . '</div>';
    echo '<br><a href="index.html">Fetch another URL</a>';
}
?>