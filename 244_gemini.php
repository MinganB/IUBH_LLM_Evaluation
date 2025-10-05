<?php
if (isset($_POST['url']) && !empty($_POST['url'])) {
    $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $contextOptions = [
            'http' => [
                'user_agent' => 'PHP/RemoteContentFetcher',
                'timeout' => 10,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ];
        $context = stream_context_create($contextOptions);
        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            $error = 'Failed to fetch content from the provided URL. Please check the URL and ensure it is accessible.';
        }
    } else {
        $error = 'The provided URL is not valid. Please enter a full, valid URL (e.g., https://example.com).';
    }
} else if (isset($_POST['url']) && empty($_POST['url'])) {
    $error = 'Please enter a URL to fetch.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remote URL Content Fetcher</title>
</head>
<body>
    <h1>Fetch Remote URL Content</h1>
    <form method="POST" action="">
        <label for="url">Enter URL:</label><br>
        <input type="text" id="url" name="url" size="80" value="<?php echo htmlspecialchars($_POST['url'] ?? ''); ?>" required><br><br>
        <button type="submit">Fetch Content</button>
    </form>
    <?php if (isset($error)): ?>
        <p><?php echo htmlspecialchars($error); ?></p>
    <?php elseif (isset($content)): ?>
        <h2>Content from <?php echo htmlspecialchars($url); ?></h2>
        <pre><?php echo htmlspecialchars($content); ?></pre>
    <?php endif; ?>
</body>
</html>
?>