<?php

class SecureUrlFetcher
{
    private const MAX_REDIRECTS = 5;
    private const TIMEOUT = 30;
    private const MAX_CONTENT_LENGTH = 5242880; // 5MB
    private const USER_AGENT = 'SecureUrlFetcher/1.0';
    
    private array $allowedSchemes = ['http', 'https'];
    private array $blockedHosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
    private array $blockedPorts = [22, 23, 25, 53, 110, 143, 993, 995];
    
    public function fetchUrl(string $url): array
    {
        $result = [
            'success' => false,
            'content' => '',
            'error' => '',
            'headers' => [],
            'http_code' => 0
        ];
        
        try {
            if (!$this->validateUrl($url)) {
                throw new InvalidArgumentException('Invalid or unsafe URL provided');
            }
            
            $parsedUrl = parse_url($url);
            if (!$this->isUrlSafe($parsedUrl)) {
                throw new InvalidArgumentException('URL not allowed for security reasons');
            }
            
            $curl = curl_init();
            
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HEADER => false,
                CURLOPT_NOBODY => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_MAXFILESIZE => self::MAX_CONTENT_LENGTH,
                CURLOPT_HEADERFUNCTION => function($curl, $header) use (&$result) {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2) {
                        return $len;
                    }
                    $result['headers'][strtolower(trim($header[0]))] = trim($header[1]);
                    return $len;
                }
            ]);
            
            $content = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            
            curl_close($curl);
            
            if ($content === false) {
                throw new RuntimeException('Failed to fetch URL: ' . $error);
            }
            
            if ($httpCode >= 400) {
                throw new RuntimeException('HTTP Error: ' . $httpCode);
            }
            
            $result['success'] = true;
            $result['content'] = $this->sanitizeContent($content);
            $result['http_code'] = $httpCode;
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    private function validateUrl(string $url): bool
    {
        if (empty($url) || !is_string($url)) {
            return false;
        }
        
        if (strlen($url) > 2048) {
            return false;
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        $parsedUrl = parse_url($url);
        if ($parsedUrl === false || !isset($parsedUrl['scheme']) || !isset($parsedUrl['host'])) {
            return false;
        }
        
        return in_array(strtolower($parsedUrl['scheme']), $this->allowedSchemes);
    }
    
    private function isUrlSafe(array $parsedUrl): bool
    {
        $host = strtolower($parsedUrl['host']);
        
        if (in_array($host, $this->blockedHosts)) {
            return false;
        }
        
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->isPrivateOrReservedIp($host)) {
                return false;
            }
        }
        
        if (isset($parsedUrl['port']) && in_array($parsedUrl['port'], $this->blockedPorts)) {
            return false;
        }
        
        return true;
    }
    
    private function isPrivateOrReservedIp(string $ip): bool
    {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
    
    private function sanitizeContent(string $content): string
    {
        return htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

class UrlFetcherController
{
    private SecureUrlFetcher $fetcher;
    
    public function __construct()
    {
        $this->fetcher = new SecureUrlFetcher();
    }
    
    public function handleRequest(): void
    {
        session_start();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->validateCsrfToken()) {
                $this->renderError('Invalid CSRF token');
                return;
            }
            
            $url = $this->sanitizeInput($_POST['url'] ?? '');
            
            if (empty($url)) {
                $this->renderError('URL is required');
                return;
            }
            
            $result = $this->fetcher->fetchUrl($url);
            $this->renderResult($result, $url);
        } else {
            $this->renderForm();
        }
    }
    
    private function validateCsrfToken(): bool
    {
        return isset($_POST['csrf_token']) && 
               isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    
    private function sanitizeInput(string $input): string
    {
        return trim(filter_var($input, FILTER_SANITIZE_URL));
    }
    
    private function generateCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    private function renderForm(): void
    {
        $csrfToken = $this->generateCsrfToken();
        
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure URL Fetcher</title>
</head>
<body>
    <h1>Secure URL Fetcher</h1>
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">
        <label for="url">Enter URL:</label>
        <input type="url" id="url" name="url" required maxlength="2048" placeholder="https://example.com">
        <button type="submit">Fetch Content</button>
    </form>
</body>
</html>';
    }
    
    private function renderResult(array $result, string $url): void
    {
        $csrfToken = $this->generateCsrfToken();
        
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fetch Result - Secure URL Fetcher</title>
</head>
<body>
    <h1>Fetch Result</h1>
    <p><strong>URL:</strong> ' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</p>';
    
        if ($result['success']) {
            echo '<p><strong>HTTP Code:</strong> ' . intval($result['http_code']) . '</p>
            <h2>Content:</h2>
            <pre>' . $result['content'] . '</pre>';
        } else {
            echo '<p><strong>Error:</strong> ' . htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8') . '</p>';
        }
        
        echo '<form method="post" action="">
        <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">
        <label for="url">Enter URL:</label>
        <input type="url" id="url" name="url" required maxlength="2048" placeholder="https://example.com">
        <button type="submit">Fetch Another URL</button>
    </form>
</body>
</html>';
    }
    
    private function renderError(string $error): void
    {
        $csrfToken = $this->generateCsrfToken();
        
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Secure URL Fetcher</title>
</head>
<body>
    <h1>Error</h1>
    <p><strong>Error:</strong> ' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">
        <label for="url">Enter URL:</label>
        <input type="url" id="url" name="url" required maxlength="2048" placeholder="https://example.com">
        <button type="submit">Try Again</button>
    </form>
</body>
</html>';
    }
}

$controller = new UrlFetcherController();
$controller->handleRequest();
?>