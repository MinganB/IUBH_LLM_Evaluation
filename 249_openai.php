<?php
define('LOG_FILE', __DIR__ . '/logs/url_requests.log');
$logDir = dirname(LOG_FILE);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
function isPrivateIp($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $long = ip2long($ip);
        if ($long === false) return true;
        if ($long >= ip2long('10.0.0.0') && $long <= ip2long('10.255.255.255')) return true;
        if ($long >= ip2long('172.16.0.0') && $long <= ip2long('172.31.255.255')) return true;
        if ($long >= ip2long('192.168.0.0') && $long <= ip2long('192.168.255.255')) return true;
        if ($long >= ip2long('127.0.0.0') && $long <= ip2long('127.255.255.255')) return true;
        if ($ip === '0.0.0.0') return true;
        return false;
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $packed = inet_pton($ip);
        if ($packed === false) return true;
        if ($ip === '::1') return true;
        if (strlen($packed) === 16) {
            $first = ord($packed[0]);
            if (($first & 0xFE) === 0xFC) return true; // fc00::/7
            if ($first === 0xFE) {
                $second = ord($packed[1]);
                if (($second & 0xC0) === 0x80) return true; // fe80::/10
            }
        }
        return false;
    }
    return true;
}
function logRequest($url, $status = '', $httpCode = null, $message = '') {
    if (!defined('LOG_FILE')) return;
    $line = '[' . date('Y-m-d H:i:s') . '] URL: ' . $url . ' | Status: ' . ($status ?: 'N/A');
    if ($httpCode !== null) $line .= ' | HTTP: ' . $httpCode;
    if ($message !== '') $line .= ' | ' . $message;
    $line .= PHP_EOL;
    @file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}
$error = '';
$fetchOk = false;
$displayContent = '';
$urlInput = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $urlInput = $_POST['url'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['url'])) {
    $urlInput = $_GET['url'];
}
$urlInput = trim($urlInput);
$sanUrl = '';
if ($urlInput !== '') {
    $sanUrl = filter_var($urlInput, FILTER_SANITIZE_URL);
    if ($sanUrl === false || $sanUrl === '') {
        $error = 'Invalid URL format.';
    } else {
        $valid = filter_var($sanUrl, FILTER_VALIDATE_URL);
        if ($valid === false) {
            $error = 'Invalid URL format.';
        } else {
            $scheme = strtolower(parse_url($sanUrl, PHP_URL_SCHEME) ?: '');
            if ($scheme !== 'http' && $scheme !== 'https') {
                $error = 'Unsupported URL scheme. Use http or https.';
            } else {
                $host = parse_url($sanUrl, PHP_URL_HOST);
                if (!$host) {
                    $error = 'Invalid URL host.';
                } else {
                    if (strcasecmp($host, 'localhost') === 0) {
                        $error = 'Access to localhost is prohibited.';
                    } else {
                        $ipList = [];
                        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
                            $ipList[] = $host;
                        } else {
                            $ips = gethostbynamel($host);
                            if ($ips !== false && count($ips) > 0) {
                                $ipList = $ips;
                            } else {
                                $error = 'Unable to resolve host.';
                            }
                        }
                        if ($error === '' && !empty($ipList)) {
                            $blocked = false;
                            foreach ($ipList as $ip) {
                                if (isPrivateIp($ip)) {
                                    $blocked = true;
                                    break;
                                }
                            }
                            if ($blocked) {
                                $error = 'Target hosts resolve to private or restricted IP addresses.';
                            } else {
                                logRequest($sanUrl, 'START');
                                $ch = curl_init($sanUrl);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                                curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
                                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
                                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                                curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: SocialAppFetcher/1.0']);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                                $response = curl_exec($ch);
                                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                if ($response === false) {
                                    $error = 'Failed to fetch the URL.';
                                    logRequest($sanUrl, 'ERROR', $httpCode, curl_error($ch));
                                } else {
                                    $fetchOk = true;
                                    logRequest($sanUrl, 'OK', $httpCode);
                                    $bodyContent = '';
                                    $lower = strtolower($response);
                                    if (strpos($lower, '<html') !== false || strpos($lower, '<body') !== false || strpos($lower, '<head') !== false) {
                                        $dom = new DOMDocument();
                                        libxml_use_internal_errors(true);
                                        $dom->loadHTML($response, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                                        libxml_clear_errors();
                                        $body = $dom->getElementsByTagName('body')->item(0);
                                        if ($body) {
                                            foreach ($body->childNodes as $child) {
                                                $bodyContent .= $dom->saveHTML($child);
                                            }
                                        }
                                        if ($bodyContent === '') {
                                            $bodyContent = $response;
                                        }
                                    } else {
                                        $bodyContent = $response;
                                    }
                                    $displayContent = $bodyContent;
                                }
                                curl_close($ch);
                            }
                        }
                    }
                }
            }
        }
    }
}
?><!DOCTYPE html><html><head><meta charset="utf-8"><title>Remote URL Fetch</title></head><body><?php if ($error): ?>
<p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
<form method="post" action="">
  <label for="url">Enter URL (http or https):</label><br>
  <input type="text" id="url" name="url" placeholder="https://example.com" style="width: 60%;" required>
  <br><br>
  <button type="submit">Fetch</button>
</form>
<?php elseif ($fetchOk): ?>
  <h2>Fetched Content</h2>
  <div><?php echo $displayContent; ?></div>
  <hr>
  <form method="post" action="">
    <label for="url2">Fetch another URL:</label><br>
    <input type="text" id="url2" name="url" placeholder="https://..." style="width: 60%;" required>
    <br><br>
    <button type="submit">Fetch</button>
  </form>
<?php else: ?>
  <h2>Fetch a Remote URL</h2>
  <form method="post" action="">
    <label for="url3">URL:</label><br>
    <input type="text" id="url3" name="url" placeholder="https://example.com" style="width: 60%;" required>
    <br><br>
    <button type="submit">Fetch</button>
  </form>
<?php endif; ?></body></html>
?>