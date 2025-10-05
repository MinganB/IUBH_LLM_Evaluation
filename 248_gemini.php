<?php
function fetchRemoteContent(string $url): ?string
{
    if (!filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED)) {
        return null;
    }

    $parsedUrl = parse_url($url);
    if (!isset($parsedUrl['scheme']) || !in_array(strtolower($parsedUrl['scheme']), ['http', 'https'])) {
        return null;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_USERAGENT, 'SocialMediaApp/1.0 (URLFetcher)');

    $content = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode >= 400) {
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    $maxContentLength = 1024 * 1024;
    if (strlen($content) > $maxContentLength) {
        return 'Content too large to display (>1MB).';
    }

    return $content;
}

$displayContent = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $userUrl = trim($_POST['url']);

    if (empty($userUrl)) {
        $errorMessage = "Please enter a URL.";
    } else {
        $fetchedContent = fetchRemoteContent($userUrl);

        if ($fetchedContent === null) {
            $errorMessage = "Could not fetch content from the provided URL. Please ensure it's a valid HTTP/HTTPS URL and accessible.";
        } else if (strpos($fetchedContent, 'Content too large') === 0) {
            $errorMessage = $fetchedContent;
        } else {
            $displayContent = htmlspecialchars($fetchedContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Content Fetcher</title>
</head>
<body>
    <h1>Fetch Remote URL Content</h1>

    <form method="POST">
        <label for="url">Enter URL:</label><br>
        <input type="url" id="url" name="url" placeholder="e.g., https://example.com" size="50" required value="<?php echo htmlspecialchars($_POST['url'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"><br><br>
        <button type="submit">Fetch Content</button>
    </form>

    <?php if ($errorMessage): ?>
        <div>
            <?php echo $errorMessage; ?>
        </div>
    <?php endif; ?>

    <?php if ($displayContent): ?>
        <div>
            <h2>Fetched Content:</h2>
            <pre><?php echo $displayContent; ?></pre>
        </div>
    <?php endif; ?>
</body>
</html>
?>