<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Real-time Page Preview</title>
</head>
<body>
<h1>Share a Page</h1>
<form id="shareForm" autocomplete="off">
  <input type="text" name="url" id="url" placeholder="Enter URL to preview" required>
  <button type="submit">Preview</button>
</form>
<div id="status" style="margin-top:8px;"></div>
<div id="preview" style="margin-top:12px; border:1px solid #ddd; padding:8px;"></div>
<script>
(function(){
  const form = document.getElementById('shareForm');
  const status = document.getElementById('status');
  const preview = document.getElementById('preview');
  form.addEventListener('submit', async function(e){
    e.preventDefault();
    const url = document.getElementById('url').value;
    status.textContent = 'Fetching...';
    try {
      const res = await fetch('fetch.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({url}).toString()
      });
      const data = await res.json();
      if (data.success) {
        preview.innerHTML = data.content;
        status.textContent = 'Preview loaded';
      } else {
        preview.innerHTML = '';
        status.textContent = 'Failed to fetch content';
      }
    } catch (err) {
      status.textContent = 'Error';
    }
  });
})();
</script>
</body>
</html>
<?php
$url = $_POST['url'] ?? '';
header('Content-Type: application/json');
if (!is_string($url) || trim($url) === '') {
    echo json_encode(['success' => false, 'content' => '']);
    exit;
}
$content = @file_get_contents($url);
$success = ($content !== false);
echo json_encode(['success' => $success, 'content' => $content ?? '']);
?>