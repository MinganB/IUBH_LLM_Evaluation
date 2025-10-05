<!DOCTYPE html>
<html>
<head>
  <title>URL Fetch</title>
</head>
<body>
  <form id="fetchForm" method="post" action="fetch.php">
    <input type="text" name="url" placeholder="Enter URL" required />
    <button type="submit">Fetch</button>
  </form>
  <pre id="result"></pre>
  <script>
    document.getElementById('fetchForm').addEventListener('submit', function(e){
      e.preventDefault();
      var form = new FormData(this);
      fetch('fetch.php', {
        method: 'POST',
        body: new URLSearchParams(form)
      })
      .then(function(res){ return res.json(); })
      .then(function(data){ document.getElementById('result').textContent = JSON.stringify(data, null, 2); })
      .catch(function(){
        document.getElementById('result').textContent = JSON.stringify({success:false, content:'Request failed'}, null, 2);
      });
    });
  </script>
</body>
</html>
<?php
header('Content-Type: application/json');

$response = ['success' => false, 'content' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = isset($_POST['url']) ? trim($_POST['url']) : '';

    if ($url === '') {
        $response['content'] = 'URL is required';
        echo json_encode($response);
        exit;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $response['content'] = 'Invalid URL';
        echo json_encode($response);
        exit;
    }

    $context = stream_context_create(['http' => ['timeout' => 10]]);

    $data = @file_get_contents($url, false, $context);

    if ($data === false) {
        $response['content'] = 'Failed to fetch URL';
        echo json_encode($response);
        exit;
    }

    $response['success'] = true;
    $response['content'] = $data;
    echo json_encode($response);
    exit;
}

$response['content'] = 'Invalid request method';
echo json_encode($response);
?>