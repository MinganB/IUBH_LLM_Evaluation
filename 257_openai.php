<?php
session_start();

$csrfToken = $_SESSION['csrf_token'] ?? null;
if (!$csrfToken) {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}

$error = null;
$contents = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $urlInput = $_POST['url'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrfToken, $submittedToken)) {
        $error = 'Invalid CSRF token.';
    } else {
        $url = trim($urlInput);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error = 'Invalid URL format.';
        } else {
            $parts = parse_url($url);
            if (!isset($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
                $error = 'Only http and https URLs are allowed.';
            } else {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'User-Agent: SocialAppBot/1.0 (+https://example.com)',
                    'Accept: */*'
                ]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                $content = curl_exec($ch);
                $curlError = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);

                if ($content === false) {
                    $error = 'Request failed: ' . trim($curlError);
                } else {
                    if (is_string($content) && stripos($contentType ?? '', 'text/html') !== false) {
                        echo $content;
                        exit;
                    } else {
                        $httpCode = $httpCode ?? 200;
                        $contents = '<pre>' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '</pre>';
                        http_response_code($httpCode);
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Fetch URL</title>
</head>
<body>
<?php
if ($error) {
    echo '<p>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
}
?>
<form method="POST" action="fetch.php" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
<label for="url">URL:</label>
<input id="url" name="url" type="url" placeholder="https://example.com" required>
<button type="submit">Fetch</button>
</form>
<?php
if ($contents) {
    echo $contents;
}
?>
</body>
</html>
?>