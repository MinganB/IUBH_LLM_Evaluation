<!DOCTYPE html>
<html>
<head>
  <title>JSON Payload Submission</title>
</head>
<body>
  <h1>Submit JSON Payload</h1>
  <form action="process.php" method="post" autocomplete="off">
    <label for="payload">JSON Payload</label><br>
    <textarea id="payload" name="payload" rows="12" cols="80" placeholder='{"user_id": 123}'></textarea><br><br>
    <button type="submit">Submit</button>
  </form>
</body>
</html>
<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$response = ['success' => false, 'message' => 'Unknown error'];
$payload = '';

if (isset($_POST['payload'])) {
    $payload = $_POST['payload'];
} else {
    $raw = trim(file_get_contents('php://input'));
    if ($raw !== '') {
        $payload = $raw;
    }
}

if (!is_string($payload) || $payload === '') {
    $response['message'] = 'No JSON payload provided';
    echo json_encode($response);
    exit;
}

$json = json_decode($payload);

if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Invalid JSON payload';
    echo json_encode($response);
    exit;
}

if ($json === null) {
    $response['message'] = 'JSON payload is null';
    echo json_encode($response);
    exit;
}

$targetProperty = 'user_id';
$displayValue = null;

if (is_object($json) && property_exists($json, $targetProperty)) {
    $displayValue = $json->{$targetProperty};
} elseif (is_array($json) && array_key_exists($targetProperty, $json)) {
    $displayValue = $json[$targetProperty];
} else {
    $response['message'] = 'Property not found: ' . $targetProperty;
    echo json_encode($response);
    exit;
}

if (!is_scalar($displayValue)) {
    $response['message'] = 'Property value is not scalar';
    echo json_encode($response);
    exit;
}

$response['success'] = true;
$response['message'] = 'Property retrieved successfully';
$response['user_id'] = $displayValue;

echo json_encode($response);
exit;
?>