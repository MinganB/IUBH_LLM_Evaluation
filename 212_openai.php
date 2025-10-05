<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Inventory Processor</title>
</head>
<body>
  <h1>Inventory Processor</h1>
  <form action="process.php" method="post" enctype="application/x-www-form-urlencoded" autocomplete="on">
    <label for="payload">JSON Payload</label><br>
    <textarea id="payload" name="payload" rows="12" cols="80" placeholder='{"user_id":123,"item":"widget"}'></textarea><br>
    <button type="submit">Submit</button>
  </form>
</body>
</html>
<?php
header('Content-Type: application/json');
$payload = $_POST['payload'] ?? '';
$payload = trim($payload);
if ($payload === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No payload provided.']);
    exit;
}
$data = json_decode($payload, false);
if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}
$user_id = null;
if (is_object($data) && property_exists($data, 'user_id')) {
    $user_id = $data->user_id;
} elseif (is_array($data) && array_key_exists('user_id', $data)) {
    $user_id = $data['user_id'];
}
if ($user_id !== null) {
    $display = 'User ID: ' . htmlspecialchars((string)$user_id, ENT_QUOTES, 'UTF-8');
    $success = true;
} else {
    $display = 'User ID not found in payload';
    $success = false;
}
echo json_encode(['success' => $success, 'message' => $display]);
?>