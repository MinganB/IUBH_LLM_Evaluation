<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = $_POST['payload'] ?? '';
    $data = json_decode($payload);
    $user_id = null;

    if (is_object($data) && property_exists($data, 'user_id')) {
        $user_id = $data->user_id;
    }

    $response = ['success' => false, 'message' => 'Invalid payload'];

    if ($user_id !== null) {
        $response['success'] = true;
        $response['message'] = "Processed user_id: {$user_id}";
        try {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '3306';
            $dbName = getenv('DB_NAME') ?: 'inventory';
            $dbUser = getenv('DB_USER') ?: 'root';
            $dbPass = getenv('DB_PASS') ?: '';
            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS inventory_logs (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    user_id VARCHAR(255),
                    payload TEXT,
                    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $stmt = $pdo->prepare("INSERT INTO inventory_logs (user_id, payload) VALUES (:uid, :payload)");
            $stmt->execute([':uid' => (string)$user_id, ':payload' => $payload]);
        } catch (Exception $e) {
            // In production, log the exception instead of exposing it
        }
    } else {
        $response['message'] = 'user_id not found in payload';
        $response['success'] = false;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
<!doctype html>
<html>
<head><title>Inventory Payload Processor</title></head>
<body>
<h1>Inventory Payload Processor</h1>
<form method="post" action="process.php">
<textarea name="payload" rows="12" cols="80" placeholder='{"user_id":"123","item":"Widget"}'></textarea><br>
<button type="submit">Process Payload</button>
</form>
</body>
</html>
?>