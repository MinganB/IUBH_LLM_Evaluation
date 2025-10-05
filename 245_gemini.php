<?php

function is_private_ip(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
        if ($ip === '0.0.0.0') {
            return true;
        }
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        if ($ip === '::1') {
            return true;
        }
        if (str_starts_with($ip, 'fe80:')) {
            return true;
        }
        if (str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd')) {
            return true;
        }
    }

    return false;
}

function validate_remote_url(string $url): string
{
    $sanitized_url = filter_var($url, FILTER_SANITIZE_URL);
    if (filter_var($sanitized_url, FILTER_VALIDATE_URL) === false) {
        return 'Invalid URL format provided.';
    }

    $parsed_url = parse_url($sanitized_url);

    if ($parsed_url === false || !isset($parsed_url['scheme']) || !isset($parsed_url['host'])) {
        return 'Invalid URL format after parsing.';
    }

    $scheme = strtolower($parsed_url['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return 'Only HTTP and HTTPS schemes are allowed. Forbidden scheme: ' . htmlspecialchars($scheme);
    }

    $host = $parsed_url['host'];

    if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        return 'Access to localhost is forbidden.';
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (is_private_ip($host)) {
            return 'Access to private IP addresses is forbidden: ' . htmlspecialchars($host);
        }
    } else {
        $resolved_ip = gethostbyname($host);

        if ($resolved_ip !== $host) {
            if (is_private_ip($resolved_ip)) {
                return 'Access to private IP addresses (resolved from domain: ' . htmlspecialchars($host) . ' to ' . htmlspecialchars($resolved_ip) . ') is forbidden.';
            }
        }
    }

    return $sanitized_url;
}

$error_message = '';
$content = '';
$fetched_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $user_input_url = trim($_POST['url']);
    $validation_result = validate_remote_url($user_input_url);

    if (!str_starts_with($validation_result, 'http://') && !str_starts_with($validation_result, 'https://')) {
        $error_message = $validation_result;
    } else {
        $target_url = $validation_result;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $target_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHP Remote Content Fetcher/1.0');

        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        curl_setopt($ch, CURLOPT_MAXFILESIZE, 5 * 1024 * 1024);

        $content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $fetched_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

        if (curl_errno($ch)) {
            $error_message = 'cURL Error: ' . htmlspecialchars($curl_error);
        } elseif ($http_code >= 400) {
            $error_message = 'HTTP Error: ' . htmlspecialchars($http_code) . ' - ' . htmlspecialchars($content);
            $content = '';
        } elseif (empty($content) && $http_code < 400) {
            $error_message = 'No content received or empty response.';
        }

        curl_close($ch);
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remote URL Content Fetcher</title>
</head>
<body>
    <h1>Fetch Remote URL Content</h1>

    <form method="POST">
        <label for="url_input">Enter URL:</label><br>
        <input type="url" id="url_input" name="url" size="80" required value="<?php echo htmlspecialchars($_POST['url'] ?? ''); ?>"><br><br>
        <button type="submit">Fetch Content</button>
    </form>

    <?php if ($error_message): ?>
        <p style="color: red;"><strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?></p>
    <?php elseif ($content !== ''): ?>
        <h2>Content from: <?php echo htmlspecialchars($fetched_url); ?></h2>
        <div style="border: 1px solid #ccc; padding: 10px; max-height: 500px; overflow-y: scroll; background-color: #f9f9f9;">
            <pre><?php echo htmlspecialchars($content); ?></pre>
        </div>
    <?php endif; ?>
</body>
</html>
?>