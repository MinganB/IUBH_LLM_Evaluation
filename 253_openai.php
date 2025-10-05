<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>URL Fetch Form</title>
</head>
<body>
<form method="post" action="fetch.php" accept-charset="UTF-8">
<input type="url" name="url" placeholder="Enter URL to fetch" required>
<button type="submit">Fetch</button>
</form>
</body>
</html>
<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['url'])) {
    http_response_code(400);
    echo 'No URL provided';
    exit;
}
$url = trim($_POST['url']);
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo 'Invalid URL';
    exit;
}
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'URLFetcher/1.0');
$response = curl_exec($ch);
if ($response === false) {
    http_response_code(502);
    echo 'Error fetching URL';
    curl_close($ch);
    exit;
}
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
curl_close($ch);
$contentType = 'text/html; charset=UTF-8';
foreach (explode("\r\n", $header) as $line) {
    if (stripos($line, 'Content-Type:') === 0) {
        $ct = trim(substr($line, strlen('Content-Type:')));
        if ($ct) {
            $contentType = $ct;
            break;
        }
    }
}
header('Content-Type: ' . $contentType);
echo $body;
?>