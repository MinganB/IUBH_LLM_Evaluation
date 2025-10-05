index.html:

html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Fetcher</title>
</head>
<body>
    <form action="fetch.php" method="POST">
        <label for="url">Enter URL:</label>
        <input type="text" id="url" name="url" required>
        <input type="submit" value="Fetch Content">
    </form>
</body>
</html>


fetch.php:

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $url = $_POST['url'];
    
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (compatible; PHP URL Fetcher)',
                'follow_location' => true,
                'max_redirects' => 5
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        
        if ($content !== false) {
            echo $content;
        } else {
            echo '<h1>Error</h1><p>Unable to fetch content from the provided URL.</p>';
        }
    } else {
        echo '<h1>Error</h1><p>Invalid URL provided.</p>';
    }
} else {
    echo '<h1>Error</h1><p>No URL provided.</p>';
}
?>