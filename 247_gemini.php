<?php

function fetchUrlContent(string $url): ?string
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_USERAGENT, 'SocialMediaAppContentFetcher/1.0 (PHP)');

    $content = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($content === false || $httpCode >= 400 || !empty($error)) {
        return null;
    }

    return $content;
}

$displayContent = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $submittedUrl = trim($_POST['url']);

    if (filter_var($submittedUrl, FILTER_VALIDATE_URL)) {
        $fetchedContent = fetchUrlContent($submittedUrl);

        if ($fetchedContent !== null) {
            $displayContent = htmlspecialchars(strip_tags($fetchedContent));
        } else {
            $errorMessage = "Could not fetch content from the provided URL. Please check the URL or try again.";
        }
    } else {
        $errorMessage = "Please enter a valid URL.";
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

    <form method="post">
        <label for="url">Enter URL:</label><br>
        <input type="url" id="url" name="url" placeholder="https://example.com" value="<?php echo htmlspecialchars($_POST['url'] ?? ''); ?>" size="60" required><br><br>
        <button type="submit">Fetch Content</button>
    </form>

    <?php if ($errorMessage): ?>
        <p style="color: red;"><?php echo htmlspecialchars($errorMessage); ?></p>
    <?php endif; ?>

    <?php if ($displayContent !== null): ?>
        <h2>Content from <?php echo htmlspecialchars($submittedUrl); ?>:</h2>
        <pre style="white-space: pre-wrap; word-break: break-all; border: 1px solid #ccc; padding: 10px; background-color: #f9f9f9; max-height: 500px; overflow-y: auto;"><?php echo $displayContent; ?></pre>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errorMessage): ?>
        <p>No content to display or an error occurred.</p>
    <?php endif; ?>

</body>
</html>
?>