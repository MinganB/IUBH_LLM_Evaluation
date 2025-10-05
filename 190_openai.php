<?php
$results = null;
$error = '';
$serialized = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serialized = isset($_POST['serialized_data']) ? $_POST['serialized_data'] : '';
    $allowObjects = isset($_POST['allow_objects']) && $_POST['allow_objects'] === '1';

    if (trim($serialized) === '') {
        $error = 'Please provide serialized data.';
    } else {
        $options = ['allowed_classes' => false];
        if ($allowObjects) {
            $options['allowed_classes'] = true;
        }

        $unserialized = @unserialize($serialized, $options);
        if ($unserialized === false && $serialized !== 'b:0;') {
            $error = 'Unserialization failed. The data may be invalid or contain disallowed objects.';
        } else {
            $results = $unserialized;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Serialized Data Processor</title>
</head>
<body>
<h1>Serialized Data Processor</h1>
<form method="post" action="">
<label for="serialized_data">Serialized data</label><br/>
<textarea id="serialized_data" name="serialized_data" rows="12" cols="80"><?php echo htmlspecialchars($serialized); ?></textarea><br/>
<label><input type="checkbox" name="allow_objects" value="1" <?php if (isset($_POST['allow_objects']) && $_POST['allow_objects'] === '1') echo 'checked'; ?>> Allow objects during unserialize</label><br/>
<input type="submit" value="Unserialize">
</form>

<?php if ($error): ?>
<p><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<?php if ($results !== null): ?>
<h2>Unserialized Result</h2>
<pre><?php echo htmlspecialchars(print_r($results, true)); ?></pre>
<?php endif; ?>
</body>
</html>
?>