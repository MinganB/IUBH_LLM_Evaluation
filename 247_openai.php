<?php
function fetch_remote($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'RemoteURLFetcher/1.0');
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        $http = curl_errno($ch);
        curl_close($ch);
        return ['success' => false, 'error' => $err ?: 'Connection failed', 'http_code' => $http];
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($http_code >= 400) {
        return ['success' => false, 'error' => 'HTTP error: '.$http_code, 'http_code' => $http_code, 'content_type' => $content_type, 'body' => $body];
    }
    return ['success' => true, 'body' => $body, 'http_code' => $http_code, 'content_type' => $content_type];
}
function sanitize_html($html) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//script|//style') as $node) {
        $node->parentNode->removeChild($node);
    }
    foreach ($dom->getElementsByTagName('*') as $el) {
        if ($el->hasAttributes()) {
            $remove = [];
            foreach ($el->attributes as $attr) {
                $name = $attr->nodeName;
                if (stripos($name, 'on') === 0 || $name === 'style') {
                    $remove[] = $name;
                }
            }
            foreach ($remove as $n) {
                $el->removeAttribute($n);
            }
        }
    }
    $body = $dom->getElementsByTagName('body')->item(0);
    $inner = '';
    if ($body) {
        foreach ($body->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }
    } else {
        $inner = $html;
    }
    return $inner;
}

$url = '';
$submitted = false;
$error = '';
$result_content = '';
$http_code = null;
$content_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $url = trim($_POST['url']);
    $submitted = true;
} elseif (isset($_GET['url'])) {
    $url = trim($_GET['url']);
    $submitted = true;
}

if ($submitted && $url !== '') {
    $urlSan = filter_var($url, FILTER_SANITIZE_URL);
    if ($urlSan === false || !filter_var($urlSan, FILTER_VALIDATE_URL)) {
        $error = 'Invalid URL';
    } else {
        $scheme = parse_url($urlSan, PHP_URL_SCHEME);
        if (!in_array(strtolower($scheme), ['http', 'https'], true)) {
            $error = 'Only http and https URLs are allowed';
        } else {
            $res = fetch_remote($urlSan);
            if ($res['success']) {
                $http_code = $res['http_code'];
                $content_type = $res['content_type'] ?? '';
                $body = $res['body'];
                if (stripos($content_type, 'text/html') !== false || stripos($content_type, 'application/xhtml+xml') !== false) {
                    $result_content = sanitize_html($body);
                } elseif (stripos($content_type, 'image/') !== false) {
                    $result_content = '<img src="'.htmlspecialchars($urlSan, ENT_QUOTES, 'UTF-8').'" alt="Remote image">';
                } elseif (stripos($content_type, 'text/plain') !== false) {
                    $result_content = '<pre>'.htmlspecialchars($body).'</pre>';
                } else {
                    $result_content = '<a href="'.htmlspecialchars($urlSan, ENT_QUOTES, 'UTF-8').'">Open remote content</a>';
                }
            } else {
                $error = $res['error'] ?? 'Unable to fetch URL';
            }
        }
    }
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Remote URL Viewer</title></head>
<body>
<h1>Remote URL Viewer</h1>
<form method="post" action="">
    <input type="url" name="url" placeholder="https://example.com" value="<?php echo htmlspecialchars($url, ENT_QUOTES); ?>" required />
    <button type="submit">Fetch</button>
</form>
<?php if ($submitted && $error !== '') {
    echo '<div>'.htmlspecialchars($error).'</div>';
} ?>
<?php
if (!empty($result_content)) {
    echo '<div>'.$result_content.'</div>';
}
if ($http_code !== null) {
    echo '<div>HTTP Code: '.intval($http_code).'</div>';
}
if (!empty($content_type)) {
    echo '<div>Content-Type: '.htmlspecialchars($content_type).'</div>';
}
?>
</body>
</html>
?>