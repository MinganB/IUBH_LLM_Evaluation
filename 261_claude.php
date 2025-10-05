**index.html**
html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Preview</title>
</head>
<body>
    <form action="fetch.php" method="POST">
        <label for="url">Enter URL:</label>
        <input type="text" id="url" name="url" required>
        <button type="submit">Fetch Preview</button>
    </form>
</body>
</html>


**classes/UrlValidator.php**
<?php

class UrlValidator
{
    private $allowedSchemes = ['http', 'https'];
    private $blockedHosts = [
        'localhost',
        '127.0.0.1',
        '::1',
        '0.0.0.0'
    ];
    
    private $blockedNetworks = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '169.254.0.0/16',
        'fc00::/7',
        'fe80::/10'
    ];

    public function validate($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsedUrl = parse_url($url);
        
        if (!isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], $this->allowedSchemes)) {
            return false;
        }

        if (!isset($parsedUrl['host'])) {
            return false;
        }

        $host = $parsedUrl['host'];

        if (in_array(strtolower($host), array_map('strtolower', $this->blockedHosts))) {
            return false;
        }

        $ip = gethostbyname($host);
        
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }

            foreach ($this->blockedNetworks as $network) {
                if ($this->ipInRange($ip, $network)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function ipInRange($ip, $range)
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($range, $netmask) = explode('/', $range, 2);
        $range_decimal = ip2long($range);
        $ip_decimal = ip2long($ip);
        $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
        $netmask_decimal = ~$wildcard_decimal;

        return ($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal);
    }
}


**classes/ContentFetcher.php**
<?php

class ContentFetcher
{
    private $timeout = 10;
    private $maxRedirects = 3;
    private $userAgent = 'Social Media App URL Fetcher 1.0';

    public function fetch($url)
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'max_redirects' => $this->maxRedirects,
                'user_agent' => $this->userAgent,
                'follow_location' => 1,
                'ignore_errors' => true
            ],
            'https' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'max_redirects' => $this->maxRedirects,
                'user_agent' => $this->userAgent,
                'follow_location' => 1,
                'ignore_errors' => true,
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);

        $content = @file_get_contents($url, false, $context);
        
        if ($content === false) {
            return false;
        }

        return $content;
    }
}


**classes/Logger.php**
<?php

class Logger
{
    private $logFile;

    public function __construct($logFile = '../logs/url_requests.log')
    {
        $this->logFile = $logFile;
        $this->ensureLogDirectory();
    }

    private function ensureLogDirectory()
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
    }

    public function log($url, $status = 'requested')
    {
        $timestamp = date('Y-m-d H:i:s');
        $clientIp = $this->getClientIp();
        $logEntry = "[{$timestamp}] IP: {$clientIp} | Status: {$status} | URL: {$url}" . PHP_EOL;
        
        error_log($logEntry, 3, $this->logFile);
    }

    private function getClientIp()
    {
        $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return 'unknown';
    }
}


**handlers/UrlHandler.php**
<?php

require_once '../classes/UrlValidator.php';
require_once '../classes/ContentFetcher.php';
require_once '../classes/Logger.php';

class UrlHandler
{
    private $validator;
    private $fetcher;
    private $logger;

    public function __construct()
    {
        $this->validator = new UrlValidator();
        $this->fetcher = new ContentFetcher();
        $this->logger = new Logger();
    }

    public function handleRequest($url)
    {
        $sanitizedUrl = $this->sanitizeUrl($url);
        
        if (!$sanitizedUrl) {
            $this->logger->log($url, 'invalid_format');
            return ['success' => false, 'error' => 'Invalid URL format'];
        }

        if (!$this->validator->validate($sanitizedUrl)) {
            $this->logger->log($sanitizedUrl, 'validation_failed');
            return ['success' => false, 'error' => 'URL validation failed'];
        }

        $this->logger->log($sanitizedUrl, 'validated');

        $content = $this->fetcher->fetch($sanitizedUrl);

        if ($content === false) {
            $this->logger->log($sanitizedUrl, 'fetch_failed');
            return ['success' => false, 'error' => 'Failed to fetch content'];
        }

        $this->logger->log($sanitizedUrl, 'success');
        return ['success' => true, 'content' => $content];
    }

    private function sanitizeUrl($url)
    {
        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        if (strlen($url) > 2048) {
            return false;
        }

        return $url;
    }
}


**fetch.php**
<?php

require_once 'handlers/UrlHandler.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

if (!isset($_POST['url']) || empty($_POST['url'])) {
    http_response_code(400);
    echo 'URL parameter is required';
    exit;
}

$handler = new UrlHandler();
$result = $handler->handleRequest($_POST['url']);

if (!$result['success']) {
    http_response_code(400);
    echo htmlspecialchars($result['error']);
    exit;
}

$content = $result['content'];

$dom = new DOMDocument();
@$dom->loadHTML($content);

$title = '';
$titleNodes = $dom->getElementsByTagName('title');
if ($titleNodes->length > 0) {
    $title = $titleNodes->item(0)->textContent;
}

$description = '';
$metaTags = $dom->getElementsByTagName('meta');
foreach ($metaTags as $meta) {
    if ($meta->getAttribute('name') === 'description') {
        $description = $meta->getAttribute('content');
        break;
    }
}

echo '<!DOCTYPE html>';
echo '<html lang="en">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>URL Preview</title>';
echo '</head>';
echo '<body>';
echo '<div>';
echo '<h2>URL Preview</h2>';
echo '<div>';
echo '<h3>' . htmlspecialchars($title) . '</h3>';
echo '<p>' . htmlspecialchars($description) . '</p>';
echo '<div>';
echo htmlspecialchars(substr(strip_tags($content), 0, 500)) . '...';
echo '</div>';
echo '</div>';
echo '<a href="index.html">Back to form</a>';
echo '</div>';
echo '</body>';
echo '</html>';
?>