<?php
set_time_limit(30);
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE & ~E_WARNING);

$ROOT = realpath(__DIR__ . '/..');
$LOG_DIR = $ROOT . '/logs';
if (!is_dir($LOG_DIR)) {
    mkdir($LOG_DIR, 0755, true);
}
define('LOG_FILE', $LOG_DIR . '/url_requests.log');

function ipIsPrivate($ip) {
    $ip = strtolower($ip);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        if (count($parts) !== 4) return false;
        $first = intval($parts[0]);
        $second = intval($parts[1]);
        if ($first === 10) return true;
        if ($first === 172 && ($second >= 16 && $second <= 31)) return true;
        if ($first === 192 && $second === 168) return true;
        if ($ip === '127.0.0.1' || $ip === '127.0.0.0') return true;
        return false;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        if ($ip === '::1') return true;
        // Common private IPv6 ranges
        if (strpos($ip, 'fe80') === 0) return true;
        if (strpos($ip, 'fc') === 0 || strpos($ip, 'fd') === 0) return true;
        return false;
    }
    return false;
}

function logRequest($url, $status, $message = '') {
    $logLine = sprintf("[%s] URL: %s  STATUS: %s %s\n",
        date('Y-m-d H:i:s'),
        $url,
        $status,
        $message
    );
    if ($fh = @fopen(LOG_FILE, 'a')) {
        if (flock($fh, LOCK_EX)) {
            fwrite($fh, $logLine);
            fflush($fh);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
    }
}

function validateUrlFormatAndHost($url) {
    $parsed = @parse_url($url);
    if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) return false;
    $scheme = strtolower($parsed['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) return false;
    $host = $parsed['host'];
    if (strcasecmp($host, 'localhost') === 0) return false;

    $ips = @gethostbynamel($host);
    if ($ips !== false) {
        foreach ($ips as $ip) {
            if (ipIsPrivate($ip)) return false;
        }
    }
    return true;
}

class HttpClient {
    private $timeout;
    private $userAgent;
    public function __construct($timeout = 8, $userAgent = 'SocialAppPreview/1.0') {
        $this->timeout = (int)$timeout;
        $this->userAgent = $userAgent;
    }
    public function fetch($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('User-Agent: ' . $this->userAgent));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['success' => false, 'error' => $err, 'code' => curl_errno($ch)];
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);
        curl_close($ch);
        return ['success' => true, 'body' => $body, 'status' => $httpCode];
    }
}

class PreviewHandler {
    private $httpClient;
    public function __construct(HttpClient $client = null) {
        $this->httpClient = $client ?: new HttpClient();
    }
    private function generatePreview($url) {
        $result = $this->httpClient->fetch($url);
        if (!$result['success']) {
            return ['title' => '', 'description' => '', 'image' => '', 'url' => $url];
        }
        $html = $result['body'];
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $title = '';
        $description = '';
        $image = '';

        $titles = $dom->getElementsByTagName('title');
        if ($titles->length > 0) {
            $title = $titles->item(0)->textContent;
        }

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            $prop = $meta->getAttribute('property');
            $name = $meta->getAttribute('name');
            $content = $meta->getAttribute('content');
            if (!$title && ($prop === 'og:title' || $prop === 'twitter:title')) {
                $title = $content;
            }
            if (!$description && ($name === 'description' || $prop === 'og:description' || $prop === 'twitter:description')) {
                $description = $content;
            }
            if (!$image && $prop === 'og:image') {
                $image = $content;
            }
        }

        $title = trim($title);
        $description = trim($description);
        $image = trim($image);

        return [
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'url' => $url
        ];
    }
    public function generate($url) {
        return $this->generatePreview($url);
    }
    public function handle($url) {
        return $this->generate($url);
    }
}

// Public API endpoint
$inputMethod = $_SERVER['REQUEST_METHOD'];
if ($inputMethod !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$raw = trim(file_get_contents('php://input'));
$payload = [];
if ($raw) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $payload = $decoded;
    }
}
$urlRaw = '';
if (isset($payload['url'])) {
    $urlRaw = (string)$payload['url'];
} elseif (isset($_POST['url'])) {
    $urlRaw = (string)$_POST['url'];
}
$urlRaw = trim($urlRaw);

header('Content-Type: application/json; charset=utf-8');

if ($urlRaw === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'URL is required']);
    exit;
}

if (!validateUrlFormatAndHost($urlRaw)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid URL']);
    exit;
}

$handler = new PreviewHandler();
$preview = $handler->handle($urlRaw);
logRequest($urlRaw, 'SUCCESS', 'Preview fetched');
echo json_encode(['status' => 'ok', 'data' => $preview]);
exit;
?>