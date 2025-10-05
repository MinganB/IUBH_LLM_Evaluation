<?php
session_start();

class UrlFetcher {
    private $maxContentLength = 1024 * 1024;
    private $timeout = 15;
    private $userAgent = 'SocialPreviewBot/1.0 (+https://example.com)';

    public function fetch($url, &$contentTypeOut = null) {
        $parsed = parse_url($url);
        if ($parsed === false || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            return false;
        }
        $scheme = strtolower($parsed['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        $host = $parsed['host'];
        if (strpos($host, 'localhost') !== false || $host === '127.0.0.1' || $host === '::1') {
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);

        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return false;
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $body = substr($response, $headerSize);

        curl_close($ch);

        if ($contentType !== null) {
            $contentTypeOut = $contentType;
        }

        if (strlen($body) > $this->maxContentLength) {
            return false;
        }

        $ct = strtolower($contentTypeOut ?? '');
        if ($ct && strpos($ct, 'text/html') === false && strpos($ct, 'application/xhtml+xml') === false) {
            return false;
        }

        if (stripos($body, '<html') === false && stripos($body, '<!doctype html') === false) {
            return false;
        }

        return $body;
    }
}

class HTMLSanitizer {
    public static function sanitize($html) {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        while (($script = $doc->getElementsByTagName('script'))->length > 0) {
            $scriptItem = $script->item(0);
            $scriptItem->parentNode->removeChild($scriptItem);
        }
        while (($style = $doc->getElementsByTagName('style'))->length > 0) {
            $styleItem = $style->item(0);
            $styleItem->parentNode->removeChild($styleItem);
        }

        self::removeEventHandlers($doc);

        $bodyList = $doc->getElementsByTagName('body');
        $inner = '';
        if ($bodyList->length > 0) {
            $body = $bodyList->item(0);
            foreach ($body->childNodes as $child) {
                $inner .= $doc->saveHTML($child);
            }
        } else {
            $inner = $doc->saveHTML($doc->documentElement);
        }

        libxml_clear_errors();
        return $inner;
    }

    private static function removeEventHandlers(DOMNode $node) {
        if ($node->hasAttributes()) {
            $toRemove = [];
            foreach ($node->attributes as $attr) {
                if (stripos($attr->name, 'on') === 0) {
                    $toRemove[] = $attr->name;
                }
            }
            foreach ($toRemove as $attrName) {
                $node->removeAttribute($attrName);
            }
        }
        foreach ($node->childNodes as $child) {
            if ($child->nodeType == XML_ELEMENT_NODE) {
                self::removeEventHandlers($child);
            }
        }
    }
}

class PreviewHandler {
    private $fetcher;
    public function __construct() {
        $this->fetcher = new UrlFetcher();
    }
    public function process($url) {
        $contentType = null;
        $html = $this->fetcher->fetch($url, $contentType);
        if ($html === false) {
            return [
                'success' => false,
                'message' => 'Unable to fetch content from the provided URL. Ensure it is reachable and returns HTML content.'
            ];
        }

        $title = '';
        $description = '';
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $titleNodes = $doc->getElementsByTagName('title');
        if ($titleNodes->length > 0) {
            $title = trim($titleNodes->item(0)->textContent);
        }
        $metaTags = $doc->getElementsByTagName('meta');
        for ($i = 0; $i < $metaTags->length; $i++) {
            $m = $metaTags->item($i);
            $name = strtolower($m->getAttribute('name'));
            $property = strtolower($m->getAttribute('property'));
            if ($name === 'description' && $description === '') {
                $description = trim($m->getAttribute('content'));
            } elseif ($property === 'og:description' && $description === '') {
                $description = trim($m->getAttribute('content'));
            }
        }

        $sanitized = HTMLSanitizer::sanitize($html);
        return [
            'success' => true,
            'title' => $title,
            'description' => $description,
            'previewHTML' => $sanitized
        ];
    }
}

function renderFrontendForm($csrfToken) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Link Preview</title></head><body>';
    echo '<h1>Share a Link Preview</h1>';
    echo '<form id="previewForm" method="POST" action="">';
    echo '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($csrfToken).'" />';
    echo '<label>URL: </label><input type="text" name="url" placeholder="https://example.com" style="width:60%;" />';
    echo '<button type="submit">Preview</button>';
    echo '</form>';
    echo '<div id="result" style="margin-top:20px;"></div>';
    echo '<script>';
    echo 'document.getElementById("previewForm").addEventListener("submit", function(e) { e.preventDefault(); var form = e.target; var data = new FormData(form); fetch("", { method: "POST", body: data, credentials: "same-origin" }).then(function(res){ return res.json(); }).then(function(json){ var resultDiv = document.getElementById("result"); if(!json.success){ resultDiv.innerHTML = "<p style=\"color:red;\">"+json.message+"</p>"; } else { var html = json.previewHTML || ""; var htmlBlock = "<h2>"+ (json.title || "") +"</h2>"; htmlBlock += "<p>"+ (json.description || "") +"</p>"; htmlBlock += "<div>"+ html +"</div>"; resultDiv.innerHTML = htmlBlock; } }).catch(function(err){ document.getElementById("result").innerHTML = "<p style=\"color:red;\">Error processing request.</p>"; }); });';
    echo '</script>';
    echo '</body></html>';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    if (!isset($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
    $url = $_POST['url'] ?? '';
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid URL.']);
        exit;
    }
    $handler = new PreviewHandler();
    $result = $handler->process($url);
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
} else {
    if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    renderFrontendForm($_SESSION['csrf_token']);
    exit;
}
?>