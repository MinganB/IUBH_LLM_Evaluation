<?php
session_start();
$tokenName = 'preview_csrf_token';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = $_POST['csrf_token'] ?? '';
  if (!isset($_SESSION[$tokenName]) || !hash_equals($_SESSION[$tokenName], $csrf)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><body><div>Invalid CSRF token.</div></body></html>';
    exit;
  }
  $target_url = $_POST['target_url'] ?? '';
  require_once __DIR__ . '/../classes/UrlValidator.php';
  require_once __DIR__ . '/../classes/ContentFetcher.php';
  require_once __DIR__ . '/../handlers/PreviewHandler.php';
  $handler = new PreviewHandler($target_url, $_SERVER);
  $result = $handler->process();
  header('Content-Type: text/html; charset=utf-8');
  echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Preview</title></head><body>';
  if (!$result['ok']) {
    echo '<div style="color:#a00;">' . htmlspecialchars($result['error']) . '</div>';
  } else {
    echo $result['content'];
  }
  echo '</body></html>';
  exit;
} else {
  $csrf = bin2hex(random_bytes(32));
  $_SESSION[$tokenName] = $csrf;
  header('Content-Type: text/html; charset=utf-8');
  echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Share Preview</title></head><body>';
  echo '<form method="POST" action="fetch.php">';
  echo '<input type="hidden" name="csrf_token" value="' . $csrf . '"/>';
  echo '<label>URL to preview: <input type="url" name="target_url" placeholder="https://example.com" required style="width:60%"/></label>';
  echo '<button type="submit">Preview</button>';
  echo '</form>';
  echo '</body></html>';
  exit;
}
?>


<?php
class UrlValidator {
  public function isValid($url) {
    if (!is_string($url)) return false;
    $url = trim($url);
    if ($url === '') return false;
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return false;
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!$scheme) return false;
    $scheme = strtolower($scheme);
    if ($scheme !== 'http' && $scheme !== 'https') return false;
    return true;
  }
}
?>


<?php
class ContentFetcher {
  private $url;
  private $maxSize = 1024 * 1024;

  public function __construct($url) {
    $this->url = $url;
  }

  public function fetch() {
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $this->url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS => 5,
      CURLOPT_CONNECTTIMEOUT => 6,
      CURLOPT_TIMEOUT => 20,
      CURLOPT_HTTPHEADER => [
        'User-Agent: SocialPreviewBot/1.0 (+https://example.com)'
      ],
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false) return null;
    if ($httpCode >= 400) return null;
    if (strlen($response) > $this->maxSize) {
      $response = substr($response, 0, $this->maxSize);
    }
    return $response;
  }
}
?>


<?php
require_once __DIR__ . '/../classes/UrlValidator.php';
require_once __DIR__ . '/../classes/ContentFetcher.php';

class PreviewHandler {
  private $url;
  private $serverInfo;

  public function __construct($url, $serverInfo = []) {
    $this->url = $url;
    $this->serverInfo = $serverInfo;
  }

  public function process() {
    $validator = new UrlValidator();
    if (!$validator->isValid($this->url)) {
      return ['ok' => false, 'error' => 'Invalid URL.'];
    }
    $fetcher = new ContentFetcher($this->url);
    $raw = $fetcher->fetch();
    if ($raw === null) {
      return ['ok' => false, 'error' => 'Unable to fetch content from the provided URL.'];
    }
    $sanitized = $this->sanitizeHtml($raw);
    return ['ok' => true, 'content' => $sanitized];
  }

  private function sanitizeHtml($html) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//script|//style') as $node) {
      $node->parentNode->removeChild($node);
    }
    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body) {
      $content = '';
      foreach ($body->childNodes as $child) {
        $content .= $dom->saveHTML($child);
      }
      return $content;
    }
    return $dom->saveHTML();
  }
}
?>