<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

$display = '';
$showForm = true;
$url = '';
$innerHTML = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $url = trim($_POST['url']);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        $display = 'Invalid URL';
    } else {
        $host = parse_url($url, PHP_URL_HOST);
        if (empty($host)) {
            $display = 'Invalid URL';
        } else {
            $hostLower = strtolower($host);
            if (strpos($hostLower, 'localhost') !== false) {
                $display = 'Access to localhost is blocked';
            } else {
                $ip = false;
                if (filter_var($host, FILTER_VALIDATE_IP)) {
                    $ip = $host;
                } else {
                    $resolved = gethostbyname($host);
                    if ($resolved && $resolved !== $host) {
                        $ip = $resolved;
                    }
                }
                $private = false;
                if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $long = ip2long($ip);
                    if ($long !== false) {
                        $private = (
                            $long >= ip2long('10.0.0.0') && $long <= ip2long('10.255.255.255')) ||
                            ($long >= ip2long('172.16.0.0') && $long <= ip2long('172.31.255.255')) ||
                            ($long >= ip2long('192.168.0.0') && $long <= ip2long('192.168.255.255')) ||
                            ($ip === '127.0.0.1');
                    }
                }
                if ($private) {
                    $display = 'URL points to a private network address';
                } else {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'SecureContentFetcher/1.0');
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8']);
                    curl_setopt($ch, CURLOPT_ENCODING, '');
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                    $resp = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);
                    if ($resp === false) {
                        $display = 'Error fetching URL: ' . htmlspecialchars($curlError ?? 'Unknown', ENT_QUOTES, 'UTF-8');
                    } elseif ($httpCode >= 400) {
                        $display = 'HTTP error: ' . $httpCode;
                    } else {
                        $innerHTML = extract_body_html($resp);
                        $display = '<h2>Fetched content from: ' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</h2>' . $innerHTML;
                        $showForm = false;
                    }
                }
            }
        }
    }
}

function extract_body_html(string $html): string {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    foreach (['script','style','iframe','object','embed'] as $tag) {
        while (($nodes = $dom->getElementsByTagName($tag)) && $nodes->length > 0) {
            $node = $nodes->item(0);
            $node->parentNode->removeChild($node);
        }
    }
    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body) {
        $inner = '';
        foreach ($body->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }
        return $inner;
    }
    return $html;
}

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>URL Fetcher</title></head><body>';

if ($display !== '') {
    echo $display;
}

if ($showForm) {
    echo '<form method="post" action="fetch.php">';
    echo '<label for="url">URL:</label>';
    echo '<input type="text" id="url" name="url" value="' . htmlspecialchars($url ?? '', ENT_QUOTES, 'UTF-8') . '" placeholder="https://example.com" required>';
    echo '<button type="submit">Fetch</button>';
    echo '</form>';
}

echo '</body></html>';
?>