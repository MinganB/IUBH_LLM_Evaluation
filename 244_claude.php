<?php
class RemoteContentFetcher
{
    private $allowedProtocols = ['http', 'https'];
    private $timeout = 30;
    private $maxRedirects = 5;
    private $userAgent = 'RemoteContentFetcher/1.0';
    
    public function fetchContent($url)
    {
        if (!$this->isValidUrl($url)) {
            throw new InvalidArgumentException('Invalid URL provided');
        }
        
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
                'user_agent' => $this->userAgent,
                'follow_location' => true,
                'max_redirects' => $this->maxRedirects,
                'ignore_errors' => true
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        
        if ($content === false) {
            $error = error_get_last();
            throw new RuntimeException('Failed to fetch content: ' . ($error['message'] ?? 'Unknown error'));
        }
        
        return $content;
    }
    
    public function fetchWithCurl($url)
    {
        if (!$this->isValidUrl($url)) {
            throw new InvalidArgumentException('Invalid URL provided');
        }
        
        if (!extension_loaded('curl')) {
            throw new RuntimeException('cURL extension is not available');
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => $this->maxRedirects,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($content === false) {
            throw new RuntimeException('cURL error: ' . $error);
        }
        
        if ($httpCode >= 400) {
            throw new RuntimeException('HTTP error: ' . $httpCode);
        }
        
        return $content;
    }
    
    private function isValidUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], $this->allowedProtocols)) {
            return false;
        }
        
        return true;
    }
    
    public function setTimeout($seconds)
    {
        $this->timeout = (int)$seconds;
    }
    
    public function setMaxRedirects($count)
    {
        $this->maxRedirects = (int)$count;
    }
    
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
    }
}

class RemoteContentDisplay
{
    private $fetcher;
    
    public function __construct(RemoteContentFetcher $fetcher = null)
    {
        $this->fetcher = $fetcher ?: new RemoteContentFetcher();
    }
    
    public function displayContent($url, $method = 'file_get_contents')
    {
        try {
            if ($method === 'curl') {
                $content = $this->fetcher->fetchWithCurl($url);
            } else {
                $content = $this->fetcher->fetchContent($url);
            }
            
            echo $this->sanitizeOutput($content);
        } catch (Exception $e) {
            echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    public function displayRawContent($url, $method = 'file_get_contents')
    {
        try {
            if ($method === 'curl') {
                $content = $this->fetcher->fetchWithCurl($url);
            } else {
                $content = $this->fetcher->fetchContent($url);
            }
            
            return $content;
        } catch (Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }
    
    private function sanitizeOutput($content)
    {
        return htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    }
    
    public function displayAsHtml($url, $method = 'file_get_contents')
    {
        try {
            if ($method === 'curl') {
                $content = $this->fetcher->fetchWithCurl($url);
            } else {
                $content = $this->fetcher->fetchContent($url);
            }
            
            echo $content;
        } catch (Exception $e) {
            echo '<div class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $url = trim($_POST['url']);
    $method = isset($_POST['method']) ? $_POST['method'] : 'file_get_contents';
    $display_type = isset($_POST['display_type']) ? $_POST['display_type'] : 'sanitized';
    
    $fetcher = new RemoteContentFetcher();
    $display = new RemoteContentDisplay($fetcher);
    
    echo '<div class="result">';
    echo '<h3>Content from: ' . htmlspecialchars($url) . '</h3>';
    
    if ($display_type === 'raw') {
        echo '<pre>';
        $display->displayContent($url, $method);
        echo '</pre>';
    } elseif ($display_type === 'html') {
        $display->displayAsHtml($url, $method);
    } else {
        echo '<pre>';
        $display->displayContent($url, $method);
        echo '</pre>';
    }
    
    echo '</div>';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Remote Content Fetcher</title>
    <meta charset="UTF-8">
</head>
<body>
    <h1>Remote Content Fetcher</h1>
    
    <form method="post" action="">
        <div>
            <label for="url">URL:</label>
            <input type="url" id="url" name="url" required value="<?php echo isset($_POST['url']) ? htmlspecialchars($_POST['url']) : ''; ?>" size="50">
        </div>
        
        <div>
            <label for="method">Fetch Method:</label>
            <select id="method" name="method">
                <option value="file_get_contents" <?php echo (isset($_POST['method']) && $_POST['method'] === 'file_get_contents') ? 'selected' : ''; ?>>file_get_contents</option>
                <option value="curl" <?php echo (isset($_POST['method']) && $_POST['method'] === 'curl') ? 'selected' : ''; ?>>cURL</option>
            </select>
        </div>
        
        <div>
            <label for="display_type">Display Type:</label>
            <select id="display_type" name="display_type">
                <option value="sanitized" <?php echo (isset($_POST['display_type']) && $_POST['display_type'] === 'sanitized') ? 'selected' : ''; ?>>Sanitized Text</option>
                <option value="raw" <?php echo (isset($_POST['display_type']) && $_POST['display_type'] === 'raw') ? 'selected' : ''; ?>>Raw Text</option>
                <option value="html" <?php echo (isset($_POST['display_type']) && $_POST['display_type'] === 'html') ? 'selected' : ''; ?>>Rendered HTML</option>
            </select>
        </div>
        
        <div>
            <input type="submit" value="Fetch Content">
        </div>
    </form>
</body>
</html>
?>