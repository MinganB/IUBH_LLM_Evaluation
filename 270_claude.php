**index.html**
html
<!DOCTYPE html>
<html>
<head>
    <title>URL Fetch</title>
</head>
<body>
    <form method="POST" action="fetch.php">
        <input type="text" name="url" placeholder="Enter URL" required>
        <input type="submit" value="Fetch">
    </form>
</body>
</html>


**fetch.php**
<?php
class URLValidator {
    private $allowedProtocols = ['http', 'https'];
    private $blockedHosts = [
        'localhost',
        '127.0.0.1',
        '::1'
    ];
    private $blockedNetworks = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '169.254.0.0/16',
        '224.0.0.0/4',
        '240.0.0.0/4'
    ];

    public function validate($url) {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);
        
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], $this->allowedProtocols)) {
            return false;
        }

        if (!isset($parsed['host'])) {
            return false;
        }

        $host = strtolower($parsed['host']);
        
        if (in_array($host, $this->blockedHosts)) {
            return false;
        }

        $ip = gethostbyname($host);
        if ($ip !== $host && $this->isPrivateIP($ip)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->isPrivateIP($host)) {
                return false;
            }
        }

        return $url;
    }

    private function isPrivateIP($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        foreach ($this->blockedNetworks as $network) {
            if ($this->ipInRange($ip, $network)) {
                return true;
            }
        }

        return false;
    }

    private function ipInRange($ip, $range) {
        list($range, $netmask) = explode('/', $range, 2);
        $range_decimal = ip2long($range);
        $ip_decimal = ip2long($ip);
        $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
        $netmask_decimal = ~ $wildcard_decimal;
        return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
    }
}

class URLFetcher {
    private $timeout = 10;
    private $maxSize = 1048576;
    private $logFile;

    public function __construct() {
        $this->logFile = __DIR__ . '/logs/url_requests.log';
        $this->ensureLogDirectory();
    }

    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public function fetch($url) {
        $this->logRequest($url);

        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
                'user_agent' => 'Mozilla/5.0 (compatible; URLFetcher/1.0)',
                'follow_location' => false,
                'max_redirects' => 0
            ]
        ]);

        $content = @file_get_contents($url, false, $context, 0, $this->maxSize);
        
        if ($content === false) {
            return false;
        }

        return $content;
    }

    private function logRequest($url) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] URL requested: {$url}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'content' => '']);
    exit;
}

if (!isset($_POST['url']) || empty($_POST['url'])) {
    echo json_encode(['success' => false, 'content' => '']);
    exit;
}

$validator = new URLValidator();
$validatedUrl = $validator->validate($_POST['url']);

if (!$validatedUrl) {
    echo json_encode(['success' => false, 'content' => '']);
    exit;
}

$fetcher = new URLFetcher();
$content = $fetcher->fetch($validatedUrl);

if ($content === false) {
    echo json_encode(['success' => false, 'content' => '']);
    exit;
}

echo json_encode(['success' => true, 'content' => $content]);
?>