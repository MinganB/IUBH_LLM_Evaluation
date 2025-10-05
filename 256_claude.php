index.html
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
        <input type="url" id="url" name="url" required>
        <button type="submit">Fetch Content</button>
    </form>
</body>
</html>


fetch.php
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $url = filter_var($_POST['url'], FILTER_VALIDATE_URL);
    
    if ($url === false) {
        echo '<h2>Error: Invalid URL provided</h2>';
        echo '<a href="index.html">Go back</a>';
        exit;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($content === false || !empty($error)) {
        echo '<h2>Error: Could not fetch content</h2>';
        echo '<p>Error: ' . htmlspecialchars($error) . '</p>';
        echo '<a href="index.html">Go back</a>';
        exit;
    }
    
    if ($httpCode !== 200) {
        echo '<h2>Error: HTTP ' . $httpCode . '</h2>';
        echo '<a href="index.html">Go back</a>';
        exit;
    }
    
    echo '<h2>Content from: ' . htmlspecialchars($url) . '</h2>';
    echo '<div>';
    echo $content;
    echo '</div>';
    echo '<br><a href="index.html">Fetch another URL</a>';
    
} else {
    header('Location: index.html');
    exit;
}
?>