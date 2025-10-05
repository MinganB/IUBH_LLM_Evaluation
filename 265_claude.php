**index.html**

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
        <input type="text" name="url" placeholder="Enter URL" required>
        <button type="submit">Fetch Content</button>
    </form>
</body>
</html>


**fetch.php**

<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $url = $_POST['url'];
    
    $content = @file_get_contents($url);
    
    if ($content !== false) {
        echo json_encode([
            'success' => true,
            'content' => $content
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'content' => null
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'content' => null
    ]);
}
?>