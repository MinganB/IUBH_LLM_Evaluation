<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>JSON Processor</title>
</head>
<body>
<form method="POST" action="process.php">
<textarea name="payload" rows="10" cols="60" placeholder='{"user_id": 123, "name": "John"}'></textarea>
<br>
<button type="submit">Submit</button>
</form>
</body>
</html>
<?php
header('Content-Type: application/json; charset=utf-8');
?>