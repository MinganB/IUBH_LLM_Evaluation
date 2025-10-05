<?php

class URLValidator
{
    private const ALLOWED_PROTOCOLS = ['http', 'https'];
    private const PRIVATE_IP_RANGES = [
        '127.0.0.0/8',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '169.254.0.0/16',
        '::1/128',
        'fc00::/7',
        'fe80::/10'
    ];

    public function validateURL($url)
    {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsedUrl = parse_url($url);
        
        if (!isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], self::ALLOWED_PROTOCOLS)) {
            return false;
        }

        if (!isset($parsedUrl['host'])) {
            return false;
        }

        return $this->validateHost($parsedUrl['host']);
    }

    private function validateHost($host)
    {
        $ip = gethostbyname($host);
        
        if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this->isPublicIP($ip);
        }

        return true;
    }

    private function isPublicIP($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->isPublicIPv4($ip);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->isPublicIPv6($ip);
        }
        
        return false;
    }

    private function isPublicIPv4($ip)
    {
        foreach (self::PRIVATE_IP_RANGES as $range) {
            if (strpos($range, ':') === false && $this->ipInRange($ip, $range)) {
                return false;
            }
        }
        
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private function isPublicIPv6($ip)
    {
        foreach (self::PRIVATE_IP_RANGES as $range) {
            if (strpos($range, ':') !== false && $this->ipv6InRange($ip, $range)) {
                return false;
            }
        }
        
        return true;
    }

    private function ipInRange($ip, $range)
    {
        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        return ($ip & $mask) == $subnet;
    }

    private function ipv6InRange($ip, $range)
    {
        list($subnet, $bits) = explode('/', $range);
        $subnet = inet_pton($subnet);
        $ip = inet_pton($ip);
        $binarySubnet = '';
        $binaryIP = '';
        
        for ($i = 0; $i < strlen($subnet); $i++) {
            $binarySubnet .= str_pad(decbin(ord($subnet[$i])), 8, '0', STR_PAD_LEFT);
            $binaryIP .= str_pad(decbin(ord($ip[$i])), 8, '0', STR_PAD_LEFT);
        }
        
        return substr($binarySubnet, 0, $bits) === substr($binaryIP, 0, $bits);
    }
}


<?php

class URLFetcher
{
    private const TIMEOUT = 10;
    private const MAX_REDIRECTS = 3;
    private const USER_AGENT = 'SocialMediaApp/1.0';
    private const MAX_CONTENT_LENGTH = 1048576;

    private $validator;
    private $logger;

    public function __construct(URLValidator $validator, SecurityLogger $logger)
    {
        $this->validator = $validator;
        $this->logger = $logger;
    }

    public function fetchContent($url)
    {
        $sanitizedUrl = $this->sanitizeInput($url);
        
        if (!$this->validator->validateURL($sanitizedUrl)) {
            $this->logger->logRequest($sanitizedUrl, 'INVALID_URL');
            throw new InvalidArgumentException('Invalid URL provided');
        }

        $this->logger->logRequest($sanitizedUrl, 'VALID');

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT,
                'user_agent' => self::USER_AGENT,
                'max_redirects' => self::MAX_REDIRECTS,
                'follow_location' => 1,
                'ignore_errors' => true
            ]
        ]);

        $content = @file_get_contents($sanitizedUrl, false, $context);
        
        if ($content === false) {
            throw new RuntimeException('Failed to fetch content');
        }

        if (strlen($content) > self::MAX_CONTENT_LENGTH) {
            throw new RuntimeException('Content too large');
        }

        return $this->extractPreviewData($content, $sanitizedUrl);
    }

    private function sanitizeInput($input)
    {
        return filter_var(trim($input), FILTER_SANITIZE_URL);
    }

    private function extractPreviewData($content, $url)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($content);
        
        $title = $this->extractTitle($dom);
        $description = $this->extractDescription($dom);
        $image = $this->extractImage($dom, $url);

        return [
            'url' => $url,
            'title' => $title,
            'description' => $description,
            'image' => $image
        ];
    }

    private function extractTitle($dom)
    {
        $titleNodes = $dom->getElementsByTagName('title');
        if ($titleNodes->length > 0) {
            return htmlspecialchars(trim($titleNodes->item(0)->textContent), ENT_QUOTES, 'UTF-8');
        }

        $metaTags = $dom->getElementsByTagName('meta');
        foreach ($metaTags as $meta) {
            if ($meta->getAttribute('property') === 'og:title') {
                return htmlspecialchars(trim($meta->getAttribute('content')), ENT_QUOTES, 'UTF-8');
            }
        }

        return '';
    }

    private function extractDescription($dom)
    {
        $metaTags = $dom->getElementsByTagName('meta');
        foreach ($metaTags as $meta) {
            if ($meta->getAttribute('name') === 'description') {
                return htmlspecialchars(trim($meta->getAttribute('content')), ENT_QUOTES, 'UTF-8');
            }
            if ($meta->getAttribute('property') === 'og:description') {
                return htmlspecialchars(trim($meta->getAttribute('content')), ENT_QUOTES, 'UTF-8');
            }
        }

        return '';
    }

    private function extractImage($dom, $baseUrl)
    {
        $metaTags = $dom->getElementsByTagName('meta');
        foreach ($metaTags as $meta) {
            if ($meta->getAttribute('property') === 'og:image') {
                $imageUrl = $meta->getAttribute('content');
                return $this->resolveImageUrl($imageUrl, $baseUrl);
            }
        }

        return '';
    }

    private function resolveImageUrl($imageUrl, $baseUrl)
    {
        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8');
        }

        $parsedBase = parse_url($baseUrl);
        $baseScheme = $parsedBase['scheme'];
        $baseHost = $parsedBase['host'];

        if (strpos($imageUrl, '//') === 0) {
            return htmlspecialchars($baseScheme . ':' . $imageUrl, ENT_QUOTES, 'UTF-8');
        }

        if (strpos($imageUrl, '/') === 0) {
            return htmlspecialchars($baseScheme . '://' . $baseHost . $imageUrl, ENT_QUOTES, 'UTF-8');
        }

        return htmlspecialchars(rtrim($baseUrl, '/') . '/' . ltrim($imageUrl, '/'), ENT_QUOTES, 'UTF-8');
    }
}


<?php

class SecurityLogger
{
    private $logFile;

    public function __construct($logFile = null)
    {
        $this->logFile = $logFile ?: dirname(__DIR__) . '/logs/url_requests.log';
        $this->ensureLogDirectory();
    }

    public function logRequest($url, $status)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf(
            "[%s] URL: %s | Status: %s | IP: %s\n",
            $timestamp,
            $this->sanitizeForLog($url),
            $status,
            $this->getClientIP()
        );

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function ensureLogDirectory()
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
    }

    private function sanitizeForLog($input)
    {
        return preg_replace('/[^\x20-\x7E]/', '', $input);
    }

    private function getClientIP()
    {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if ($header === 'HTTP_X_FORWARDED_FOR') {
                    $ip = explode(',', $ip)[0];
                }
                return filter_var(trim($ip), FILTER_VALIDATE_IP) ? $ip : 'UNKNOWN';
            }
        }
        
        return 'UNKNOWN';
    }
}


<?php

require_once dirname(__DIR__) . '/classes/URLValidator.php';
require_once dirname(__DIR__) . '/classes/URLFetcher.php';
require_once dirname(__DIR__) . '/classes/SecurityLogger.php';

class URLPreviewHandler
{
    private $fetcher;

    public function __construct()
    {
        $validator = new URLValidator();
        $logger = new SecurityLogger();
        $this->fetcher = new URLFetcher($validator, $logger);
    }

    public function handle()
    {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['url']) || empty($input['url'])) {
            http_response_code(400);
            echo json_encode(['error' => 'URL parameter is required']);
            return;
        }

        try {
            $previewData = $this->fetcher->fetchContent($input['url']);
            echo json_encode([
                'success' => true,
                'data' => $previewData
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid URL provided']);
        } catch (RuntimeException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Unable to fetch content']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }
}


<?php

require_once dirname(__DIR__) . '/handlers/URLPreviewHandler.php';

$handler = new URLPreviewHandler();
$handler->handle();
?>