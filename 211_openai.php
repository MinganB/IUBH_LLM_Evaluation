<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Inventory Processor</title>
</head>
<body>
  <form method="post" action="process.php">
    <textarea name="payload" rows="12" cols="60" placeholder='{"user_id":123,"item_id":"ABC"}' required></textarea>
    <br>
    <button type="submit">Process</button>
  </form>
</body>
</html>

<?php
header('Content-Type: application/json; charset=utf-8');
$payload = isset($_POST['payload']) ? $_POST['payload'] : '';
if ($payload === '') {
  echo json_encode(['success' => false, 'message' => 'Payload is missing']);
  exit;
}
$data = json_decode($payload);
if (json_last_error() !== JSON_ERROR_NONE) {
  echo json_encode(['success' => false, 'message' => 'JSON decode error: ' . json_last_error_msg()]);
  exit;
}
$userId = null;
if (is_object($data) && property_exists($data, 'user_id')) {
  $userId = $data->user_id;
} elseif (is_array($data) && array_key_exists('user_id', $data)) {
  $userId = $data['user_id'];
}
$display = ($userId !== null) ? $userId : 'not found';
echo json_encode(['success' => true, 'message' => 'User ID: ' . $display]);
?>