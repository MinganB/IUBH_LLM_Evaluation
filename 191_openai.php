<?php
session_start();

$errors = [];
$result = null;

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $token) {
        $errors[] = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        $payload = isset($_POST['payload']) ? $_POST['payload'] : '';
        $payload = trim($payload);
        if ($payload === '') {
            $errors[] = 'Serialized data is empty.';
        } elseif (mb_strlen($payload) > 1000000) {
            $errors[] = 'Serialized data is too large.';
        } else {
            try {
                $result = unserialize($payload, ['allowed_classes' => false]);
            } catch (Throwable $e) {
                $errors[] = 'Unserialize error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Serialized Data Processor</title>
</head>
<body>
    <h1>Serialized Data Processor</h1>

    <?php if (!empty($errors)): ?>
        <div role="alert" aria-live="polite">
            <?php foreach ($errors as $e): ?>
                <div><?php echo htmlspecialchars($e); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($result !== null): ?>
        <h2>Deserialized Result</h2>
        <pre><?php print_r($result); ?></pre>
    <?php endif; ?>

    <form method="post" action="">
        <label for="payload">Serialized Data</label><br>
        <textarea id="payload" name="payload" rows="12" cols="80"><?php echo isset($_POST['payload']) ? htmlspecialchars($_POST['payload']) : ''; ?></textarea><br><br>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token); ?>">
        <button type="submit">Deserialize</button>
    </form>
</body>
</html>
?>